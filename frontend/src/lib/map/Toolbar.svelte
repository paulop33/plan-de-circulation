<script>
	import { appState, routingState, updateSource } from '$lib/stores.svelte.js';
	import { loadGeoJSON } from '$lib/api/api.js';
	import { clearRoute } from '$lib/domain/routing.js';
	import { checkConnectivity } from '$lib/domain/graph.js';

	const tools = [
		{ id: 'direction', label: 'Sens' },
		{ id: 'pedestrian', label: 'Pieton' },
		{ id: 'filter', label: 'Filtre' },
		{ id: 'bollard', label: 'Borne' },
		{ id: 'split', label: '\u2702 Couper' },
	];

	let connectivityBanner = $state(null);
	let bannerTimeout;

	function selectTool(toolId) {
		appState.activeTool = toolId;
		if (appState.routingActive) {
			appState.routingActive = false;
			clearRoute(appState.map, routingState);
		}
	}

	async function handleReset() {
		const map = appState.map;
		if (!map) return;
		appState.userChanges = {};
		appState.userSplits = {};
		appState.currentMapId = null;
		appState.currentMapMeta = null;
		const data = await loadGeoJSON(map.getBounds());
		appState.data = data;
		updateSource();
	}

	function handleCheck() {
		const result = checkConnectivity(appState.data);
		if (result.connected) {
			connectivityBanner = { text: 'Reseau connecte', color: 'bg-green-600' };
		} else {
			connectivityBanner = { text: `${result.componentCount} zones deconnectees`, color: 'bg-red-600' };
		}
		clearTimeout(bannerTimeout);
		bannerTimeout = setTimeout(() => { connectivityBanner = null; }, 5000);
	}

	function toggleRouting() {
		appState.routingActive = !appState.routingActive;
		if (!appState.routingActive) {
			clearRoute(appState.map, routingState);
		}
	}
</script>

<div class="bg-white rounded-lg shadow-lg p-2 flex flex-col gap-1 pointer-events-auto">
	{#each tools as tool}
		<button
			onclick={() => selectTool(tool.id)}
			class="px-3 py-1.5 rounded text-sm font-medium"
			class:ring-2={appState.activeTool === tool.id && !appState.routingActive}
			class:ring-blue-500={appState.activeTool === tool.id && !appState.routingActive}
			class:bg-blue-50={appState.activeTool === tool.id && !appState.routingActive}
		>
			{tool.label}
		</button>
	{/each}
</div>
<div class="bg-white rounded-lg shadow-lg p-2 flex flex-col gap-1 pointer-events-auto">
	<button onclick={handleCheck} class="px-3 py-1.5 rounded text-sm font-medium bg-gray-100 hover:bg-gray-200">Verifier</button>
	<button onclick={handleReset} class="px-3 py-1.5 rounded text-sm font-medium bg-gray-100 hover:bg-gray-200">Reinitialiser</button>
</div>
<div class="bg-white rounded-lg shadow-lg p-2 flex flex-col gap-1 pointer-events-auto">
	<button
		onclick={toggleRouting}
		class="px-3 py-1.5 rounded text-sm font-medium bg-gray-100 hover:bg-gray-200"
		class:ring-2={appState.routingActive}
		class:ring-blue-500={appState.routingActive}
		class:bg-blue-50={appState.routingActive}
	>
		Itineraire
	</button>
</div>

{#if connectivityBanner}
<div class="fixed top-16 left-1/2 -translate-x-1/2 z-30 px-6 py-3 rounded-lg shadow-lg text-white font-medium transition-opacity duration-300 pointer-events-auto {connectivityBanner.color}">
	{connectivityBanner.text}
</div>
{/if}
