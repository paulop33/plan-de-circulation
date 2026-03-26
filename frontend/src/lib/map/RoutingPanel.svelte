<script>
	import { appState, routingState } from '$lib/stores.svelte.js';
	import { clearRoute, computeAndDisplayRoute } from '$lib/domain/routing.js';

	let distance = $state(null);
	let error = $state('');

	const modes = [
		{ id: 'car', label: 'Voiture' },
		{ id: 'bike', label: 'Velo' },
	];

	function applyResult(result) {
		if (result?.error) {
			error = result.error;
			distance = null;
		} else if (result?.distance != null) {
			distance = result.distance;
			error = '';
		} else {
			distance = null;
			error = '';
		}
	}

	function setMode(mode) {
		routingState.mode = mode;
		if (routingState.start && routingState.end && appState.map) {
			applyResult(computeAndDisplayRoute(appState.map, appState.data, routingState));
		}
	}

	function handleClear() {
		clearRoute(appState.map, routingState);
		distance = null;
		error = '';
	}

	// Reactively display routing results from map clicks (read-only derivation, no side effects)
	$effect(() => {
		const hasRoute = routingState.start && routingState.end;
		if (!hasRoute) {
			distance = null;
			error = '';
		}
	});

	let formattedDistance = $derived(
		distance != null
			? (distance >= 1000
				? `Distance : ${(distance / 1000).toFixed(1)} km`
				: `Distance : ${Math.round(distance)} m`)
			: null
	);

	// Expose for MapContainer to call after routing clicks
	export function onRouteResult(result) {
		applyResult(result);
	}
</script>

{#if appState.routingActive}
<div class="bg-white rounded-lg shadow-lg p-3 pointer-events-auto text-sm">
	<div class="font-medium mb-2">Itineraire</div>
	<div class="flex gap-1 mb-2">
		{#each modes as mode}
			<button
				onclick={() => setMode(mode.id)}
				class="flex-1 px-2 py-1 rounded text-xs font-medium"
				class:ring-2={routingState.mode === mode.id}
				class:ring-blue-500={routingState.mode === mode.id}
				class:bg-blue-50={routingState.mode === mode.id}
				class:bg-gray-100={routingState.mode !== mode.id}
			>
				{mode.label}
			</button>
		{/each}
	</div>
	<div class="text-xs text-gray-500 mb-2">Cliquez 2 points sur la carte</div>
	{#if formattedDistance}
		<div class="text-sm font-medium text-blue-600 mb-2">{formattedDistance}</div>
	{/if}
	{#if error}
		<div class="text-sm text-red-600 mb-2">{error}</div>
	{/if}
	<button onclick={handleClear} class="w-full px-2 py-1 rounded text-xs font-medium bg-gray-100 hover:bg-gray-200">Effacer</button>
</div>
{/if}
