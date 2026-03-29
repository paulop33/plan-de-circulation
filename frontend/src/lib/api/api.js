import { appConfig } from './config.js';

async function fetchApi(path, params = {}) {
	const url = new URL(path, appConfig.backendUrl || window.location.origin);
	for (const [key, value] of Object.entries(params)) {
		url.searchParams.append(key, value);
	}
	const response = await fetch(url, { credentials: 'include' });
	return await response.json();
}

export async function loadGeoJSON(bounds) {
	const padLng = (bounds.getEast() - bounds.getWest()) * appConfig.boundsPadding;
	const padLat = (bounds.getNorth() - bounds.getSouth()) * appConfig.boundsPadding;
	return fetchApi('/api/data', {
		min_lon: bounds.getWest() - padLng,
		min_lat: bounds.getSouth() - padLat,
		max_lon: bounds.getEast() + padLng,
		max_lat: bounds.getNorth() + padLat,
	});
}

export const loadTransitGeoJSON = () => fetchApi('/api/transit');
export const loadTrafficGeoJSON = () => fetchApi('/api/traffic');
export const loadPunctualTrafficGeoJSON = () => fetchApi('/api/punctual-traffic');

export function loadParlonsVeloGeoJSON(bounds) {
	return fetchApi('/api/parlons-velo', {
		min_lon: bounds.getWest(),
		min_lat: bounds.getSouth(),
		max_lon: bounds.getEast(),
		max_lat: bounds.getNorth(),
	});
}
