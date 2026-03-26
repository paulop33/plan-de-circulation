<script>
	import { page } from '$app/stores';
	import { onMount } from 'svelte';
	import { uiState } from '$lib/stores.svelte.js';

	onMount(() => {
		uiState.resetToken = $page.params.token;
		uiState.resetModalOpen = true;
	});
</script>
