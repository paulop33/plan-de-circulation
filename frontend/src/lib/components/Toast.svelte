<script>
	import { uiState } from '$lib/stores.svelte.js';

	let visible = $state(false);

	$effect(() => {
		if (uiState.toast) {
			visible = true;
			const timer = setTimeout(() => {
				visible = false;
				setTimeout(() => { uiState.toast = null; }, 300);
			}, 2000);
			return () => clearTimeout(timer);
		}
	});
</script>

{#if uiState.toast}
<div
	class="fixed top-16 left-1/2 -translate-x-1/2 z-50 px-4 py-2 bg-green-600 text-white rounded-lg shadow-lg text-sm font-medium transition-opacity duration-300"
	class:opacity-0={!visible}
>
	{uiState.toast}
</div>
{/if}
