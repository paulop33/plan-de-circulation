import { appState, uiState, updateSource } from '$lib/stores.svelte.js';
import { createMap, updateMap } from '$lib/api/maps-api.js';
import { loadParlonsVeloGeoJSON } from '$lib/api/api.js';

export function applyMapData(mapData) {
	const map = appState.map;
	if (!map) return;

	appState.currentMapId = mapData.id;
	appState.currentMapMeta = {
		name: mapData.name,
		description: mapData.description,
		status: mapData.status,
	};
	appState.userChanges = mapData.changes || {};
	appState.userSplits = mapData.splits || {};

	map.flyTo({
		center: [mapData.centerLng, mapData.centerLat],
		zoom: mapData.zoom,
	});
}

export async function saveCurrentMap() {
	if (!appState.currentUser || !appState.map) return;
	const map = appState.map;
	const center = map.getCenter();
	const mapData = {
		centerLng: center.lng,
		centerLat: center.lat,
		zoom: map.getZoom(),
		changes: appState.userChanges,
		splits: appState.userSplits,
	};

	if (appState.currentMapId) {
		const meta = appState.currentMapMeta;
		if (meta) {
			mapData.name = meta.name;
			mapData.description = meta.description;
			mapData.status = meta.status;
		}
		const { ok } = await updateMap(appState.currentMapId, mapData);
		if (ok) uiState.toast = 'Carte sauvegardee';
	} else {
		const name = prompt('Nom de la carte :', 'Ma carte');
		if (!name) return;
		mapData.name = name;
		const { ok, data } = await createMap(mapData);
		if (ok) {
			appState.currentMapId = data.id;
			appState.currentMapMeta = { name: data.name, description: data.description, status: data.status };
			uiState.toast = 'Carte sauvegardee';
		}
	}
}

export async function refreshParlonsVelo() {
	const map = appState.map;
	if (!map) return;
	if (map.getLayoutProperty('parlons-velo-layer', 'visibility') === 'none') return;
	const data = await loadParlonsVeloGeoJSON(map.getBounds());
	const source = map.getSource('parlons-velo');
	if (source) source.setData(data);
}
