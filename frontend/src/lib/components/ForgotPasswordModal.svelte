<script>
	import { uiState } from '$lib/stores.svelte.js';
	import { forgotPassword } from '$lib/api/auth.js';

	let email = $state('');
	let message = $state('');

	async function handleSubmit(e) {
		e.preventDefault();
		const resetBaseUrl = window.location.origin + '/';
		await forgotPassword(email, resetBaseUrl);
		message = 'Si un compte existe avec cet email, un lien de reinitialisation a ete envoye.';
	}
</script>

{#if uiState.forgotModalOpen}
<div class="fixed inset-0 z-50 flex items-center justify-center bg-black/40">
	<div class="bg-white rounded-xl shadow-2xl p-6 w-80">
		<div class="flex items-center justify-between mb-4">
			<span class="font-semibold">Mot de passe oublie</span>
			<button onclick={() => { uiState.forgotModalOpen = false; message = ''; }} class="text-gray-400 hover:text-gray-600 text-xl">&times;</button>
		</div>
		<form onsubmit={handleSubmit} class="flex flex-col gap-3">
			<input bind:value={email} type="email" placeholder="Votre email" required class="px-3 py-2 border border-gray-300 rounded text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
			{#if message}
				<div class="text-green-600 text-xs">{message}</div>
			{/if}
			<button type="submit" class="px-3 py-2 rounded text-sm font-medium bg-blue-500 text-white hover:bg-blue-600">Envoyer</button>
		</form>
	</div>
</div>
{/if}
