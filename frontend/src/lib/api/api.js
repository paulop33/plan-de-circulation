import { appConfig } from './config.js';

export async function loadGeoJSON(bounds) {
	const url = new URL('/api/data', appConfig.backendUrl || window.location.origin);

	const padLng = (bounds.getEast() - bounds.getWest()) * appConfig.boundsPadding;
	const padLat = (bounds.getNorth() - bounds.getSouth()) * appConfig.boundsPadding;

	url.searchParams.append('min_lon', bounds.getWest() - padLng);
	url.searchParams.append('min_lat', bounds.getSouth() - padLat);
	url.searchParams.append('max_lon', bounds.getEast() + padLng);
	url.searchParams.append('max_lat', bounds.getNorth() + padLat);

	const response = await fetch(url, { credentials: 'include' });
	return await response.json();
}

export async function loadTransitGeoJSON() {
	const url = new URL('/api/transit', appConfig.backendUrl || window.location.origin);
	const response = await fetch(url, { credentials: 'include' });
	return await response.json();
}

export async function loadTrafficGeoJSON() {
	const url = new URL('/api/traffic', appConfig.backendUrl || window.location.origin);
	const response = await fetch(url, { credentials: 'include' });
	return await response.json();
}

export async function loadParlonsVeloGeoJSON(bounds) {
	const url = new URL('/api/parlons-velo', appConfig.backendUrl || window.location.origin);
	url.searchParams.append('min_lon', bounds.getWest());
	url.searchParams.append('min_lat', bounds.getSouth());
	url.searchParams.append('max_lon', bounds.getEast());
	url.searchParams.append('max_lat', bounds.getNorth());
	const response = await fetch(url, { credentials: 'include' });
	return await response.json();
}
