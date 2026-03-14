import maplibregl, { AttributionControl, NavigationControl } from 'maplibre-gl';
import { appConfig } from './config.js';
import { loadGeoJSON } from './api.js';
import { getMap, setMap, getData, setData, getUserChanges, getActiveTool, updateSource } from './state.js';
import { roadLayer, pedestrianLayer, arrowsLayer } from './layers.js';
import { toggleDirection, togglePedestrian, toggleModalFilter } from './interactions.js';
import { updateZoomOverlay, initToolbar, initResetButton, showConnectivityResult } from './ui.js';
import { checkConnectivity } from './graph.js';

async function refreshData() {
    const map = getMap();
    if (map.getZoom() < appConfig.maxZoomRefresh) return;

    const data = await loadGeoJSON(map.getBounds());
    setData(data);

    const userChanges = getUserChanges();
    for (const [key, value] of Object.entries(userChanges)) {
        data.features = data.features.map(elem => {
            if (elem.properties['osm_id'] === value.properties['osm_id']) {
                elem.properties = { ...value.properties };
            }
            return elem;
        });
    }

    updateSource();
}

function handleClick(e) {
    const tool = getActiveTool();
    const feature = e.features[0];
    if (tool === 'direction') {
        toggleDirection(feature);
    } else if (tool === 'pedestrian') {
        togglePedestrian(feature);
    } else if (tool === 'filter') {
        toggleModalFilter(feature);
    }
}

export function initializeMap(containerId, styleUrl) {
    const map = new maplibregl.Map({
        container: containerId,
        style: styleUrl,
        center: appConfig.mapInitCenter,
        zoom: appConfig.mapInitZoom,
        attributionControl: false,
    });
    setMap(map);

    map.addControl(new NavigationControl({ showCompass: false }), 'top-left');
    map.addControl(new AttributionControl({ compact: false }), 'bottom-left');

    map.on('load', async () => {
        const data = await loadGeoJSON(map.getBounds());
        setData(data);

        map.addSource('roads', {
            type: 'geojson',
            data: data,
            generateId: true,
        });

        map.addLayer(roadLayer);
        map.addLayer(pedestrianLayer);
        map.addLayer(arrowsLayer);

        map.on('click', 'road-layer', handleClick);
        map.on('click', 'pedestrian-layer', handleClick);

        initToolbar();
        initResetButton();

        const btnCheck = document.getElementById('btn-check');
        if (btnCheck) {
            btnCheck.addEventListener('click', () => {
                const result = checkConnectivity();
                showConnectivityResult(result);
            });
        }

        updateZoomOverlay();
    });

    map.on('moveend', () => { refreshData(); updateZoomOverlay(); });
    map.on('zoomend', () => { refreshData(); updateZoomOverlay(); });

    return map;
}
