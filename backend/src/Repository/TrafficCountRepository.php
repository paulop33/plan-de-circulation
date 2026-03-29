<?php

namespace App\Repository;

use Doctrine\DBAL\Connection;

class TrafficCountRepository
{
    public function __construct(private Connection $connection) {}

    public function findLatestWithHistory(): array
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

        return $this->connection->fetchAllAssociative($sql);
    }
}
