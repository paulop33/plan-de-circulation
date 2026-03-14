import { ROAD_STATUS } from './config.js';
import { getData, getUserChanges, updateSource } from './state.js';

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
