<script>
	import { appState, uiState } from '$lib/stores.svelte.js';
	import MapContainer from '$lib/map/MapContainer.svelte';
	import Toolbar from '$lib/map/Toolbar.svelte';
	import RoutingPanel from '$lib/map/RoutingPanel.svelte';
	import MapsPanel from '$lib/map/MapsPanel.svelte';
	import Legend from '$lib/map/Legend.svelte';
	import ZoomOverlay from '$lib/map/ZoomOverlay.svelte';
</script>

<div class="relative">
	<MapContainer />
	<ZoomOverlay />

	<div class="absolute top-12 right-2 z-20 flex flex-col gap-2 pointer-events-none">
		<Toolbar />
		<RoutingPanel />
	</div>

	{#if appState.currentUser && uiState.mapsPanelOpen}
		<MapsPanel />
	{/if}

	<Legend />

	<img class="my-0 absolute bottom-0 right-0 z-10" src="/assets/img/logo-VC-carte.png" width="75" height="75" alt="logo Velo-Cite Bordeaux Metropole">
</div>
