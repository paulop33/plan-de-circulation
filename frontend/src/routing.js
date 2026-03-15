import { getData, getMap } from './state.js';
import { coordKey } from './graph.js';

let routingState = {
    start: null,    // coordKey string
    end: null,      // coordKey string
    startLngLat: null,
    endLngLat: null,
    mode: 'car',    // 'car' or 'bike'
};

export function getRoutingState() { return routingState; }

export function setRoutingMode(mode) {
    routingState.mode = mode;
    const map = getMap();
    if (routingState.start && routingState.end) {
        computeAndDisplayRoute(map);
    }
}

export function clearRoute() {
    routingState.start = null;
    routingState.end = null;
    routingState.startLngLat = null;
    routingState.endLngLat = null;
    const map = getMap();
    if (map) {
        const routeSrc = map.getSource('route');
        if (routeSrc) routeSrc.setData({ type: 'FeatureCollection', features: [] });
        const markersSrc = map.getSource('route-markers');
        if (markersSrc) markersSrc.setData({ type: 'FeatureCollection', features: [] });
    }
    updateRoutingPanel(null);
}

function haversineDistance(coord1, coord2) {
    const R = 6371000;
    const toRad = d => d * Math.PI / 180;
    const dLat = toRad(coord2[1] - coord1[1]);
    const dLon = toRad(coord2[0] - coord1[0]);
    const a = Math.sin(dLat / 2) ** 2 +
        Math.cos(toRad(coord1[1])) * Math.cos(toRad(coord2[1])) * Math.sin(dLon / 2) ** 2;
    return R * 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
}

export function buildRoutingGraph(mode) {
    const data = getData();
    // nodeKey -> Map(neighborKey -> weight)
    const graph = new Map();

    function addEdge(from, to, weight) {
        if (!graph.has(from)) graph.set(from, new Map());
        const edges = graph.get(from);
        if (!edges.has(to) || edges.get(to) > weight) {
            edges.set(to, weight);
        }
    }

    for (const feature of data.features) {
        const status = feature.properties.status || 'normal';
        const coords = feature.geometry.coordinates;
        if (!coords || coords.length < 2) continue;

        if (mode === 'car') {
            if (status === 'pedestrian' || status === 'modalfilter' || status === 'bollard') continue;

            const oneway = feature.properties.oneway === true;
            const reverse = feature.properties.reverse === true;

            for (let i = 0; i < coords.length - 1; i++) {
                const from = coordKey(coords[i]);
                const to = coordKey(coords[i + 1]);
                const weight = haversineDistance(coords[i], coords[i + 1]);

                if (oneway) {
                    if (reverse) {
                        addEdge(to, from, weight);
                    } else {
                        addEdge(from, to, weight);
                    }
                } else {
                    addEdge(from, to, weight);
                    addEdge(to, from, weight);
                }
            }
        } else {
            // Bike: all roads, always bidirectional
            for (let i = 0; i < coords.length - 1; i++) {
                const from = coordKey(coords[i]);
                const to = coordKey(coords[i + 1]);
                const weight = haversineDistance(coords[i], coords[i + 1]);
                addEdge(from, to, weight);
                addEdge(to, from, weight);
            }
        }
    }

    return graph;
}

// Min-heap for Dijkstra
class MinHeap {
    constructor() { this.data = []; }
    push(item) {
        this.data.push(item);
        this._bubbleUp(this.data.length - 1);
    }
    pop() {
        const top = this.data[0];
        const last = this.data.pop();
        if (this.data.length > 0) {
            this.data[0] = last;
            this._sinkDown(0);
        }
        return top;
    }
    get size() { return this.data.length; }
    _bubbleUp(i) {
        while (i > 0) {
            const parent = (i - 1) >> 1;
            if (this.data[i].dist >= this.data[parent].dist) break;
            [this.data[i], this.data[parent]] = [this.data[parent], this.data[i]];
            i = parent;
        }
    }
    _sinkDown(i) {
        const n = this.data.length;
        while (true) {
            let smallest = i;
            const l = 2 * i + 1, r = 2 * i + 2;
            if (l < n && this.data[l].dist < this.data[smallest].dist) smallest = l;
            if (r < n && this.data[r].dist < this.data[smallest].dist) smallest = r;
            if (smallest === i) break;
            [this.data[i], this.data[smallest]] = [this.data[smallest], this.data[i]];
            i = smallest;
        }
    }
}

