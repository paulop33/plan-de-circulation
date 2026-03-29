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
    name: 'app:import-punctual-traffic',
    description: 'Importe les capteurs de trafic routier ponctuel depuis l\'open data Bordeaux Métropole',
)]
class ImportPunctualTrafficCommand extends Command
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

        $url = $_ENV['PUNCTUAL_TRAFFIC_URL'] ?? getenv('PUNCTUAL_TRAFFIC_URL')
            ?: 'https://opendata.bordeaux-metropole.fr/api/explore/v2.1/catalog/datasets/pc_capte_ponct_p/exports/geojson?limit=-1';

        $io->section('Téléchargement des capteurs ponctuels...');
        $response = $this->httpClient->request('GET', $url, ['timeout' => 120]);
        $data = json_decode($response->getContent(), true);

        if (!$data || empty($data['features'])) {
            $io->error('Aucune donnée récupérée.');
            return Command::FAILURE;
        }

        $io->info(sprintf('%d features récupérées.', count($data['features'])));

        $io->section('Création de la table punctual_traffic_counts...');
        $this->connection->executeStatement('DROP TABLE IF EXISTS punctual_traffic_counts');
        $this->connection->executeStatement(<<<SQL
            CREATE TABLE punctual_traffic_counts (
                id SERIAL PRIMARY KEY,
                gid VARCHAR(50),
                sens_orientation VARCHAR(5),
                orientation VARCHAR(20),
                tmjo_tv INTEGER,
                tmjo_vl INTEGER,
                tmjo_pl INTEGER,
                hpm_tv INTEGER,
                hps_tv INTEGER,
                v85_vl NUMERIC(5,1),
                v85_pl NUMERIC(5,1),
                annee INTEGER,
                semaine VARCHAR(100),
                insee VARCHAR(10),
                geom geometry(Point, 4326)
            )
        SQL);

        $inserted = 0;
        $skipped = 0;

        $this->connection->beginTransaction();
        foreach ($data['features'] as $feature) {
            $geometry = $feature['geometry'] ?? null;
            if (!$geometry || $geometry['type'] !== 'Point' || empty($geometry['coordinates'])) {
                $skipped++;
                continue;
            }

            $props = $feature['properties'];
            $tmjoTv = $props['tmjo_tv'] ?? null;
            if ($tmjoTv === null) {
                $skipped++;
                continue;
            }

            [$lon, $lat] = $geometry['coordinates'];

            $this->connection->executeStatement(
                "INSERT INTO punctual_traffic_counts (gid, sens_orientation, orientation, tmjo_tv, tmjo_vl, tmjo_pl, hpm_tv, hps_tv, v85_vl, v85_pl, annee, semaine, insee, geom) VALUES (:gid, :sens_orientation, :orientation, :tmjo_tv, :tmjo_vl, :tmjo_pl, :hpm_tv, :hps_tv, :v85_vl, :v85_pl, :annee, :semaine, :insee, ST_SetSRID(ST_MakePoint(:lon, :lat), 4326))",
                [
                    'gid' => (string) ($props['gid'] ?? ''),
                    'sens_orientation' => $props['sens_orientation'] ?? null,
                    'orientation' => $props['orientation'] ?? null,
                    'tmjo_tv' => (int) $tmjoTv,
                    'tmjo_vl' => isset($props['tmjo_vl']) ? (int) $props['tmjo_vl'] : null,
                    'tmjo_pl' => isset($props['tmjo_pl']) ? (int) $props['tmjo_pl'] : null,
                    'hpm_tv' => isset($props['hpm_tv']) ? (int) $props['hpm_tv'] : null,
                    'hps_tv' => isset($props['hps_tv']) ? (int) $props['hps_tv'] : null,
                    'v85_vl' => $props['v85_vl'] ?? null,
                    'v85_pl' => $props['v85_pl'] ?? null,
                    'annee' => isset($props['annee']) ? (int) $props['annee'] : null,
                    'semaine' => $props['semaine'] ?? null,
                    'insee' => $props['insee'] ?? null,
                    'lon' => $lon,
                    'lat' => $lat,
                ]
            );
            $inserted++;
        }
        $this->connection->commit();

        $this->connection->executeStatement('CREATE INDEX idx_punctual_traffic_geom ON punctual_traffic_counts USING GIST (geom)');
        $this->connection->executeStatement('CREATE INDEX idx_punctual_traffic_latest ON punctual_traffic_counts (gid, sens_orientation, annee DESC)');

        $io->success(sprintf('Import terminé : %d capteurs insérés (%d ignorés).', $inserted, $skipped));

        return Command::SUCCESS;
    }
}
