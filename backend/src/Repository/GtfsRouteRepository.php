<?php

namespace App\Repository;

use Doctrine\DBAL\Connection;

class GtfsRouteRepository
{
    public function __construct(private Connection $connection) {}

    public function findAll(): array
    {
        $sql = <<<SQL
            SELECT ST_AsGeoJSON(geom) AS geojson, route_short_name, route_type, route_color, origin, headsign
            FROM gtfs_routes
        SQL;

        return $this->connection->fetchAllAssociative($sql);
    }
}
