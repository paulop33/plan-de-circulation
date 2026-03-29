<?php

namespace App\Service;

use App\Dto\BboxQuery;
use App\Repository\ParlonsVeloRepository;

class ParlonsVeloService
{
    public function __construct(private ParlonsVeloRepository $repository) {}

    public function getFeatureCollection(BboxQuery $bbox): array
    {
        $rows = $this->repository->findByBbox($bbox);

        $features = [];
        foreach ($rows as $row) {
            $features[] = [
                'type' => 'Feature',
                'geometry' => json_decode($row['geojson'], true),
                'properties' => [
                    'description' => $row['description'],
                ],
            ];
        }

        return [
            'type' => 'FeatureCollection',
            'features' => $features,
        ];
    }
}
