// map.js
import maplibregl, {AttributionControl, NavigationControl} from 'maplibre-gl';
import * as turf from '@turf/turf';
import {appConfig, loadGeoJSON, refreshData, toggleDirection} from './utils.js';

// Exemple d'une ligne
const line = turf.lineString([
    [0, 0],
    [5, 5],
    [10, 10],
]);
// Point de coupure
const point = turf.point([5, 5]);
// Divise la ligne
const splitLines = turf.lineSplit(line, point);
console.log(splitLines);

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

        // map.on('click', 'road-layer', (e) => {
        //     const feature = e.features[0];
        //     toggleDirection(feature, data, map, userChanges);
        // });

/*        map.on('click', (event) => {
            const clickedPoint = turf.point([event.lngLat.lng, event.lngLat.lat]);
            const lineFeature = lineData.features[0];
            const splitLines = splitLine(lineFeature, clickedPoint);

            // Mettre à jour la source avec les segments de ligne
            map.getSource('line-source').setData({
                type: 'FeatureCollection',
                features: splitLines.features,
            });
        });*/


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
                    properties: {},
                },
            ],
        };

        map.addSource('line-source', { type: 'geojson', data: lineData });

        map.addLayer({
            id: 'line-layer',
            type: 'line',
            source: 'line-source',
            paint: { 'line-color': '#ff0000', 'line-width': 4 },
        });

        let startPointSegment = null;
        map.on('click', (event) => {
            if (!startPointSegment) {
                startPointSegment = turf.point([event.lngLat.lng, event.lngLat.lat]);
                return;
            }
            const clickedLine = turf.lineString([
                    startPointSegment.geometry.coordinates,
                    [event.lngLat.lng, event.lngLat.lat]
                ]
            );

console.log(lineData);
            const splitLines = turf.lineSplit(lineData.features[0], clickedLine);
console.log(splitLines);

            map.getSource('line-source').setData({
                type: 'FeatureCollection',
                features: splitLines.features,
            });
        });
    });

    map.on('moveend', () => refreshData(map, data, userChanges));
    map.on('zoomend', () => refreshData(map, data, userChanges));

    return map;
}
