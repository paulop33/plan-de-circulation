import { appConfig } from './config.js';

export async function loadGeoJSON(bounds) {
    const url = new URL('/api/data', appConfig.backendUrl);

    url.searchParams.append('min_lon', bounds.getWest());
    url.searchParams.append('min_lat', bounds.getSouth());
    url.searchParams.append('max_lon', bounds.getEast());
    url.searchParams.append('max_lat', bounds.getNorth());

    const response = await fetch(url);
    return await response.json();
}

export async function loadTransitGeoJSON() {
    const url = new URL('/api/transit', appConfig.backendUrl);
    const response = await fetch(url);
    return await response.json();
}

export async function loadTrafficGeoJSON() {
    const url = new URL('/api/traffic', appConfig.backendUrl);
    const response = await fetch(url);
    return await response.json();
}

export async function loadParlonsVeloGeoJSON(bounds) {
    const url = new URL('/api/parlons-velo', appConfig.backendUrl);
    url.searchParams.append('min_lon', bounds.getWest());
    url.searchParams.append('min_lat', bounds.getSouth());
    url.searchParams.append('max_lon', bounds.getEast());
    url.searchParams.append('max_lat', bounds.getNorth());
    const response = await fetch(url);
    return await response.json();
}