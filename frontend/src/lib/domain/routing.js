import { coordKey } from './graph.js';
import { ROAD_STATUS } from '$lib/api/config.js';

function haversineDistance(coord1, coord2) {
	const R = 6371000;
	const toRad = d => d * Math.PI / 180;
	const dLat = toRad(coord2[1] - coord1[1]);
	const dLon = toRad(coord2[0] - coord1[0]);
	const a = Math.sin(dLat / 2) ** 2 +
		Math.cos(toRad(coord1[1])) * Math.cos(toRad(coord2[1])) * Math.sin(dLon / 2) ** 2;
	return R * 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
}

export function buildRoutingGraph(data, mode) {
	const graph = new Map();

	function addEdge(from, to, weight) {
		if (!graph.has(from)) graph.set(from, new Map());
		const edges = graph.get(from);
		if (!edges.has(to) || edges.get(to) > weight) {
			edges.set(to, weight);
		}
	}

	for (const feature of data.features) {
		const status = feature.properties.status || ROAD_STATUS.NORMAL;
		const coords = feature.geometry.coordinates;
		if (!coords || coords.length < 2) continue;

		if (mode === 'car') {
			if (status === ROAD_STATUS.PEDESTRIAN || status === ROAD_STATUS.MODAL_FILTER || status === ROAD_STATUS.BOLLARD) continue;

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

export function computeAndDisplayRoute(map, data, rState) {
	if (!rState.start || !rState.end) return null;

	const graph = buildRoutingGraph(data, rState.mode);
	const result = dijkstra(graph, rState.start, rState.end);

	if (!result) {
		const routeSrc = map.getSource('route');
		if (routeSrc) routeSrc.setData({ type: 'FeatureCollection', features: [] });
		return { error: 'Aucun itinéraire trouvé. Toutes les rues intermédiaires sont-elles chargées ?' };
	}

	const geojson = buildRouteGeoJSON(result.path);
	const routeSrc = map.getSource('route');
	if (routeSrc) routeSrc.setData(geojson);

	return { distance: result.distance };
}

export function handleRoutingClick(lngLat, map, data, rState) {
	const graph = buildRoutingGraph(data, rState.mode);

	if (rState.start && rState.end) {
		clearRoute(map, rState);
		return { action: 'cleared' };
	}

	const nodeKey = snapToNearestNode(lngLat, graph);
	if (!nodeKey) {
		return { action: 'error', message: 'Aucun noeud trouvé à proximité.' };
	}

	if (!rState.start) {
		rState.start = nodeKey;
		rState.startLngLat = lngLat;
		updateMarkers(map, rState);
		return { action: 'start-set' };
	} else {
		rState.end = nodeKey;
		rState.endLngLat = lngLat;
		updateMarkers(map, rState);
		const result = computeAndDisplayRoute(map, data, rState);
		return { action: 'route-computed', ...result };
	}
}

export function clearRoute(map, rState) {
	rState.start = null;
	rState.end = null;
	rState.startLngLat = null;
	rState.endLngLat = null;
	if (map) {
		const routeSrc = map.getSource('route');
		if (routeSrc) routeSrc.setData({ type: 'FeatureCollection', features: [] });
		const markersSrc = map.getSource('route-markers');
		if (markersSrc) markersSrc.setData({ type: 'FeatureCollection', features: [] });
	}
}

export function updateMarkers(map, rState) {
	const features = [];
	if (rState.startLngLat) {
		features.push({
			type: 'Feature',
			geometry: { type: 'Point', coordinates: [rState.startLngLat.lng, rState.startLngLat.lat] },
			properties: { type: 'start' },
		});
	}
	if (rState.endLngLat) {
		features.push({
			type: 'Feature',
			geometry: { type: 'Point', coordinates: [rState.endLngLat.lng, rState.endLngLat.lat] },
			properties: { type: 'end' },
		});
	}
	const src = map.getSource('route-markers');
	if (src) src.setData({ type: 'FeatureCollection', features });
}
