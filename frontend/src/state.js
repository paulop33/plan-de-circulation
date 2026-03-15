let map = null;
let data = [];
let userChanges = {};
let userSplits = {};
let activeTool = 'direction';
let routingActive = false;

export function getMap() { return map; }
export function setMap(m) { map = m; }

export function getData() { return data; }
export function setData(d) { data = d; }

export function getUserChanges() { return userChanges; }
export function clearUserChanges() { userChanges = {}; userSplits = {}; }

export function getUserSplits() { return userSplits; }
export function clearUserSplits() { userSplits = {}; }

export function getActiveTool() { return activeTool; }
export function setActiveTool(tool) { activeTool = tool; }

export function isRoutingActive() { return routingActive; }
export function setRoutingActive(active) { routingActive = active; }

export function updateSource() {
    const source = map?.getSource('roads');
    if (source) {
        source.setData(data);
    }
}
