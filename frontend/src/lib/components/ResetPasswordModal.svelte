<script>
	import { uiState } from '$lib/stores.svelte.js';
	import { resetPassword } from '$lib/api/auth.js';
	import { goto } from '$app/navigation';

	let password = $state('');
	let confirm = $state('');
	let error = $state('');
	let message = $state('');

	async function handleSubmit(e) {
		e.preventDefault();
		error = '';
		message = '';

		if (password !== confirm) {
			error = 'Les mots de passe ne correspondent pas';
			return;
		}

		const { ok, data } = await resetPassword(uiState.resetToken, password);
		if (ok) {
			message = 'Mot de passe modifie ! Vous pouvez vous connecter.';
		} else {
			error = data?.error || 'Erreur lors de la reinitialisation';
		}
	}

	function close() {
		uiState.resetModalOpen = false;
		uiState.resetToken = null;
		password = '';
		confirm = '';
		error = '';
		message = '';
		goto('/');
	}
</script>

{#if uiState.resetModalOpen}
<div class="fixed inset-0 z-50 flex items-center justify-center bg-black/40">
	<div class="bg-white rounded-xl shadow-2xl p-6 w-80">
		<div class="flex items-center justify-between mb-4">
			<span class="font-semibold">Nouveau mot de passe</span>
			<button onclick={close} class="text-gray-400 hover:text-gray-600 text-xl">&times;</button>
		</div>
		<form onsubmit={handleSubmit} class="flex flex-col gap-3">
			<input bind:value={password} type="password" placeholder="Nouveau mot de passe (min. 8 car.)" required minlength="8" class="px-3 py-2 border border-gray-300 rounded text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
			<input bind:value={confirm} type="password" placeholder="Confirmer le mot de passe" required minlength="8" class="px-3 py-2 border border-gray-300 rounded text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
			{#if error}
				<div class="text-red-600 text-xs">{error}</div>
			{/if}
			{#if message}
				<div class="text-green-600 text-xs">{message}</div>
			{/if}
			<button type="submit" class="px-3 py-2 rounded text-sm font-medium bg-blue-500 text-white hover:bg-blue-600">Reinitialiser</button>
		</form>
	</div>
</div>
{/if}
