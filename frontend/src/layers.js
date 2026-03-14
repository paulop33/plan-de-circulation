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
