export const ROAD_STATUS = {
	NORMAL: 'normal',
	PEDESTRIAN: 'pedestrian',
	MODAL_FILTER: 'modalfilter',
	BOLLARD: 'bollard',
};

export const appConfig = {
	backendUrl: import.meta.env.VITE_BACKEND_URL || '',
	maxZoomRefresh: 13,
	mapInitCenter: [-0.5670392, 44.82459],
	mapInitZoom: 12,
	boundsPadding: 0.5,
};
