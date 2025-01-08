# Plan de circulation
Ce petit projet vous permet de charger puis modifier le plan de circulation de la ville de votre choix.

Les données proviennent d'OpenStreetMap (OSM).

## Charger les données :
Le projet ne dispose pas de données par défaut. Il faudra les récupérer.
https://overpass-turbo.eu/# permet par exemple d'exporter les données d'OSM (un exemple de requete se trouve dans le fichier ``export.txt``).

### Import des données
Le petit utilitaire ogr2ogr peut servir à importer dans la base de données Postgis.

```bash
docker cp export.geojson backend:/app
ogr2ogr -f "PostgreSQL" PG:"host=db port=5432 dbname=geodatabase user=userdb password=passdb" export.geojson -nln osm_data -lco GEOMETRY_NAME=geom
```

## Structure du projet
- backend en python pour charger les données d'OSM
- frontend en html et vanilla js pour le moment
- carte avec mapbox
