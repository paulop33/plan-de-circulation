<?php

namespace App\Command;

use Doctrine\DBAL\Connection;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Contracts\HttpClient\HttpClientInterface;

#[AsCommand(
    name: 'app:import-overpass',
    description: 'Importe les données routières depuis l\'API Overpass dans PostGIS',
)]
class ImportOverpassCommand extends Command
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly Connection $connection,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        // Bbox depuis variables d'environnement (défaut : Bordeaux Métropole)
        $bbox = sprintf('%s,%s,%s,%s',
            $_ENV['IMPORT_MIN_LAT'] ?? getenv('IMPORT_MIN_LAT') ?: '44.78',
            $_ENV['IMPORT_MIN_LON'] ?? getenv('IMPORT_MIN_LON') ?: '-0.65',
            $_ENV['IMPORT_MAX_LAT'] ?? getenv('IMPORT_MAX_LAT') ?: '44.92',
            $_ENV['IMPORT_MAX_LON'] ?? getenv('IMPORT_MAX_LON') ?: '-0.50',
        );
        $io->info(sprintf('Bbox : %s', $bbox));

        $query = <<<OVERPASS
[out:xml][timeout:300];
(
  way[highway=primary]({$bbox});
  way[highway=secondary]({$bbox});
  way[highway=tertiary]({$bbox});
  way[highway=residential]({$bbox});
  way[highway=living_street]({$bbox});
  way[highway=pedestrian]({$bbox});
);
out body;
>;
out skel qt;
OVERPASS;

        $io->section('Téléchargement des données depuis Overpass...');

        $response = $this->httpClient->request('POST', 'https://overpass-api.de/api/interpreter', [
            'body' => ['data' => $query],
            'timeout' => 600,
        ]);

        $tmpDir = sys_get_temp_dir();
        $osmFile = $tmpDir.'/overpass_export.osm';

        file_put_contents($osmFile, $response->getContent());
        $io->success('Données téléchargées.');

        // Recréer la table (DROP + CREATE pour s'assurer du schéma correct)
        $io->section('Création de la table osm_data...');
        $this->connection->executeStatement('DROP TABLE IF EXISTS osm_data');
        $this->connection->executeStatement(<<<SQL
            CREATE TABLE osm_data (
                ogc_fid SERIAL PRIMARY KEY,
                geom geometry(Geometry, 4326),
                osm_id VARCHAR(255),
                name VARCHAR(255),
                highway VARCHAR(50),
                oneway VARCHAR(10),
                other_tags TEXT
            )
        SQL);
        $io->success('Table prête.');

        // Importer via ogr2ogr (OSM driver → lines layer → PostGIS)
        $io->section('Import dans PostGIS...');
        $dbUrl = $_ENV['DATABASE_URL'] ?? getenv('DATABASE_URL');
        preg_match('#pdo-pgsql://([^:]+):([^@]+)@([^:]+):(\d+)/(.+)#', $dbUrl, $m);

        $pgConn = sprintf(
            'host=%s port=%s dbname=%s user=%s password=%s',
            $m[3], $m[4], $m[5], $m[1], $m[2]
        );

        // Set OSM_USE_CUSTOM_INDEXING=NO to avoid temp file issues
        $importCmd = sprintf(
            'OSM_USE_CUSTOM_INDEXING=NO ogr2ogr -f "PostgreSQL" PG:"%s" %s lines -nln osm_data -append -lco GEOMETRY_NAME=geom -lco FID=ogc_fid 2>&1',
            $pgConn,
            escapeshellarg($osmFile),
        );
        $importOutput = shell_exec($importCmd);
        if ($importOutput) {
            $io->note($importOutput);
        }

        // Vérifier l'import
        $count = $this->connection->fetchOne('SELECT COUNT(*) FROM osm_data');
        if ($count === 0) {
            $io->error('Aucune donnée importée. Vérifiez la connexion et les logs ci-dessus.');
            @unlink($osmFile);
            return Command::FAILURE;
        }

        // Extraire oneway depuis other_tags (format hstore: "key"=>"value",...)
        $io->section('Extraction du tag oneway...');
        $this->connection->executeStatement(<<<SQL
            UPDATE osm_data
            SET oneway = 'yes'
            WHERE other_tags LIKE '%"oneway"=>"yes"%'
        SQL);
        $onewayCount = $this->connection->fetchOne("SELECT COUNT(*) FROM osm_data WHERE oneway = 'yes'");
        $io->success(sprintf('%d rues en sens unique détectées.', $onewayCount));

        // Détecter les rues bornées (motor_vehicle=no ou destination, hors piétonnes)
        $io->section('Détection des rues bornées...');
        $this->connection->executeStatement("ALTER TABLE osm_data ADD COLUMN IF NOT EXISTS bollard BOOLEAN DEFAULT FALSE");
        $this->connection->executeStatement(<<<SQL
            UPDATE osm_data SET bollard = TRUE
            WHERE (other_tags LIKE '%"motor_vehicle"=>"no"%'
               OR other_tags LIKE '%"motor_vehicle"=>"destination"%')
               AND highway != 'pedestrian'
        SQL);
        $bollardCount = $this->connection->fetchOne("SELECT COUNT(*) FROM osm_data WHERE bollard = TRUE");
        $io->success(sprintf('%d rues bornées détectées.', $bollardCount));

        $pedestrianCount = $this->connection->fetchOne("SELECT COUNT(*) FROM osm_data WHERE highway = 'pedestrian'");
        $io->success(sprintf('%d rues piétonnes détectées.', $pedestrianCount));

        $io->success(sprintf('Import terminé : %d entités dans osm_data.', $count));

        // Nettoyage
        @unlink($osmFile);

        return Command::SUCCESS;
    }
}
