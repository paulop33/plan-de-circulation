import maplibregl, { AttributionControl, NavigationControl } from 'maplibre-gl';
import { appConfig } from './config.js';
import { loadGeoJSON, loadTransitGeoJSON, loadTrafficGeoJSON } from './api.js';
import { getMap, setMap, getData, setData, getUserChanges, getUserSplits, getActiveTool, updateSource } from './state.js';
import { roadLayer, pedestrianLayer, arrowsLayer, tramLayer, busLayer, trafficLayer } from './layers.js';
import { toggleDirection, togglePedestrian, toggleModalFilter, handleSplit } from './interactions.js';
import { updateZoomOverlay, initToolbar, initResetButton, showConnectivityResult } from './ui.js';
import { checkConnectivity } from './graph.js';

function buildSparklineSVG(history) {
    if (!history || history.length < 2) return '';
    const w = 200, h = 80, pad = 20;
    const years = history.map(d => d.year);
    const vals = history.map(d => d.mjo_val);
    const minY = Math.min(...vals);
    const maxY = Math.max(...vals);
    const rangeY = maxY - minY || 1;
    const minX = Math.min(...years);
    const maxX = Math.max(...years);
    const rangeX = maxX - minX || 1;

    const points = history.map(d => {
        const x = pad + ((d.year - minX) / rangeX) * (w - 2 * pad);
        const y = h - pad - ((d.mjo_val - minY) / rangeY) * (h - 2 * pad);
        return { x, y, year: d.year, val: d.mjo_val };
    });

    const polyline = points.map(p => `${p.x},${p.y}`).join(' ');
    const dots = points.map(p =>
        `<circle cx="${p.x}" cy="${p.y}" r="3" fill="#e74c3c"/>` +
        `<text x="${p.x}" y="${p.y - 6}" text-anchor="middle" font-size="9" fill="#333">${p.val}</text>` +
        `<text x="${p.x}" y="${h - 2}" text-anchor="middle" font-size="8" fill="#666">${p.year}</text>`
    ).join('');

    return `<svg width="${w}" height="${h}" style="display:block;margin-top:6px">
        <polyline points="${polyline}" fill="none" stroke="#e74c3c" stroke-width="2"/>
        ${dots}
    </svg>`;
}

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

        // Traffic counts layer
        loadTrafficGeoJSON().then(trafficData => {
            map.addSource('traffic', {
                type: 'geojson',
                data: trafficData,
            });
            map.addLayer(trafficLayer);

            // Popup au clic
            map.on('click', 'traffic-layer', (e) => {
                const props = e.features[0].properties;
                const history = typeof props.history === 'string' ? JSON.parse(props.history) : props.history;
                const sparkline = buildSparklineSVG(history);
                const html = `<strong>${props.nom_voie || props.ident}</strong>
                    <br>TMJO : ${props.mjo_val} véh/j (${props.year || '?'})
                    ${props.sens_cir ? '<br>Direction : ' + props.sens_cir : ''}
                    ${sparkline}`;
                new maplibregl.Popup({ maxWidth: '260px' })
                    .setLngLat(e.lngLat)
                    .setHTML(html)
                    .addTo(map);
            });
            map.on('mouseenter', 'traffic-layer', () => { map.getCanvas().style.cursor = 'pointer'; });
            map.on('mouseleave', 'traffic-layer', () => { map.getCanvas().style.cursor = ''; });
        });

        document.getElementById('toggle-tram')?.addEventListener('change', (e) => {
            map.setLayoutProperty('tram-layer', 'visibility', e.target.checked ? 'visible' : 'none');
        });
        document.getElementById('toggle-bus')?.addEventListener('change', (e) => {
            map.setLayoutProperty('bus-layer', 'visibility', e.target.checked ? 'visible' : 'none');
        });
        document.getElementById('toggle-traffic')?.addEventListener('change', (e) => {
            map.setLayoutProperty('traffic-layer', 'visibility', e.target.checked ? 'visible' : 'none');
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
