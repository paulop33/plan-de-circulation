// map.js
import maplibregl, {AttributionControl, NavigationControl} from 'maplibre-gl';
import {appConfig, loadGeoJSON, refreshData, toggleDirection} from './utils.js';

let map;
let data = [];
let userChanges = {};

export function initializeMap(containerId, styleUrl) {
    map = new maplibregl.Map({
        container: containerId, // ID de l'élément HTML où la carte sera rendue
        style: styleUrl,        // Style MapLibre (modifiez si besoin)
        center: appConfig.mapInitCenter,         // Coordonnées (longitude, latitude)
        zoom: appConfig.mapInitZoom,              // Niveau de zoom initial
        attributionControl: false,
    });
    map.addControl(new NavigationControl({ showCompass: false }), 'top-left');
    map.addControl(new AttributionControl({ compact: false }), 'bottom-left');


    map.on('load', async () => {
        data = await loadGeoJSON(map.getBounds());

        map.addSource('roads', {
            type: 'geojson',
            data: data,
            generateId: true,
        });

        map.addLayer({
            id: 'road-layer',
            type: 'line',
            source: 'roads',
            paint: {
                'line-color': [
                    'case',
                    ['==', ['get', 'override'], true],
                    '#000000',
                    '#8e8c8c',
                ],
                'line-width': 3,
                'line-opacity': 1,
            },
            layout: {
                'line-cap': 'round',
                'line-join': 'round',
            }
        });

        map.addLayer({
            id: 'arrows',
            type: 'symbol',
            source: 'roads',
            layout: {
                'symbol-placement': 'line',
                'text-field': [
                    'case',
                    ['==', ['get', 'reverse'], true],
                    '◀',
                    '▶'
                ],
                'text-keep-upright': false,
                'text-size': 16,
                'symbol-spacing': 50,
            },
            paint: {
                'text-color': '#8e8c8c',
                'text-opacity': 1,
            },
        });

        map.on('click', 'road-layer', (e) => {
            const feature = e.features[0];
            toggleDirection(feature, data, map, userChanges);
        });
    });

    map.on('moveend', () => refreshData(map, data, userChanges));
    map.on('zoomend', () => refreshData(map, data, userChanges));

    return map;
}
