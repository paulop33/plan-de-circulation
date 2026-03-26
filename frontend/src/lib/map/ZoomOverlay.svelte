<script>
	import { appState } from '$lib/stores.svelte.js';
	import { appConfig } from '$lib/api/config.js';

	let zoom = $state(appConfig.mapInitZoom);

	$effect(() => {
		const map = appState.map;
		if (!map) return;
		const handler = () => { zoom = map.getZoom(); };
		map.on('zoomend', handler);
		return () => map.off('zoomend', handler);
	});

	let needsZoom = $derived(zoom < appConfig.maxZoomRefresh);
</script>

{#if needsZoom}
<div class="absolute inset-0 z-20 bg-black/50 flex items-center justify-center pointer-events-none">
	<span class="bg-white text-gray-800 px-6 py-3 rounded-lg shadow-lg text-lg font-medium">Zoomez davantage pour voir les rues</span>
</div>
{/if}
