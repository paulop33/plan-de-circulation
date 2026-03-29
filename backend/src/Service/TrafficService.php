<?php

namespace App\Service;

use App\Repository\TrafficCountRepository;

class TrafficService
{
    public function __construct(private TrafficCountRepository $repository) {}

    public function getFeatureCollection(): array
    {
        $rows = $this->repository->findLatestWithHistory();

        $features = [];
        foreach ($rows as $row) {
            $features[] = [
                'type' => 'Feature',
                'geometry' => json_decode($row['geojson'], true),
                'properties' => [
                    'ident' => $row['ident'],
                    'nom_voie' => $row['nom_voie'],
                    'sens_cir' => $row['sens_cir'],
                    'mjo_val' => (int) $row['mjo_val'],
                    'hpm_val' => $row['hpm_val'] !== null ? (int) $row['hpm_val'] : null,
                    'hps_val' => $row['hps_val'] !== null ? (int) $row['hps_val'] : null,
                    'year' => $row['year'] !== null ? (int) $row['year'] : null,
                    'history' => $row['history'],
                ],
            ];
        }

        return [
            'type' => 'FeatureCollection',
            'features' => $features,
        ];
    }
}
