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

        // Créer la table si elle n'existe pas
        $io->section('Création de la table osm_data si nécessaire...');
        $this->connection->executeStatement(<<<SQL
            CREATE TABLE IF NOT EXISTS osm_data (
                ogc_fid SERIAL PRIMARY KEY,
                geom geometry(Geometry, 4326),
                name VARCHAR(255),
                id VARCHAR(255),
                oneway VARCHAR(10),
                highway VARCHAR(50)
            )
        SQL);
        $this->connection->executeStatement('TRUNCATE TABLE osm_data');
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

        $io->success(sprintf('Import terminé : %d entités dans osm_data.', $count));

        // Nettoyage
        @unlink($osmFile);

        return Command::SUCCESS;
    }
}
