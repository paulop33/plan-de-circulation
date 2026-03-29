<?php

namespace App\Repository;

use App\Dto\BboxQuery;
use Doctrine\DBAL\Connection;

class OsmDataRepository
{
    public function __construct(private Connection $connection) {}

    public function findByBbox(BboxQuery $bbox): array
    {
        $sql = <<<SQL
            SELECT ST_AsGeoJSON(geom) AS geojson, name, ogc_fid, oneway, highway, bollard
            FROM osm_data
            WHERE geom && ST_MakeEnvelope(:min_lon, :min_lat, :max_lon, :max_lat, 4326)
        SQL;

        return $this->connection->fetchAllAssociative($sql, [
            'min_lon' => $bbox->min_lon,
            'min_lat' => $bbox->min_lat,
            'max_lon' => $bbox->max_lon,
            'max_lat' => $bbox->max_lat,
        ]);
    }
}
