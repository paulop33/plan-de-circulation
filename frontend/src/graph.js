import { getData } from './state.js';

export function coordKey(coord) {
    return `${coord[0].toFixed(6)},${coord[1].toFixed(6)}`;
}

export function checkConnectivity() {
    const data = getData();
    const adjacency = new Map();

    function addEdge(a, b) {
        if (!adjacency.has(a)) adjacency.set(a, new Set());
        if (!adjacency.has(b)) adjacency.set(b, new Set());
        adjacency.get(a).add(b);
        adjacency.get(b).add(a);
    }

    for (const feature of data.features) {
        const status = feature.properties.status || 'normal';
        if (status === 'pedestrian' || status === 'modalfilter') continue;

        const coords = feature.geometry.coordinates;
        if (!coords || coords.length < 2) continue;

        const start = coordKey(coords[0]);
        const end = coordKey(coords[coords.length - 1]);
        addEdge(start, end);
    }

    if (adjacency.size === 0) {
        return { connected: true, componentCount: 1 };
    }

    const visited = new Set();
    let componentCount = 0;

    for (const node of adjacency.keys()) {
        if (visited.has(node)) continue;
        componentCount++;

        const stack = [node];
        while (stack.length > 0) {
            const current = stack.pop();
            if (visited.has(current)) continue;
            visited.add(current);
            for (const neighbor of adjacency.get(current)) {
                if (!visited.has(neighbor)) {
                    stack.push(neighbor);
                }
            }
        }
    }

    return {
        connected: componentCount === 1,
        componentCount,
    };
}
