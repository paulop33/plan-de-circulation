from fastapi import FastAPI, Query
from psycopg2 import connect
import json
from fastapi.middleware.cors import CORSMiddleware

app = FastAPI()

app.add_middleware(
    CORSMiddleware,
    allow_origins=["http://localhost:8080",
                   "https://plan-de-circulation.velo-cite.org"],
    allow_credentials=True,
    allow_methods=["*"],  # Autoriser toutes les méthodes (GET, POST, etc.)
    allow_headers=["*"],  # Autoriser tous les en-têtes
)

# Connexion PostgreSQL
DB_SETTINGS = {
    "dbname": "geodatabase",
    "user": "userdb",
    "password": "passdb",
    "host": "db",
    "port": "5432"
}

def query_postgis(min_lon, min_lat, max_lon, max_lat):
    conn = connect(**DB_SETTINGS)
    cursor = conn.cursor()

    query = """
    SELECT ST_AsGeoJSON(geom) AS geojson, name, id, oneway
    FROM osm_data
    WHERE geom && ST_MakeEnvelope(%s, %s, %s, %s, 4326);
    """
    cursor.execute(query, (min_lon, min_lat, max_lon, max_lat))
    results = cursor.fetchall()

    features = [
        {
            "type": "Feature",
            "geometry": json.loads(row[0]),
            "properties": {
                "name": row[1],
                "@id": row[2],
                "oneway": row[3] == "yes"
            }
        }
        for row in results
    ]
    conn.close()
    return {"type": "FeatureCollection", "features": features}

@app.get("/api/data")
async def get_data(
    min_lon: float = Query(...), min_lat: float = Query(...),
    max_lon: float = Query(...), max_lat: float = Query(...)
):
    return query_postgis(min_lon, min_lat, max_lon, max_lat)
