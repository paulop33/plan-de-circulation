<script>
	import { onMount } from 'svelte';
	import maplibregl, { AttributionControl, NavigationControl } from 'maplibre-gl';
	import { appConfig } from '$lib/api/config.js';
	import { loadGeoJSON, loadTransitGeoJSON, loadTrafficGeoJSON, loadPunctualTrafficGeoJSON } from '$lib/api/api.js';
	import { appState, routingState, updateSource } from '$lib/stores.svelte.js';
	import { roadLayer, pedestrianLayer, arrowsLayer, tramLayer, busLayer, trafficLayer, punctualTrafficLayer, parlonsVeloLayer, routeLayer, routeMarkersLayer } from '$lib/domain/layers.js';
	import { toggleDirection, togglePedestrian, toggleModalFilter, toggleBollard, handleSplit } from '$lib/domain/interactions.js';
	import { handleRoutingClick, computeAndDisplayRoute } from '$lib/domain/routing.js';
	import { refreshParlonsVelo } from '$lib/domain/map-actions.js';

	let mapContainer;

	function addPopupLayer(map, layerId, htmlBuilder) {
		map.on('click', layerId, (e) => {
			const html = htmlBuilder(e.features[0].properties);
			new maplibregl.Popup({ maxWidth: '260px' })
				.setLngLat(e.lngLat)
				.setHTML(html)
				.addTo(map);
		});
		map.on('mouseenter', layerId, () => { map.getCanvas().style.cursor = 'pointer'; });
		map.on('mouseleave', layerId, () => { map.getCanvas().style.cursor = ''; });
	}

	function buildSparklineSVG(history) {
		if (!history || history.length < 2) return '';
		const w = 200, h = 80, pad = 20;
		const years = history.map(d => d.year);
		const vals = history.map(d => d.mjo_val);
		const minY = Math.min(...vals);
		const maxY = Math.max(...vals);
		const rangeY = maxY - minY || 1;
		const minX = Math.min(...years);
		const maxX = Math.max(...years);
		const rangeX = maxX - minX || 1;

		const points = history.map(d => {
			const x = pad + ((d.year - minX) / rangeX) * (w - 2 * pad);
			const y = h - pad - ((d.mjo_val - minY) / rangeY) * (h - 2 * pad);
			return { x, y, year: d.year, val: d.mjo_val };
		});

		const polyline = points.map(p => `${p.x},${p.y}`).join(' ');
		const dots = points.map(p =>
			`<circle cx="${p.x}" cy="${p.y}" r="3" fill="#e74c3c"/>` +
			`<text x="${p.x}" y="${p.y - 6}" text-anchor="middle" font-size="9" fill="#333">${p.val}</text>` +
			`<text x="${p.x}" y="${h - 2}" text-anchor="middle" font-size="8" fill="#666">${p.year}</text>`
		).join('');

		return `<svg width="${w}" height="${h}" style="display:block;margin-top:6px">
			<polyline points="${polyline}" fill="none" stroke="#e74c3c" stroke-width="2"/>
			${dots}
		</svg>`;
	}

	function recomputeRoute() {
		if (routingState.start && routingState.end && appState.map) {
			computeAndDisplayRoute(appState.map, appState.data, routingState);
		}
	}

	async function refreshData() {
		const map = appState.map;
		if (!map || map.getZoom() < appConfig.maxZoomRefresh) return;

		const data = await loadGeoJSON(map.getBounds());
		appState.data = data;

		const splitIds = new Set(Object.keys(appState.userSplits).map(Number));
		const changeMap = new Map();
		for (const value of Object.values(appState.userChanges)) {
			changeMap.set(value.properties['osm_id'], value);
		}

		// Single-pass: filter splits and apply changes
		data.features = data.features.filter(f => !splitIds.has(f.properties.osm_id));
		for (const [, [featureA, featureB]] of Object.entries(appState.userSplits)) {
			data.features.push(featureA, featureB);
		}
		data.features = data.features.map(elem => {
			const changed = changeMap.get(elem.properties['osm_id']);
			if (changed) elem.properties = { ...changed.properties };
			return elem;
		});

		updateSource();
		recomputeRoute();
	}

	function handleClick(e) {
		if (appState.routingActive) return;
		const feature = e.features[0];
		const tool = appState.activeTool;
		if (tool === 'direction') {
			toggleDirection(feature, appState, updateSource, recomputeRoute);
		} else if (tool === 'pedestrian') {
			togglePedestrian(feature, appState, updateSource, recomputeRoute);
		} else if (tool === 'filter') {
			toggleModalFilter(feature, appState, updateSource, recomputeRoute);
		} else if (tool === 'bollard') {
			toggleBollard(feature, appState, updateSource, recomputeRoute);
		} else if (tool === 'split') {
			handleSplit(feature, e.lngLat, appState, updateSource);
		}
	}

	onMount(() => {
		const map = new maplibregl.Map({
			container: mapContainer,
			style: '/assets/style.json',
			center: appConfig.mapInitCenter,
			zoom: appConfig.mapInitZoom,
			attributionControl: false,
		});
		appState.map = map;

		map.addControl(new NavigationControl({ showCompass: false }), 'top-left');
		map.addControl(new AttributionControl({ compact: false }), 'bottom-left');

		map.on('load', async () => {
			// Fire all data fetches in parallel
			const [roadData, transitData, trafficData, punctualTrafficData] = await Promise.all([
				loadGeoJSON(map.getBounds()),
				loadTransitGeoJSON(),
				loadTrafficGeoJSON(),
				loadPunctualTrafficGeoJSON(),
			]);

			appState.data = roadData;

			map.addSource('roads', { type: 'geojson', data: roadData, generateId: true });
			map.addLayer(roadLayer);
			map.addLayer(pedestrianLayer);
			map.addLayer(arrowsLayer);

			map.addSource('transit', { type: 'geojson', data: transitData });
			map.addLayer(tramLayer);
			map.addLayer(busLayer);

			map.addSource('traffic', { type: 'geojson', data: trafficData });
			map.addLayer(trafficLayer);

			addPopupLayer(map, 'traffic-layer', (props) => {
				const history = typeof props.history === 'string' ? JSON.parse(props.history) : props.history;
				return `<strong>${props.nom_voie || props.ident}</strong>
					<br>TMJO : ${props.mjo_val} veh/j (${props.year || '?'})
					${props.sens_cir ? '<br>Direction : ' + props.sens_cir : ''}
					${buildSparklineSVG(history)}`;
			});

			map.addSource('punctual-traffic', { type: 'geojson', data: punctualTrafficData });
			map.addLayer(punctualTrafficLayer);

			addPopupLayer(map, 'punctual-traffic-layer', (p) => {
				return `<strong>Comptage ponctuel</strong>
					<br>TMJO : ${p.tmjo_tv} veh/j (${p.annee || '?'})
					${p.orientation ? '<br>Direction : ' + p.orientation : ''}
					<br>VL : ${p.tmjo_vl ?? '?'} / PL : ${p.tmjo_pl ?? '?'}
					<br>HPM : ${p.hpm_tv ?? '?'} veh/h — HPS : ${p.hps_tv ?? '?'} veh/h
					${p.v85_vl ? '<br>V85 VL : ' + p.v85_vl + ' km/h' : ''}
					${p.v85_pl ? '<br>V85 PL : ' + p.v85_pl + ' km/h' : ''}`;
			});

			map.addSource('parlons-velo', { type: 'geojson', data: { type: 'FeatureCollection', features: [] } });
			map.addLayer(parlonsVeloLayer);

			addPopupLayer(map, 'parlons-velo-layer', (props) => {
				return `<strong>Point a ameliorer</strong>
					${props.description ? '<br>' + props.description : ''}`;
			});

			refreshParlonsVelo();

			map.addSource('route', { type: 'geojson', data: { type: 'FeatureCollection', features: [] } });
			map.addSource('route-markers', { type: 'geojson', data: { type: 'FeatureCollection', features: [] } });
			map.addLayer(routeLayer);
			map.addLayer(routeMarkersLayer);

			map.on('click', (e) => {
				if (appState.routingActive) {
					handleRoutingClick(e.lngLat, map, appState.data, routingState);
				}
			});

			map.on('click', 'road-layer', handleClick);
			map.on('click', 'pedestrian-layer', handleClick);
		});

		// moveend fires on both pan and zoom — no need for zoomend
		map.on('moveend', () => { refreshData(); refreshParlonsVelo(); });

		return () => {
			map.remove();
			appState.map = null;
		};
	});
</script>

<div bind:this={mapContainer} style="width: 100%; height: 100vh;"></div>
