# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

Interactive web app for designing traffic circulation plans for Bordeaux Metropole. Users edit road properties (one-way, pedestrian, modal filters, bollards) on a map, compute shortest-path routes, and share scenarios. Data comes from OpenStreetMap via Overpass API.

Language: French (UI, comments, commit messages).

## Commands

```bash
# Docker-based workflow
make up          # Production build (docker compose --profile prod)
make dev         # Dev mode with hot-reload frontend
make down        # Stop containers
make logs        # Tail logs
make seed        # Import OSM road data via Overpass API
make clean       # Full reset (delete volumes)

# Frontend (inside frontend/ or via frontend-dev container)
npm run dev      # Vite dev server
npm run build    # Static production build
```

Backend runs on port 8000, frontend on 8080. The Vite dev server proxies `/api` to the backend.

## Architecture

**Frontend** (`frontend/src/`): SvelteKit (Svelte 5 with runes), static adapter, MapLibre GL for maps, Turf.js for geospatial ops, TailwindCSS.

**Backend** (`backend/src/`): Symfony 6.4 (PHP 8.2), Doctrine ORM, PostGIS database, API Platform 4.3 (pour les nouvelles API REST).

### Frontend structure

- `lib/stores.svelte.js` — Central reactive state (`appState`, `routingState`, `uiState`) using Svelte 5 runes
- `lib/api/` — API calls (`api.js` for data, `auth.js` for auth, `maps-api.js` for CRUD, `config.js` for constants)
- `lib/domain/` — Business logic: `layers.js` (MapLibre layer defs), `interactions.js` (road editing tools), `routing.js` (Dijkstra pathfinding), `graph.js` (connectivity)
- `lib/map/` — Map UI components (MapContainer, Toolbar, RoutingPanel, Legend)
- `lib/components/` — Auth modals, toast notifications
- `routes/` — SvelteKit pages: home (`+page.svelte`), map editor (`map/[id]`, `map/new`), shared view (`shared/[token]`)

### Backend structure

- `Controller/` — DataController (GeoJSON endpoints), MapController (CRUD + sharing), AuthController
- `Entity/` — User, Map (stores changes/splits as JSON), MapShare. Les nouvelles entités doivent utiliser l'attribut `#[ApiResource]` d'API Platform
- `Command/` — Import commands: ImportOverpass, ImportGtfs, ImportTraffic, ImportParlonsVelo
- API endpoints: `/api/data` (roads in bbox), `/api/transit`, `/api/traffic`, `/api/parlons-velo`, `/api/maps/*`, `/api/auth/*`
- Documentation Swagger UI disponible sur `/api/docs`

### Data flow

1. MapContainer loads road GeoJSON from `/api/data` within map bounds
2. On pan/zoom, data is refetched; user modifications (`appState.userChanges`) are reapplied
3. Editing tools modify feature properties (oneway, pedestrian, bollard, etc.) and store changes in `appState.userChanges`
4. Maps are saved to the backend with changes/splits as JSON diffs over the base OSM data

### Road statuses

`NORMAL`, `PEDESTRIAN`, `MODAL_FILTER`, `BOLLARD` — defined in `lib/api/config.js`. Roads also have `oneway`, `reverse`, and `override` boolean properties.
