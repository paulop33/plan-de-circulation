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
    name: 'app:import-gtfs',
    description: 'Importe les lignes de transport en commun depuis un fichier GTFS dans PostGIS',
)]
class ImportGtfsCommand extends Command
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

        $gtfsUrl = $_ENV['GTFS_URL'] ?? getenv('GTFS_URL') ?: 'https://bdx.mecatran.com/utw/ws/gtfsfeed/static/bordeaux?apiKey=opendata-bordeaux-metropole-flux-gtfs-rt';

        // 1. Télécharger le GTFS zip
        $io->section('Téléchargement du GTFS...');
        $response = $this->httpClient->request('GET', $gtfsUrl, ['timeout' => 300]);

        $tmpDir = sys_get_temp_dir() . '/gtfs_import_' . uniqid();
        mkdir($tmpDir, 0777, true);
        $zipFile = $tmpDir . '/gtfs.zip';
        file_put_contents($zipFile, $response->getContent());
        $io->success('GTFS téléchargé.');

        // 2. Extraire le zip
        $io->section('Extraction...');
        $zip = new \ZipArchive();
        if ($zip->open($zipFile) !== true) {
            $io->error('Impossible d\'ouvrir le fichier zip.');
            return Command::FAILURE;
        }
        $zip->extractTo($tmpDir);
        $zip->close();

        // 3. Parser routes.txt
        $io->section('Parsing routes.txt...');
        $routes = [];
        $handle = fopen($tmpDir . '/routes.txt', 'r');
        $header = fgetcsv($handle);
        $idx = array_flip($header);
        while (($row = fgetcsv($handle)) !== false) {
            $routeId = $row[$idx['route_id']];
            $routes[$routeId] = [
                'route_short_name' => $row[$idx['route_short_name']],
                'route_type' => (int) $row[$idx['route_type']],
                'route_color' => isset($idx['route_color']) ? $row[$idx['route_color']] : '',
            ];
        }
        fclose($handle);
        $io->success(sprintf('%d routes parsées.', count($routes)));

        // 4. Parser trips.txt — dédupliquer : un shape_id par (route_id, direction_id)
        $io->section('Parsing trips.txt...');
        $tripMap = []; // key: "route_id-direction_id" => shape_id
        $handle = fopen($tmpDir . '/trips.txt', 'r');
        $header = fgetcsv($handle);
        $idx = array_flip($header);
        while (($row = fgetcsv($handle)) !== false) {
            $routeId = $row[$idx['route_id']];
            $directionId = isset($idx['direction_id']) ? (int) $row[$idx['direction_id']] : 0;
            $shapeId = isset($idx['shape_id']) ? $row[$idx['shape_id']] : null;
            if ($shapeId === null || $shapeId === '') {
                continue;
            }
            $key = $routeId . '-' . $directionId;
            if (!isset($tripMap[$key])) {
                $tripMap[$key] = [
                    'route_id' => $routeId,
                    'direction_id' => $directionId,
                    'shape_id' => $shapeId,
                ];
            }
        }
        fclose($handle);
        $io->success(sprintf('%d combinaisons route/direction.', count($tripMap)));

        // 5. Parser shapes.txt (uniquement les shapes nécessaires)
        $io->section('Parsing shapes.txt...');
        $neededShapeIds = [];
        foreach ($tripMap as $entry) {
            $neededShapeIds[$entry['shape_id']] = true;
        }

        $shapes = []; // shape_id => [[lon, lat, seq], ...]
        $handle = fopen($tmpDir . '/shapes.txt', 'r');
        $header = fgetcsv($handle);
        $idx = array_flip($header);
        while (($row = fgetcsv($handle)) !== false) {
            $shapeId = $row[$idx['shape_id']];
            if (!isset($neededShapeIds[$shapeId])) {
                continue;
            }
            $shapes[$shapeId][] = [
                'lon' => (float) $row[$idx['shape_pt_lon']],
                'lat' => (float) $row[$idx['shape_pt_lat']],
                'seq' => (int) $row[$idx['shape_pt_sequence']],
            ];
        }
        fclose($handle);

        // Trier par séquence
        foreach ($shapes as &$points) {
            usort($points, fn($a, $b) => $a['seq'] <=> $b['seq']);
        }
        unset($points);
        $io->success(sprintf('%d shapes parsées.', count($shapes)));

        // 6. Créer la table
        $io->section('Création de la table gtfs_routes...');
        $this->connection->executeStatement('DROP TABLE IF EXISTS gtfs_routes');
        $this->connection->executeStatement(<<<SQL
            CREATE TABLE gtfs_routes (
                id SERIAL PRIMARY KEY,
                route_short_name VARCHAR(50),
                route_type INTEGER,
                route_color VARCHAR(10),
                direction_id INTEGER,
                geom geometry(LineString, 4326)
            )
        SQL);

        // 7. Insérer les lignes
        $io->section('Insertion des lignes...');
        $tramCount = 0;
        $busCount = 0;

        foreach ($tripMap as $entry) {
            $routeId = $entry['route_id'];
            $directionId = $entry['direction_id'];
            $shapeId = $entry['shape_id'];

            if (!isset($routes[$routeId]) || !isset($shapes[$shapeId])) {
                continue;
            }

            $route = $routes[$routeId];
            $points = $shapes[$shapeId];

            if (count($points) < 2) {
                continue;
            }

            $coords = implode(',', array_map(
                fn($p) => $p['lon'] . ' ' . $p['lat'],
                $points
            ));
            $wkt = 'LINESTRING(' . $coords . ')';

            $this->connection->executeStatement(
                "INSERT INTO gtfs_routes (route_short_name, route_type, route_color, direction_id, geom) VALUES (:name, :type, :color, :dir, ST_GeomFromText(:wkt, 4326))",
                [
                    'name' => $route['route_short_name'],
                    'type' => $route['route_type'],
                    'color' => $route['route_color'],
                    'dir' => $directionId,
                    'wkt' => $wkt,
                ]
            );

            if ($route['route_type'] === 0) {
                $tramCount++;
            } elseif ($route['route_type'] === 3) {
                $busCount++;
            }
        }

        $totalCount = $this->connection->fetchOne('SELECT COUNT(*) FROM gtfs_routes');
        $io->success(sprintf('Import terminé : %d lignes (%d tram, %d bus).', $totalCount, $tramCount, $busCount));

        // 8. Nettoyage
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
