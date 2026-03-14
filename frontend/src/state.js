let map = null;
let data = [];
let userChanges = {};
let activeTool = 'direction';

export function getMap() { return map; }
export function setMap(m) { map = m; }

export function getData() { return data; }
export function setData(d) { data = d; }

export function getUserChanges() { return userChanges; }
export function clearUserChanges() { userChanges = {}; }

export function getActiveTool() { return activeTool; }
export function setActiveTool(tool) { activeTool = tool; }

export function updateSource() {
    const source = map?.getSource('roads');
    if (source) {
        source.setData(data);
    }
}
