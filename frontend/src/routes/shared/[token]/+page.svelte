<script>
	import { page } from '$app/stores';
	import { appState } from '$lib/stores.svelte.js';
	import { getSharedMap } from '$lib/api/maps-api.js';
	import { applyMapData } from '$lib/domain/map-actions.js';

	let loadedToken = null;

	$effect(() => {
		const map = appState.map;
		const token = $page.params.token;
		if (map && token && loadedToken !== token) {
			loadedToken = token;
			map.resize();
			getSharedMap(token).then(({ ok, data }) => {
				if (ok && data) applyMapData(data);
			});
		}
	});
</script>
