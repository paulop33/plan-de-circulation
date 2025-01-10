// utils.js
export let appConfig = {
    backendUrl: 'http://localhost:8000',
    maxZoomRefresh: 14,
    mapInitCenter: [-0.5670392, 44.82459],
    mapInitZoom: 14,
}


// Fonction pour charger des données GeoJSON à partir des limites données
export async function loadGeoJSON(bounds) {
    const url = new URL('/api/data', appConfig.backendUrl);

    url.searchParams.append('min_lon', bounds.getWest());
    url.searchParams.append('min_lat', bounds.getSouth());
    url.searchParams.append('max_lon', bounds.getEast());
    url.searchParams.append('max_lat', bounds.getNorth());

    const response = await fetch(url);
    return await response.json();
}

// Fonction pour rafraîchir les données de la carte
export async function refreshData(map, data, userChanges) {
    if (map.getZoom() < appConfig.maxZoomRefresh) {
        return;
    }
    data = await loadGeoJSON(map.getBounds());
    for (const [key, value] of Object.entries(userChanges)) {
        applyChangeOnFeature(data, value);
    }
    const source = map.getSource('roads');
    if (source) {
        source.setData(data);
    }
}

// Applique un changement à une entité spécifique dans les données
export function applyChangeOnFeature(data, feature) {
    data.features = data.features.map(elem => {
        if (elem.properties['@id'] === feature.properties['@id']) {
            elem.properties = {...feature.properties};
        }
        return elem;
    });
}

export function deleteFeature(data, feature) {
    data.features = data.features.filter(elem => {
        return elem.properties['@id'] !== feature.properties['@id'];
    });
}

export function addFeature(data, feature) {
    data.features.push(feature);
}

// Permet de basculer la direction d'une route
export function toggleDirection(feature, data, map, userChanges) {
    if (typeof feature.properties.oneway === 'undefined') {
        feature.properties.oneway = false;
    }
    if (typeof feature.properties.reverse === 'undefined') {
        feature.properties.reverse = false;
    }
    if (typeof userChanges[feature.properties['@id']] === 'undefined') {
        feature.properties.initialoneway = feature.properties.oneway;
    }

    feature.properties.override = true;

    if (feature.properties.oneway === false) {
        feature.properties.oneway = true;
        feature.properties.reverse = false;
    } else if (feature.properties.oneway === true && !feature.properties.reverse) {
        feature.properties.reverse = true;
    } else if (feature.properties.oneway === true && feature.properties.reverse) {
        feature.properties.oneway = false;
        feature.properties.reverse = false;
    }

    if (
        feature.properties.initialoneway === feature.properties.oneway &&
        feature.properties.reverse === false
    ) {
        feature.properties.override = false;
    }

    userChanges[feature.properties['@id']] = feature;
    applyChangeOnFeature(data, feature);

    // Met à jour la source avec les nouvelles données
    const source = map.getSource('roads');
    if (source) {
        source.setData(data);
    }
}

export function splitLine(lineCoordinates, splitIndex) {
    const firstSegment = lineCoordinates.slice(0, splitIndex + 1);
    const secondSegment = lineCoordinates.slice(splitIndex);

    return [firstSegment, secondSegment];
}
