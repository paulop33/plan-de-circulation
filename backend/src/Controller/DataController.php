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
            SELECT ST_AsGeoJSON(geom) AS geojson, name, ogc_fid, oneway, highway, bollard
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
                    'bollard' => (bool) $row['bollard'],
                    'status' => $row['highway'] === 'pedestrian' ? 'pedestrian' : 'normal',
                ],
            ];
        }

        return $this->json([
            'type' => 'FeatureCollection',
            'features' => $features,
        ]);
    }

    #[Route('/api/traffic', methods: ['GET'])]
    public function getTraffic(Connection $connection): JsonResponse
    {
        $sql = <<<SQL
            WITH latest AS (
                SELECT DISTINCT ON (ident, COALESCE(sens_cir, ''))
                    id, ident, nom_voie, sens_cir, mjo_val, hpm_val, hps_val, year, geom
                FROM traffic_counts
                ORDER BY ident, COALESCE(sens_cir, ''), year DESC
            ),
            history AS (
                SELECT ident, COALESCE(sens_cir, '') AS sens_cir_key,
                    json_agg(json_build_object('year', year, 'mjo_val', mjo_val) ORDER BY year) AS history
                FROM traffic_counts
                GROUP BY ident, COALESCE(sens_cir, '')
            )
            SELECT ST_AsGeoJSON(l.geom) AS geojson, l.ident, l.nom_voie, l.sens_cir,
                   l.mjo_val, l.hpm_val, l.hps_val, l.year, h.history::text
            FROM latest l
            JOIN history h ON l.ident = h.ident AND COALESCE(l.sens_cir, '') = h.sens_cir_key
        SQL;

        $rows = $connection->fetchAllAssociative($sql);

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

        return $this->json([
            'type' => 'FeatureCollection',
            'features' => $features,
        ]);
    }

    #[Route('/api/transit', methods: ['GET'])]
    public function getTransit(Connection $connection): JsonResponse
    {
        $sql = <<<SQL
            SELECT ST_AsGeoJSON(geom) AS geojson, route_short_name, route_type, route_color
            FROM gtfs_routes
        SQL;

        $rows = $connection->fetchAllAssociative($sql);

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

        return $this->json([
            'type' => 'FeatureCollection',
            'features' => $features,
        ]);
    }
}
