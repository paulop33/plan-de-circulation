import { apiFetch } from './helpers.js';

export async function listMaps() {
	return apiFetch('/api/maps');
}

export async function createMap(mapData) {
	return apiFetch('/api/maps', {
		method: 'POST',
		body: JSON.stringify(mapData),
	});
}

export async function getMap(id) {
	return apiFetch(`/api/maps/${id}`);
}

export async function updateMap(id, mapData) {
	return apiFetch(`/api/maps/${id}`, {
		method: 'PUT',
		body: JSON.stringify(mapData),
	});
}

export async function deleteMap(id) {
	return apiFetch(`/api/maps/${id}`, { method: 'DELETE' });
}

export async function duplicateMap(id) {
	return apiFetch(`/api/maps/${id}/duplicate`, { method: 'POST' });
}

export async function shareMap(id, shareData) {
	return apiFetch(`/api/maps/${id}/share`, {
		method: 'POST',
		body: JSON.stringify(shareData),
	});
}

export async function revokeShare(mapId, shareId) {
	return apiFetch(`/api/maps/${mapId}/share/${shareId}`, { method: 'DELETE' });
}

export async function listPublicMaps() {
	return apiFetch('/api/maps/public');
}

export async function getPublicMap(id) {
	return apiFetch(`/api/maps/public/${id}`);
}

export async function getSharedMap(token) {
	return apiFetch(`/api/maps/shared/${token}`);
}
