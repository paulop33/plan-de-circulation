<?php

namespace App\Repository;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\TableNotFoundException;

class PunctualTrafficCountRepository
{
    public function __construct(private Connection $connection) {}

    /**
     * @return array|null null si la table n'existe pas
     */
    public function findLatest(): ?array
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

            return $this->connection->fetchAllAssociative($sql);
        } catch (TableNotFoundException) {
            return null;
        }
    }
}
