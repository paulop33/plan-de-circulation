<?php

namespace App\Service;

use App\Dto\BboxQuery;
use App\Repository\OsmDataRepository;

class OsmDataService
{
    public function __construct(private OsmDataRepository $repository) {}

    public function getFeatureCollection(BboxQuery $bbox): array
    {
        $rows = $this->repository->findByBbox($bbox);

        $features = [];
        foreach ($rows as $row) {
            $features[] = [
                'type' => 'Feature',
                'geometry' => json_decode($row['geojson'], true),
                'properties' => [
                    'name' => $row['name'],
                    'osm_id' => $row['ogc_fid'],
                    'oneway' => $row['oneway'] === 'yes',
                    'highway' => $row['highway'],
                    'bollard' => (bool) $row['bollard'],
                    'status' => $row['highway'] === 'pedestrian' ? 'pedestrian' : 'normal',
                ],
            ];
        }

        return [
            'type' => 'FeatureCollection',
            'features' => $features,
        ];
    }
}
