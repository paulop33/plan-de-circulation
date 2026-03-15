<?php

namespace App\Command;

use Doctrine\DBAL\Connection;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Contracts\HttpClient\HttpClientInterface;

#[AsCommand(
    name: 'app:import-parlons-velo',
    description: 'Importe les points rouges Parlons Vélo depuis un fichier GeoJSON zippé dans PostGIS',
)]
class ImportParlonsVeloCommand extends Command
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly Connection $connection,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('url', null, InputOption::VALUE_REQUIRED, 'URL du fichier ZIP à télécharger');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $url = $input->getOption('url')
            ?? $_ENV['PARLONS_VELO_URL']
            ?? getenv('PARLONS_VELO_URL')
            ?: 'https://opendata.parlons-velo.fr/download/cc96c79e1c460a08d122b8d251286438.zip';

        // 1. Télécharger le ZIP
        $io->section('Téléchargement du fichier Parlons Vélo...');
        $response = $this->httpClient->request('GET', $url, ['timeout' => 300]);

        $tmpDir = sys_get_temp_dir() . '/parlons_velo_import_' . uniqid();
        mkdir($tmpDir, 0777, true);
        $zipFile = $tmpDir . '/parlons_velo.zip';
        file_put_contents($zipFile, $response->getContent());
        $io->success('Fichier téléchargé.');

        // 2. Extraire le ZIP
        $io->section('Extraction...');
        $zip = new \ZipArchive();
        if ($zip->open($zipFile) !== true) {
            $io->error('Impossible d\'ouvrir le fichier zip.');
            $this->removeDirectory($tmpDir);
            return Command::FAILURE;
        }
        $zip->extractTo($tmpDir);
        $zip->close();

        // Trouver le fichier GeoJSON
        $geojsonFiles = glob($tmpDir . '/points-rouges-*.geojson');
        if (empty($geojsonFiles)) {
            $io->error('Aucun fichier points-rouges-*.geojson trouvé dans l\'archive.');
            $this->removeDirectory($tmpDir);
            return Command::FAILURE;
        }
        $geojsonFile = $geojsonFiles[0];
        $io->success('Fichier trouvé : ' . basename($geojsonFile));

        // 3. Parser le GeoJSON
        $io->section('Parsing du GeoJSON...');
        $geojson = json_decode(file_get_contents($geojsonFile), true);
        if (!$geojson || !isset($geojson['features'])) {
            $io->error('Fichier GeoJSON invalide.');
            $this->removeDirectory($tmpDir);
            return Command::FAILURE;
        }
        $io->success(sprintf('%d features trouvées.', count($geojson['features'])));

        // 4. Créer la table
        $io->section('Création de la table parlons_velo_points...');
        $this->connection->executeStatement('DROP TABLE IF EXISTS parlons_velo_points');
        $this->connection->executeStatement(<<<SQL
            CREATE TABLE parlons_velo_points (
                id SERIAL PRIMARY KEY,
                commune VARCHAR(10),
                description TEXT,
                geom geometry(Point, 4326)
            )
        SQL);

        // 5. Insérer les features
        $io->section('Insertion des points...');
        $count = 0;
        foreach ($geojson['features'] as $feature) {
            $coords = $feature['geometry']['coordinates'] ?? null;
            if (!$coords || $feature['geometry']['type'] !== 'Point') {
                continue;
            }

            $lon = (float) $coords[0];
            $lat = (float) $coords[1];
            $props = $feature['properties'] ?? [];

            $this->connection->executeStatement(
                "INSERT INTO parlons_velo_points (commune, description, geom) VALUES (:commune, :description, ST_SetSRID(ST_MakePoint(:lon, :lat), 4326))",
                [
                    'commune' => $props['commune'] ?? null,
                    'description' => $props['description'] ?? null,
                    'lon' => $lon,
                    'lat' => $lat,
                ]
            );
            $count++;
        }

        // 6. Créer l'index spatial
        $this->connection->executeStatement('CREATE INDEX idx_parlons_velo_geom ON parlons_velo_points USING GIST (geom)');

        $io->success(sprintf('Import terminé : %d points insérés.', $count));

        // 7. Nettoyage
        $this->removeDirectory($tmpDir);

        return Command::SUCCESS;
    }

    private function removeDirectory(string $dir): void
    {
        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($files as $file) {
            if ($file->isDir()) {
                rmdir($file->getRealPath());
            } else {
                unlink($file->getRealPath());
            }
        }
        rmdir($dir);
    }
}
