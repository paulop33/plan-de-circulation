// map.js
import maplibregl, {AttributionControl, NavigationControl} from 'maplibre-gl';
import * as turf from '@turf/turf';
import {addFeature, appConfig, deleteFeature, loadGeoJSON, refreshData, toggleDirection} from './utils.js';

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

        const lineData = {
            type: 'FeatureCollection',
            features: [
                {
                    type: 'Feature',
                    geometry: {
                        type: 'LineString',
                        coordinates: [
                            [-10, 0],
                            [0, 10],
                            [10, 0],
                        ],
                    },
                    properties: { id: 1, name: 'Line 1' },
                },
                {
                    type: 'Feature',
                    geometry: {
                        type: 'LineString',
                        coordinates: [
                            [-5, -5],
                            [5, 5],
                        ],
                    },
                    properties: { id: 2, name: 'Line 2' },
                },
            ],
        };

        // map.addSource('line-source', { type: 'geojson', data: lineData });
        //
        // map.addLayer({
        //     id: 'line-layer',
        //     type: 'line',
        //     source: 'line-source',
        //     paint: { 'line-color': '#ff0000', 'line-width': 4 },
        // });

        map.on('contextmenu', 'road-layer', (event) => {
            const clickedLine = turf.point([event.lngLat.lng, event.lngLat.lat]);
            const features = map.queryRenderedFeatures(event.point, {
                layers: ['road-layer'], // Spécifiez la couche cible
            });

            if (features.length) {
                const clickedFeature = features[0]; // Première feature cliquée
                const splitLines = turf.lineSplit(clickedFeature, clickedLine);
                deleteFeature(data, clickedFeature);
                splitLines.features.forEach(feature => addFeature(data, feature));
                map.getSource('roads').setData(data);
            }
        });
    });

    map.on('moveend', () => refreshData(map, data, userChanges));
    map.on('zoomend', () => refreshData(map, data, userChanges));

    return map;
}
