<?php

namespace App\Controller;

use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

class DataController extends AbstractController
{
    #[Route('/api/data', methods: ['GET'])]
    public function getData(Request $request, Connection $connection): JsonResponse
    {
        $minLon = (float) $request->query->get('min_lon');
        $minLat = (float) $request->query->get('min_lat');
        $maxLon = (float) $request->query->get('max_lon');
        $maxLat = (float) $request->query->get('max_lat');

        $sql = <<<SQL
            SELECT ST_AsGeoJSON(geom) AS geojson, name, ogc_fid, oneway, highway
            FROM osm_data
            WHERE geom && ST_MakeEnvelope(:min_lon, :min_lat, :max_lon, :max_lat, 4326)
        SQL;

        $rows = $connection->fetchAllAssociative($sql, [
            'min_lon' => $minLon,
            'min_lat' => $minLat,
            'max_lon' => $maxLon,
            'max_lat' => $maxLat,
        ]);

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
                    'status' => 'normal',
                ],
            ];
        }

        return $this->json([
            'type' => 'FeatureCollection',
            'features' => $features,
        ]);
    }
}
