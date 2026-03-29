<?php

namespace App\Service;

use App\Repository\GtfsRouteRepository;

class TransitService
{
    public function __construct(private GtfsRouteRepository $repository) {}

    public function getFeatureCollection(): array
    {
        $rows = $this->repository->findAll();

        $features = [];
        foreach ($rows as $row) {
            $features[] = [
                'type' => 'Feature',
                'geometry' => json_decode($row['geojson'], true),
                'properties' => [
                    'route_short_name' => $row['route_short_name'],
                    'route_type' => (int) $row['route_type'],
                    'route_color' => '#' . ($row['route_color'] ?: '888888'),
                ],
            ];
        }

        return [
            'type' => 'FeatureCollection',
            'features' => $features,
        ];
    }
}
