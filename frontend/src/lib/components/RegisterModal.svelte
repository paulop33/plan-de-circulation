<script>
	import { appState, uiState } from '$lib/stores.svelte.js';
	import { register } from '$lib/api/auth.js';

	let email = $state('');
	let password = $state('');
	let confirm = $state('');
	let error = $state('');

	async function handleSubmit(e) {
		e.preventDefault();
		error = '';

		if (password !== confirm) {
			error = 'Les mots de passe ne correspondent pas';
			return;
		}

		const { ok, data } = await register(email, password);
		if (ok) {
			appState.currentUser = data;
			uiState.registerModalOpen = false;
			email = '';
			password = '';
			confirm = '';
		} else {
			error = data?.error || "Erreur lors de l'inscription";
		}
	}
</script>

{#if uiState.registerModalOpen}
<div class="fixed inset-0 z-50 flex items-center justify-center bg-black/40">
	<div class="bg-white rounded-xl shadow-2xl p-6 w-80">
		<div class="flex items-center justify-between mb-4">
			<span class="font-semibold">Inscription</span>
			<button onclick={() => uiState.registerModalOpen = false} class="text-gray-400 hover:text-gray-600 text-xl">&times;</button>
		</div>
		<form onsubmit={handleSubmit} class="flex flex-col gap-3">
			<input bind:value={email} type="email" placeholder="Email" required class="px-3 py-2 border border-gray-300 rounded text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
			<input bind:value={password} type="password" placeholder="Mot de passe (min. 8 car.)" required minlength="8" class="px-3 py-2 border border-gray-300 rounded text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
			<input bind:value={confirm} type="password" placeholder="Confirmer le mot de passe" required minlength="8" class="px-3 py-2 border border-gray-300 rounded text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
			{#if error}
				<div class="text-red-600 text-xs">{error}</div>
			{/if}
			<button type="submit" class="px-3 py-2 rounded text-sm font-medium bg-blue-500 text-white hover:bg-blue-600">Creer le compte</button>
			<div class="text-xs text-center text-gray-500">
				<button type="button" onclick={() => { uiState.registerModalOpen = false; uiState.loginModalOpen = true; }} class="text-blue-500 hover:underline">Deja un compte ? Se connecter</button>
			</div>
		</form>
	</div>
</div>
{/if}
