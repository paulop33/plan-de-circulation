# Plan de circulation

Ce projet permet de charger puis modifier le plan de circulation d'une ville. Les données proviennent d'OpenStreetMap (OSM).

## Quick start

```bash
cp .env.example .env
make up
make seed
```

Ouvrir http://localhost:8080 — la carte affiche les rues de Bordeaux centre.

## Commandes disponibles

| Commande | Description |
|----------|-------------|
| `make up` | Lance le projet (build + démarrage) |
| `make down` | Arrête les conteneurs |
| `make logs` | Affiche les logs en continu |
| `make seed` | Importe les données routières depuis Overpass |
| `make clean` | Reset complet (supprime les volumes de données) |

## Import des données

La commande `make seed` appelle l'API Overpass pour télécharger les données routières de Bordeaux centre, puis les importe dans PostGIS via `ogr2ogr`.

Vous pouvez relancer `make seed` à tout moment pour réimporter les données (la table est vidée puis remplie à nouveau).

## Structure du projet

- **backend/** — API Symfony 6.4 (PHP 8.2) qui sert les données GeoJSON depuis PostGIS
- **frontend/** — Interface carte en HTML/JS avec MapLibre GL, bundlée par Vite
- **compose.yml** — Orchestration Docker (PostGIS, backend, frontend)

## API

`GET http://localhost:8000/api/data?min_lon=-0.58&min_lat=44.82&max_lon=-0.56&max_lat=44.83`

Retourne un GeoJSON `FeatureCollection` des rues dans la bbox donnée.
