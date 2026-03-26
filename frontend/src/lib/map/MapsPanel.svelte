<script>
	import { onMount } from 'svelte';
	import { appState, uiState } from '$lib/stores.svelte.js';
	import { listMaps, createMap } from '$lib/api/maps-api.js';
	import MapItem from './MapItem.svelte';

	let mapsOwn = $state([]);
	let mapsShared = $state([]);

	onMount(() => {
		refreshList();
	});

	async function refreshList() {
		const { ok, data } = await listMaps();
		if (!ok) return;
		mapsOwn = data.own || [];
		mapsShared = data.shared || [];
	}

	async function handleNewMap() {
		const name = prompt('Nom de la carte :');
		if (!name) return;
		const map = appState.map;
		const center = map.getCenter();
		const { ok, data } = await createMap({
			name,
			centerLng: center.lng,
			centerLat: center.lat,
			zoom: map.getZoom(),
			changes: appState.userChanges,
			splits: appState.userSplits,
		});
		if (ok) {
			appState.currentMapId = data.id;
			appState.currentMapMeta = { name: data.name, description: data.description, status: data.status };
			await refreshList();
		}
	}
</script>

<div class="absolute top-12 right-2 z-30 w-72 max-h-[80vh] overflow-y-auto bg-white rounded-lg shadow-xl p-3 pointer-events-auto">
	<div class="flex items-center justify-between mb-2">
		<span class="font-medium text-sm">Mes cartes</span>
		<button onclick={() => uiState.mapsPanelOpen = false} class="text-gray-400 hover:text-gray-600 text-lg">&times;</button>
	</div>
	<button onclick={handleNewMap} class="w-full mb-2 px-3 py-1.5 rounded text-sm font-medium bg-blue-500 text-white hover:bg-blue-600">+ Nouvelle carte</button>

	{#if mapsOwn.length === 0 && mapsShared.length === 0}
		<p class="text-gray-400 text-xs">Aucune carte</p>
	{/if}

	{#if mapsOwn.length > 0}
		<div class="text-xs font-medium text-gray-500 mb-1">Mes cartes</div>
		{#each mapsOwn as mapData (mapData.id)}
			<MapItem {mapData} isOwner={true} onRefresh={refreshList} />
		{/each}
	{/if}

	{#if mapsShared.length > 0}
		<div class="text-xs font-medium text-gray-500 mb-1 mt-2">Partagees avec moi</div>
		{#each mapsShared as mapData (mapData.id)}
			<MapItem {mapData} isOwner={false} onRefresh={refreshList} />
		{/each}
	{/if}
</div>
