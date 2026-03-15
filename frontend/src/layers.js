export const roadLayer = {
    id: 'road-layer',
    type: 'line',
    source: 'roads',
    filter: ['!=', ['get', 'status'], 'pedestrian'],
    paint: {
        'line-color': [
            'case',
            ['all', ['==', ['get', 'status'], 'modalfilter'], ['==', ['get', 'override'], true]],
            '#f59e0b',
            ['==', ['get', 'override'], true],
            '#000000',
            ['==', ['get', 'bollard'], true],
            '#a855f7',
            '#8e8c8c',
        ],
        'line-width': 3,
        'line-opacity': 1,
    },
    layout: {
        'line-cap': 'round',
        'line-join': 'round',
    },
};

export const pedestrianLayer = {
    id: 'pedestrian-layer',
    type: 'line',
    source: 'roads',
    filter: ['==', ['get', 'status'], 'pedestrian'],
    paint: {
        'line-color': '#22c55e',
        'line-width': 3,
        'line-opacity': 1,
        'line-dasharray': [2, 4],
    },
    layout: {
        'line-cap': 'round',
        'line-join': 'round',
    },
};

export const tramLayer = {
    id: 'tram-layer',
    type: 'line',
    source: 'transit',
    filter: ['==', ['get', 'route_type'], 0],
    paint: {
        'line-color': ['get', 'route_color'],
        'line-width': 3,
        'line-opacity': 0.8,
    },
    layout: {
        'line-cap': 'round',
        'line-join': 'round',
    },
};

export const busLayer = {
    id: 'bus-layer',
    type: 'line',
    source: 'transit',
    filter: ['==', ['get', 'route_type'], 3],
    paint: {
        'line-color': ['get', 'route_color'],
        'line-width': 2,
        'line-opacity': 0.8,
        'line-dasharray': [2, 2],
    },
    layout: {
        'line-cap': 'round',
        'line-join': 'round',
    },
};

export const trafficLayer = {
    id: 'traffic-layer',
    type: 'circle',
    source: 'traffic',
    paint: {
        'circle-radius': [
            'interpolate', ['linear'], ['get', 'mjo_val'],
            0, 4,
            5000, 10,
            15000, 20,
            30000, 35,
        ],
        'circle-color': [
            'interpolate', ['linear'], ['get', 'mjo_val'],
            0, '#22c55e',
            5000, '#eab308',
            15000, '#f97316',
            30000, '#ef4444',
        ],
        'circle-opacity': 0.7,
        'circle-stroke-width': 1,
        'circle-stroke-color': '#ffffff',
    },
};

export const arrowsLayer = {
    id: 'arrows',
    type: 'symbol',
    source: 'roads',
    filter: ['!=', ['get', 'status'], 'pedestrian'],
    layout: {
        'symbol-placement': 'line',
        'text-field': [
            'case',
            ['!', ['get', 'oneway']],
            '',
            ['==', ['get', 'reverse'], true],
            '\u25C0',
            '\u25B6',
        ],
        'text-keep-upright': false,
        'text-size': 16,
        'symbol-spacing': 50,
    },
    paint: {
        'text-color': '#8e8c8c',
        'text-opacity': 1,
    },
};
