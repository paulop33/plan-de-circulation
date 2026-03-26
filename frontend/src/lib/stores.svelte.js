export const appState = $state({
	map: null,
	data: { type: 'FeatureCollection', features: [] },
	userChanges: {},
	userSplits: {},
	activeTool: 'direction',
	routingActive: false,
	currentUser: null,
	currentMapId: null,
	currentMapMeta: null,
});

export const routingState = $state({
	start: null,
	end: null,
	startLngLat: null,
	endLngLat: null,
	mode: 'car',
});

export const uiState = $state({
	loginModalOpen: false,
	registerModalOpen: false,
	forgotModalOpen: false,
	resetModalOpen: false,
	resetToken: null,
	toast: null,
	mapsPanelOpen: false,
});

export function updateSource() {
	const source = appState.map?.getSource('roads');
	if (source) source.setData(appState.data);
}
