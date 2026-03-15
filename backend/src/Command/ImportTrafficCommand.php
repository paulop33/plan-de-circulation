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
    name: 'app:import-traffic',
    description: 'Importe les comptages moyens de trafic routier depuis l\'open data Bordeaux Métropole',
)]
class ImportTrafficCommand extends Command
{
    private const DEFAULT_DATASETS = [
        2020 => 'comptage_trafic_2020',
        2021 => 'comptage_trafic_2021',
        2022 => 'comptage_trafic_2022',
        2023 => 'comptage-du-trafic-2023-bordeaux-metropole',
        2024 => 'comptage-du-trafic-2024-bordeaux-metropole',
    ];

    private const BASE_URL = 'https://opendata.bordeaux-metropole.fr/api/explore/v2.1/catalog/datasets/%s/exports/geojson?limit=-1';

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly Connection $connection,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $sensorsUrl = $_ENV['TRAFFIC_SENSORS_URL'] ?? getenv('TRAFFIC_SENSORS_URL')
            ?: 'https://opendata.bordeaux-metropole.fr/api/explore/v2.1/catalog/datasets/pc_capte_p/exports/geojson?limit=-1';

        // Build dataset URLs from env or defaults
        $datasets = $this->getDatasets();

        // 1. Télécharger les capteurs (avec géométrie) — une seule fois
        $io->section('Téléchargement des capteurs...');
        $sensorsResponse = $this->httpClient->request('GET', $sensorsUrl, ['timeout' => 120]);
        $sensorsData = json_decode($sensorsResponse->getContent(), true);

        if (!$sensorsData || empty($sensorsData['features'])) {
            $io->error('Aucune donnée de capteurs récupérée.');
            return Command::FAILURE;
        }

        // Indexer les capteurs par ident
        $sensors = [];
        foreach ($sensorsData['features'] as $feature) {
            $ident = $feature['properties']['ident'] ?? null;
            $geometry = $feature['geometry'] ?? null;
            if ($ident && $geometry && $geometry['type'] === 'Point') {
                $sensors[$ident] = $geometry['coordinates'];
            }
        }
        $io->success(sprintf('%d capteurs avec géométrie.', count($sensors)));

        // 2. Créer la table
        $io->section('Création de la table traffic_counts...');
        $this->connection->executeStatement('DROP TABLE IF EXISTS traffic_counts');
        $this->connection->executeStatement(<<<SQL
            CREATE TABLE traffic_counts (
                id SERIAL PRIMARY KEY,
                ident VARCHAR(50),
                nom_voie VARCHAR(255),
                sens_cir VARCHAR(100),
                mjo_val INTEGER,
                hpm_val INTEGER,
                hps_val INTEGER,
                year INTEGER,
                geom geometry(Point, 4326)
            )
        SQL);

        // 3. Boucler sur chaque année
        $totalInserted = 0;
        $totalSkipped = 0;

        foreach ($datasets as $year => $url) {
            $io->section(sprintf('Téléchargement des comptages %d...', $year));
            $countsResponse = $this->httpClient->request('GET', $url, ['timeout' => 120]);
            $countsData = json_decode($countsResponse->getContent(), true);

            if (!$countsData || empty($countsData['features'])) {
                $io->warning(sprintf('Aucune donnée de comptages pour %d, ignoré.', $year));
                continue;
            }
            $io->info(sprintf('%d comptages récupérés pour %d.', count($countsData['features']), $year));

            $inserted = 0;
            $skipped = 0;

            foreach ($countsData['features'] as $feature) {
                $props = $feature['properties'];
                $ident = $props['ident'] ?? null;

                if (!$ident || !isset($sensors[$ident])) {
                    $skipped++;
                    continue;
                }

                $mjoVal = $props['mjo_val'] ?? null;
                if ($mjoVal === null) {
                    $skipped++;
                    continue;
                }

                [$lon, $lat] = $sensors[$ident];

                $this->connection->executeStatement(
                    "INSERT INTO traffic_counts (ident, nom_voie, sens_cir, mjo_val, hpm_val, hps_val, year, geom) VALUES (:ident, :nom_voie, :sens_cir, :mjo_val, :hpm_val, :hps_val, :year, ST_SetSRID(ST_MakePoint(:lon, :lat), 4326))",
                    [
                        'ident' => $ident,
                        'nom_voie' => $props['nom_voie'] ?? null,
                        'sens_cir' => $props['sens_cir'] ?? null,
                        'mjo_val' => (int) $mjoVal,
                        'hpm_val' => isset($props['hpm_val']) ? (int) $props['hpm_val'] : null,
                        'hps_val' => isset($props['hps_val']) ? (int) $props['hps_val'] : null,
                        'year' => $year,
                        'lon' => $lon,
                        'lat' => $lat,
                    ]
                );
                $inserted++;
            }

            $io->success(sprintf('%d : %d insérés, %d ignorés.', $year, $inserted, $skipped));
            $totalInserted += $inserted;
            $totalSkipped += $skipped;
        }

        // 4. Créer un index spatial
        $this->connection->executeStatement('CREATE INDEX idx_traffic_counts_geom ON traffic_counts USING GIST (geom)');

        $io->success(sprintf('Import terminé : %d comptages insérés au total (%d ignorés).', $totalInserted, $totalSkipped));

        return Command::SUCCESS;
    }

    /**
     * @return array<int, string> year => URL
     */
    private function getDatasets(): array
    {
        $envUrls = $_ENV['TRAFFIC_COUNTS_URLS'] ?? getenv('TRAFFIC_COUNTS_URLS');

        if ($envUrls) {
            // Format: "2020=https://...,2021=https://..." or just "https://url1,https://url2"
            $datasets = [];
            foreach (explode(',', $envUrls) as $entry) {
                $entry = trim($entry);
                if (str_contains($entry, '=')) {
                    [$year, $url] = explode('=', $entry, 2);
                    $datasets[(int) $year] = $url;
                }
            }
            if (!empty($datasets)) {
                return $datasets;
            }
        }

        // Default datasets
        $datasets = [];
        foreach (self::DEFAULT_DATASETS as $year => $datasetId) {
            $datasets[$year] = sprintf(self::BASE_URL, $datasetId);
        }
        return $datasets;
    }
}
