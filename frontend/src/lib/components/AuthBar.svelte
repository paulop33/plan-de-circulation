<script>
	import { page } from '$app/stores';
	import { appState, uiState } from '$lib/stores.svelte.js';
	import { logout } from '$lib/api/auth.js';
	import { saveCurrentMap } from '$lib/domain/map-actions.js';

	let isHome = $derived($page.route.id === '/');
	let isLoggedIn = $derived(appState.currentUser !== null);

	async function handleLogout() {
		await logout();
		appState.currentUser = null;
		appState.currentMapId = null;
		appState.currentMapMeta = null;
	}
</script>

<div class="fixed top-0 left-0 right-0 z-30 bg-white/95 shadow-sm px-4 py-1.5 flex items-center justify-between text-sm">
	<div class="flex items-center gap-3">
		<a href="/" class="font-semibold text-gray-700 hover:text-blue-600 no-underline">Plan de circulation</a>
		{#if appState.currentMapMeta}
			<span class="text-xs text-blue-600 font-medium px-2 py-0.5 bg-blue-50 rounded">{appState.currentMapMeta.name}</span>
		{/if}
	</div>
	<div class="flex items-center gap-2">
		{#if !isLoggedIn}
			<button onclick={() => uiState.loginModalOpen = true} class="px-3 py-1 rounded text-sm font-medium bg-blue-500 text-white hover:bg-blue-600">Connexion</button>
			<button onclick={() => uiState.registerModalOpen = true} class="px-3 py-1 rounded text-sm font-medium bg-gray-100 hover:bg-gray-200">Inscription</button>
		{:else}
			{#if !isHome}
				<button onclick={saveCurrentMap} class="px-3 py-1 rounded text-sm font-medium bg-green-500 text-white hover:bg-green-600">Sauvegarder</button>
			{/if}
			<button onclick={() => uiState.mapsPanelOpen = !uiState.mapsPanelOpen} class="px-3 py-1 rounded text-sm font-medium bg-gray-100 hover:bg-gray-200">Mes cartes</button>
			<span class="text-xs text-gray-500">{appState.currentUser.email}</span>
			<button onclick={handleLogout} class="px-3 py-1 rounded text-sm font-medium bg-gray-100 hover:bg-gray-200">Deconnexion</button>
		{/if}
	</div>
</div>