export function dijkstra(graph, startKey, endKey) {
    const dist = new Map();
    const prev = new Map();
    const heap = new MinHeap();

    dist.set(startKey, 0);
    heap.push({ node: startKey, dist: 0 });

    while (heap.size > 0) {
        const { node, dist: d } = heap.pop();
        if (d > (dist.get(node) ?? Infinity)) continue;
        if (node === endKey) break;

        const neighbors = graph.get(node);
        if (!neighbors) continue;

        for (const [neighbor, weight] of neighbors) {
            const newDist = d + weight;
            if (newDist < (dist.get(neighbor) ?? Infinity)) {
                dist.set(neighbor, newDist);
                prev.set(neighbor, node);
                heap.push({ node: neighbor, dist: newDist });
            }
        }
    }

    if (!dist.has(endKey)) return null;

    const path = [];
    let current = endKey;
    while (current !== undefined) {
        path.unshift(current);
        current = prev.get(current);
    }

    return { path, distance: dist.get(endKey) };
}

function buildRouteGeoJSON(path) {
    const coordinates = path.map(key => key.split(',').map(Number));

    return {
        type: 'FeatureCollection',
        features: coordinates.length > 1 ? [{
            type: 'Feature',
            geometry: { type: 'LineString', coordinates },
            properties: {},
        }] : [],
    };
}

function snapToNearestNode(lngLat, graph) {
    const clickCoord = [lngLat.lng, lngLat.lat];
    let bestKey = null;
    let bestDist = Infinity;

    for (const nodeKey of graph.keys()) {
        const [lng, lat] = nodeKey.split(',').map(Number);
        const d = haversineDistance(clickCoord, [lng, lat]);
        if (d < bestDist) {
            bestDist = d;
            bestKey = nodeKey;
        }
    }

    if (bestDist > 500) return null;
    return bestKey;
}

function updateRoutingPanel(distance) {
    const panel = document.getElementById('routing-panel');
    if (!panel) return;

    const distEl = panel.querySelector('#routing-distance');
    if (distEl) {
        if (distance !== null && distance !== undefined) {
            distEl.textContent = distance >= 1000
                ? `Distance : ${(distance / 1000).toFixed(1)} km`
                : `Distance : ${Math.round(distance)} m`;
            distEl.classList.remove('hidden');
        } else {
            distEl.textContent = '';
            distEl.classList.add('hidden');
        }
    }

    const errorEl = panel.querySelector('#routing-error');
    if (errorEl) errorEl.classList.add('hidden');
}

function showRoutingError(msg) {
    const panel = document.getElementById('routing-panel');
    if (!panel) return;
    const errorEl = panel.querySelector('#routing-error');
    if (errorEl) {
        errorEl.textContent = msg;
        errorEl.classList.remove('hidden');
    }
    const distEl = panel.querySelector('#routing-distance');
    if (distEl) distEl.classList.add('hidden');
}

export function computeAndDisplayRoute(map) {
    if (!routingState.start || !routingState.end) return;

    const graph = buildRoutingGraph(routingState.mode);
    const result = dijkstra(graph, routingState.start, routingState.end);

    if (!result) {
        showRoutingError('Aucun itinéraire trouvé. Toutes les rues intermédiaires sont-elles chargées ?');
        const routeSrc = map.getSource('route');
        if (routeSrc) routeSrc.setData({ type: 'FeatureCollection', features: [] });
        return;
    }

    const geojson = buildRouteGeoJSON(result.path);
    const routeSrc = map.getSource('route');
    if (routeSrc) routeSrc.setData(geojson);

    updateRoutingPanel(result.distance);
}

function updateMarkers(map) {
    const features = [];
    if (routingState.startLngLat) {
        features.push({
            type: 'Feature',
            geometry: { type: 'Point', coordinates: [routingState.startLngLat.lng, routingState.startLngLat.lat] },
            properties: { type: 'start' },
        });
    }
    if (routingState.endLngLat) {
        features.push({
            type: 'Feature',
            geometry: { type: 'Point', coordinates: [routingState.endLngLat.lng, routingState.endLngLat.lat] },
            properties: { type: 'end' },
        });
    }
    const src = map.getSource('route-markers');
    if (src) src.setData({ type: 'FeatureCollection', features });
}

export function handleRoutingClick(lngLat, map) {
    const graph = buildRoutingGraph(routingState.mode);

    if (routingState.start && routingState.end) {
        clearRoute();
        return;
    }

    const nodeKey = snapToNearestNode(lngLat, graph);
    if (!nodeKey) {
        showRoutingError('Aucun noeud trouvé à proximité.');
        return;
    }

    if (!routingState.start) {
        routingState.start = nodeKey;
        routingState.startLngLat = lngLat;
        updateMarkers(map);
        updateRoutingPanel(null);
    } else {
        routingState.end = nodeKey;
        routingState.endLngLat = lngLat;
        updateMarkers(map);
        computeAndDisplayRoute(map);
    }
}
