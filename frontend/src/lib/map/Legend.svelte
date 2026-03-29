<script>
	import { appState } from '$lib/stores.svelte.js';
	import { refreshParlonsVelo } from '$lib/domain/map-actions.js';

	let tramChecked = $state(true);
	let busChecked = $state(false);
	let trafficChecked = $state(false);
	let punctualTrafficChecked = $state(false);
	let parlonsVeloChecked = $state(false);
	let hierarchyChecked = $state(false);
	let onlyModifiedChecked = $state(false);

	function toggleLayer(layerId, visible) {
		const map = appState.map;
		if (!map) return;
		map.setLayoutProperty(layerId, 'visibility', visible ? 'visible' : 'none');
	}

	function handleHierarchy(e) {
		hierarchyChecked = e.target.checked;
		const map = appState.map;
		if (!map) return;
		if (hierarchyChecked) {
			map.setPaintProperty('road-layer', 'line-width', [
				'match', ['get', 'highway'],
				'primary', 6, 'secondary', 5, 'tertiary', 4,
				'residential', 3, 'living_street', 2, 3,
			]);
			map.setLayoutProperty('arrows', 'text-size', [
				'match', ['get', 'highway'],
				'primary', 20, 'secondary', 18, 'tertiary', 16,
				'residential', 14, 'living_street', 12, 14,
			]);
		} else {
			map.setPaintProperty('road-layer', 'line-width', 3);
			map.setLayoutProperty('arrows', 'text-size', 16);
		}
	}
	function handleOnlyModified(e) {
		onlyModifiedChecked = e.target.checked;
		const map = appState.map;
		if (!map) return;
		if (onlyModifiedChecked) {
			map.setFilter('road-layer', ['all', ['!=', ['get', 'status'], 'pedestrian'], ['==', ['get', 'override'], true]]);
			map.setFilter('pedestrian-layer', ['all', ['==', ['get', 'status'], 'pedestrian'], ['==', ['get', 'override'], true]]);
			map.setFilter('arrows', ['all', ['!=', ['get', 'status'], 'pedestrian'], ['==', ['get', 'override'], true]]);
		} else {
			map.setFilter('road-layer', ['!=', ['get', 'status'], 'pedestrian']);
			map.setFilter('pedestrian-layer', ['==', ['get', 'status'], 'pedestrian']);
			map.setFilter('arrows', ['!=', ['get', 'status'], 'pedestrian']);
		}
	}

	function handleTram(e) {
		tramChecked = e.target.checked;
		toggleLayer('tram-layer', tramChecked);
	}
	function handleBus(e) {
		busChecked = e.target.checked;
		toggleLayer('bus-layer', busChecked);
	}
	function handleTraffic(e) {
		trafficChecked = e.target.checked;
		toggleLayer('traffic-layer', trafficChecked);
	}
	function handlePunctualTraffic(e) {
		punctualTrafficChecked = e.target.checked;
		toggleLayer('punctual-traffic-layer', punctualTrafficChecked);
	}
	function handleParlonsVelo(e) {
		parlonsVeloChecked = e.target.checked;
		toggleLayer('parlons-velo-layer', parlonsVeloChecked);
		if (parlonsVeloChecked) refreshParlonsVelo();
	}
</script>

<div class="absolute bottom-2 left-2 z-20 bg-white/90 rounded-lg shadow-lg p-3 text-xs pointer-events-auto">
	<div class="font-medium mb-1.5">Legende</div>
	<div class="flex items-center gap-2 mb-1">
		<span class="inline-block w-6 h-0.5 bg-gray-400"></span>
		<span>Rue (inchangee)</span>
	</div>
	<div class="flex items-center gap-2 mb-1">
		<span class="inline-block w-6 h-0.5 bg-black"></span>
		<span>Sens modifie</span>
	</div>
	<div class="flex items-center gap-2 mb-1">
		<span class="inline-block w-6 h-0.5 bg-amber-500"></span>
		<span>Filtre modal</span>
	</div>
	<div class="flex items-center gap-2 mb-1">
		<span class="inline-block w-6 h-0.5 bg-purple-500"></span>
		<span>Rue bornee</span>
	</div>
	<div class="flex items-center gap-2 mb-1">
		<span class="inline-block w-6 border-t-2 border-dashed border-green-500"></span>
		<span>Pietonne</span>
	</div>
	<label class="flex items-center gap-2 mt-2 cursor-pointer">
		<input type="checkbox" checked={hierarchyChecked} onchange={handleHierarchy} class="accent-gray-500">
		<span>Hierarchie routiere</span>
	</label>
	{#if hierarchyChecked}
		<div class="ml-5 mt-1">
			<div class="flex items-center gap-2 mb-1">
				<span class="inline-block w-6 border-t-[3px] border-gray-400"></span>
				<span>Primaire</span>
			</div>
			<div class="flex items-center gap-2 mb-1">
				<span class="inline-block w-6 border-t-2 border-gray-400"></span>
				<span>Secondaire</span>
			</div>
			<div class="flex items-center gap-2 mb-1">
				<span class="inline-block w-6 border-t border-gray-400"></span>
				<span>Tertiaire</span>
			</div>
			<div class="flex items-center gap-2 mb-1">
				<span class="inline-block w-6 border-t-[0.5px] border-gray-400"></span>
				<span>Residentielle</span>
			</div>
		</div>
	{/if}
	<label class="flex items-center gap-2 mt-1 cursor-pointer">
		<input type="checkbox" checked={onlyModifiedChecked} onchange={handleOnlyModified} class="accent-gray-500">
		<span>Modifications uniquement</span>
	</label>
	<div class="text-[10px] text-gray-400 mt-2">Source : OpenStreetMap</div>
	<div class="border-t border-gray-200 mt-2 pt-2">
		<div class="font-medium mb-1.5">Transport</div>
		<label class="flex items-center gap-2 mb-1 cursor-pointer">
			<input type="checkbox" checked={tramChecked} onchange={handleTram} class="accent-blue-500">
			<span>Tram</span>
		</label>
		<label class="flex items-center gap-2 mb-1 cursor-pointer">
			<input type="checkbox" checked={busChecked} onchange={handleBus} class="accent-orange-500">
			<span>Bus</span>
		</label>
		<label class="flex items-center gap-2 mb-1 cursor-pointer">
			<input type="checkbox" checked={trafficChecked} onchange={handleTraffic} class="accent-red-500">
			<span>Compteurs voitures</span>
		</label>
		<label class="flex items-center gap-2 mb-1 cursor-pointer">
			<input type="checkbox" checked={punctualTrafficChecked} onchange={handlePunctualTraffic} class="accent-blue-500">
			<span>Comptages ponctuels</span>
		</label>
		<div class="text-[10px] text-gray-400 mt-1">Source : Open Data Bordeaux Metropole</div>
	</div>
	<div class="border-t border-gray-200 mt-2 pt-2">
		<div class="font-medium mb-1.5">Velo</div>
		<label class="flex items-center gap-2 mb-1 cursor-pointer">
			<input type="checkbox" checked={parlonsVeloChecked} onchange={handleParlonsVelo} class="accent-red-500">
			<span class="flex items-center gap-1"><span class="inline-block w-2.5 h-2.5 rounded-full bg-red-500"></span>Points a ameliorer</span>
		</label>
		<div class="text-[10px] text-gray-400 mt-1">Source : Barometre Parlons Velo 2025 — FUB</div>
	</div>
</div>
