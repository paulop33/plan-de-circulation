<script>
	import { page } from '$app/stores';
	import { appState } from '$lib/stores.svelte.js';
	import { getPublicMap } from '$lib/api/maps-api.js';
	import { applyMapData } from '$lib/domain/map-actions.js';

	let loadedId = null;

	$effect(() => {
		const map = appState.map;
		const id = parseInt($page.params.id);
		if (map && id && loadedId !== id) {
			loadedId = id;
			map.resize();
			getPublicMap(id).then(({ ok, data }) => {
				if (ok && data) applyMapData(data);
			});
		}
	});
</script>
