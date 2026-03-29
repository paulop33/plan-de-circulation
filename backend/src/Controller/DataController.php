<?php

namespace App\Controller;

use App\Dto\BboxQuery;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\Routing\Attribute\Route;

class DataController extends AbstractController
{
    #[Route('/api/data', methods: ['GET'])]
    public function getData(#[MapQueryString] BboxQuery $bbox, Connection $connection): JsonResponse
    {
        $sql = <<<SQL
            SELECT ST_AsGeoJSON(geom) AS geojson, name, ogc_fid, oneway, highway, bollard
            FROM osm_data
            WHERE geom && ST_MakeEnvelope(:min_lon, :min_lat, :max_lon, :max_lat, 4326)
        SQL;

        $rows = $connection->fetchAllAssociative($sql, [
            'min_lon' => $bbox->min_lon,
            'min_lat' => $bbox->min_lat,
            'max_lon' => $bbox->max_lon,
            'max_lat' => $bbox->max_lat,
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

    #[Route('/api/punctual-traffic', methods: ['GET'])]
    public function getPunctualTraffic(Connection $connection): JsonResponse
    {
        try {
            $sql = <<<SQL
                SELECT DISTINCT ON (gid, sens_orientation)
                    ST_AsGeoJSON(geom) AS geojson, gid, sens_orientation, orientation,
                    tmjo_tv, tmjo_vl, tmjo_pl, hpm_tv, hps_tv,
                    v85_vl, v85_pl, annee, semaine
                FROM punctual_traffic_counts
                ORDER BY gid, sens_orientation, annee DESC
            SQL;

            $rows = $connection->fetchAllAssociative($sql);
        } catch (\Doctrine\DBAL\Exception\TableNotFoundException) {
            return $this->json(['type' => 'FeatureCollection', 'features' => []]);
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

        return $this->json([
            'type' => 'FeatureCollection',
            'features' => $features,
        ]);
    }

    #[Route('/api/parlons-velo', methods: ['GET'])]
    public function getParlonsVelo(#[MapQueryString] BboxQuery $bbox, Connection $connection): JsonResponse
    {
        $sql = <<<SQL
            SELECT ST_AsGeoJSON(geom) AS geojson, description
            FROM parlons_velo_points
            WHERE geom && ST_MakeEnvelope(:min_lon, :min_lat, :max_lon, :max_lat, 4326)
        SQL;

        $rows = $connection->fetchAllAssociative($sql, [
            'min_lon' => $bbox->min_lon,
            'min_lat' => $bbox->min_lat,
            'max_lon' => $bbox->max_lon,
            'max_lat' => $bbox->max_lat,
        ]);

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
