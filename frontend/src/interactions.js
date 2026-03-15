import { ROAD_STATUS } from './config.js';
import { getData, getUserChanges, getUserSplits, updateSource } from './state.js';

function applyChange(feature) {
    const data = getData();
    const userChanges = getUserChanges();

    userChanges[feature.properties['osm_id']] = feature;

    data.features = data.features.map(elem => {
        if (elem.properties['osm_id'] === feature.properties['osm_id']) {
            elem.properties = { ...feature.properties };
        }
        return elem;
    });

    updateSource();
}

function ensureDefaults(feature) {
    const props = feature.properties;
    if (typeof props.oneway === 'undefined') props.oneway = false;
    if (typeof props.reverse === 'undefined') props.reverse = false;
    if (typeof props.status === 'undefined') props.status = ROAD_STATUS.NORMAL;
}

function snapshotInitial(feature) {
    const userChanges = getUserChanges();
    if (typeof userChanges[feature.properties['osm_id']] === 'undefined') {
        feature.properties.initialoneway = feature.properties.oneway;
        feature.properties.initialstatus = feature.properties.status;
    }
}

function checkOverride(feature) {
    const props = feature.properties;
    const directionChanged = props.initialoneway !== props.oneway || props.reverse === true;
    const statusChanged = props.initialstatus !== props.status;
    props.override = directionChanged || statusChanged;
}

export function toggleDirection(feature) {
    ensureDefaults(feature);
    snapshotInitial(feature);

    if (feature.properties.status !== ROAD_STATUS.NORMAL) return;

    if (feature.properties.oneway === false) {
        feature.properties.oneway = true;
        feature.properties.reverse = false;
    } else if (feature.properties.oneway === true && !feature.properties.reverse) {
        feature.properties.reverse = true;
    } else if (feature.properties.oneway === true && feature.properties.reverse) {
        feature.properties.oneway = false;
        feature.properties.reverse = false;
    }

    checkOverride(feature);
    applyChange(feature);
}

export function togglePedestrian(feature) {
    ensureDefaults(feature);
    snapshotInitial(feature);

    if (feature.properties.status === ROAD_STATUS.PEDESTRIAN) {
        feature.properties.status = ROAD_STATUS.NORMAL;
    } else {
        feature.properties.status = ROAD_STATUS.PEDESTRIAN;
        feature.properties.oneway = false;
        feature.properties.reverse = false;
    }

    checkOverride(feature);
    applyChange(feature);
}

function projectOnSegment(point, segStart, segEnd) {
    const dx = segEnd[0] - segStart[0];
    const dy = segEnd[1] - segStart[1];
    const lenSq = dx * dx + dy * dy;

    if (lenSq === 0) return { point: segStart, t: 0 };

    let t = ((point[0] - segStart[0]) * dx + (point[1] - segStart[1]) * dy) / lenSq;
    t = Math.max(0, Math.min(1, t));

    return {
        point: [segStart[0] + t * dx, segStart[1] + t * dy],
        t,
    };
}

function distanceToSegment(point, segStart, segEnd) {
    const proj = projectOnSegment(point, segStart, segEnd);
    const dx = point[0] - proj.point[0];
    const dy = point[1] - proj.point[1];
    return { distance: Math.sqrt(dx * dx + dy * dy), projection: proj };
}

let splitIdCounter = 0;

export function handleSplit(feature, lngLat) {
    const osmId = feature.properties.osm_id;
    const coords = feature.geometry.coordinates;
    const clickPoint = [lngLat.lng, lngLat.lat];

    if (coords.length < 2) return;

    let bestDist = Infinity;
    let bestIdx = 0;
    let bestProj = null;

    for (let i = 0; i < coords.length - 1; i++) {
        const result = distanceToSegment(clickPoint, coords[i], coords[i + 1]);
        if (result.distance < bestDist) {
            bestDist = result.distance;
            bestIdx = i;
            bestProj = result.projection;
        }
    }

    if (!bestProj) return;

    const splitPoint = bestProj.point;

    if (bestProj.t <= 0.001 && bestIdx === 0) return;
    if (bestProj.t >= 0.999 && bestIdx === coords.length - 2) return;

    const coordsA = coords.slice(0, bestIdx + 1).concat([splitPoint]);
    const coordsB = [splitPoint].concat(coords.slice(bestIdx + 1));

    splitIdCounter++;
    const idA = -Date.now() - splitIdCounter;
    const idB = idA - 1;

    const featureA = {
        type: 'Feature',
        geometry: { type: 'LineString', coordinates: coordsA },
        properties: { ...feature.properties, osm_id: idA },
    };
    const featureB = {
        type: 'Feature',
        geometry: { type: 'LineString', coordinates: coordsB },
        properties: { ...feature.properties, osm_id: idB },
    };

    const userSplits = getUserSplits();
    userSplits[osmId] = [featureA, featureB];

    const data = getData();
    data.features = data.features.filter(f => f.properties.osm_id !== osmId);
    data.features.push(featureA, featureB);
    updateSource();
}

export function toggleModalFilter(feature) {
    ensureDefaults(feature);
    snapshotInitial(feature);

    if (feature.properties.status === ROAD_STATUS.MODAL_FILTER) {
        feature.properties.status = ROAD_STATUS.NORMAL;
    } else {
        feature.properties.status = ROAD_STATUS.MODAL_FILTER;
    }

    checkOverride(feature);
    applyChange(feature);
}

export function toggleBollard(feature) {
    ensureDefaults(feature);
    snapshotInitial(feature);

    if (feature.properties.status === ROAD_STATUS.BOLLARD) {
        feature.properties.status = ROAD_STATUS.NORMAL;
    } else {
        feature.properties.status = ROAD_STATUS.BOLLARD;
    }

    checkOverride(feature);
    applyChange(feature);
}
