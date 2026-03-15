import maplibregl, { AttributionControl, NavigationControl } from 'maplibre-gl';
import { appConfig } from './config.js';
import { loadGeoJSON, loadTransitGeoJSON } from './api.js';
import { getMap, setMap, getData, setData, getUserChanges, getUserSplits, getActiveTool, updateSource } from './state.js';
import { roadLayer, pedestrianLayer, arrowsLayer, tramLayer, busLayer } from './layers.js';
import { toggleDirection, togglePedestrian, toggleModalFilter, handleSplit } from './interactions.js';
import { updateZoomOverlay, initToolbar, initResetButton, showConnectivityResult } from './ui.js';
import { checkConnectivity } from './graph.js';

async function refreshData() {
    const map = getMap();
    if (map.getZoom() < appConfig.maxZoomRefresh) return;

    const data = await loadGeoJSON(map.getBounds());
    setData(data);

    const userSplits = getUserSplits();
    for (const [originalId, [featureA, featureB]] of Object.entries(userSplits)) {
        data.features = data.features.filter(f => f.properties.osm_id !== Number(originalId));
        data.features.push(featureA, featureB);
    }

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
    } else if (tool === 'split') {
        handleSplit(feature, e.lngLat);
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

        // Transit layers
        loadTransitGeoJSON().then(transitData => {
            map.addSource('transit', {
                type: 'geojson',
                data: transitData,
            });
            map.addLayer(tramLayer);
            map.addLayer(busLayer);
        });

        document.getElementById('toggle-tram')?.addEventListener('change', (e) => {
            map.setLayoutProperty('tram-layer', 'visibility', e.target.checked ? 'visible' : 'none');
        });
        document.getElementById('toggle-bus')?.addEventListener('change', (e) => {
            map.setLayoutProperty('bus-layer', 'visibility', e.target.checked ? 'visible' : 'none');
        });

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
