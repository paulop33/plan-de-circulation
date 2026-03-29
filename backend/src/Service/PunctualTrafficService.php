<?php

namespace App\Service;

use App\Repository\PunctualTrafficCountRepository;

class PunctualTrafficService
{
    public function __construct(private PunctualTrafficCountRepository $repository) {}

    public function getFeatureCollection(): array
    {
        $rows = $this->repository->findLatest();

        if ($rows === null) {
            return ['type' => 'FeatureCollection', 'features' => []];
        }

        $features = [];
        foreach ($rows as $row) {
            $features[] = [
                'type' => 'Feature',
                'geometry' => json_decode($row['geojson'], true),
                'properties' => [
                    'gid' => $row['gid'],
                    'orientation' => $row['orientation'],
                    'sens_orientation' => $row['sens_orientation'],
                    'tmjo_tv' => (int) $row['tmjo_tv'],
                    'tmjo_vl' => $row['tmjo_vl'] !== null ? (int) $row['tmjo_vl'] : null,
                    'tmjo_pl' => $row['tmjo_pl'] !== null ? (int) $row['tmjo_pl'] : null,
                    'hpm_tv' => $row['hpm_tv'] !== null ? (int) $row['hpm_tv'] : null,
                    'hps_tv' => $row['hps_tv'] !== null ? (int) $row['hps_tv'] : null,
                    'v85_vl' => $row['v85_vl'] !== null ? (float) $row['v85_vl'] : null,
                    'v85_pl' => $row['v85_pl'] !== null ? (float) $row['v85_pl'] : null,
                    'annee' => $row['annee'] !== null ? (int) $row['annee'] : null,
                    'semaine' => $row['semaine'],
                ],
            ];
        }

        return [
            'type' => 'FeatureCollection',
            'features' => $features,
        ];
    }
}
