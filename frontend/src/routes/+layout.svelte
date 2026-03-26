<script>
	import './layout.css';
	import { page } from '$app/stores';
	import { onMount } from 'svelte';
	import { appState } from '$lib/stores.svelte.js';
	import { fetchCurrentUser } from '$lib/api/auth.js';
	import AuthBar from '$lib/components/AuthBar.svelte';
	import LoginModal from '$lib/components/LoginModal.svelte';
	import RegisterModal from '$lib/components/RegisterModal.svelte';
	import ForgotPasswordModal from '$lib/components/ForgotPasswordModal.svelte';
	import ResetPasswordModal from '$lib/components/ResetPasswordModal.svelte';
	import Toast from '$lib/components/Toast.svelte';
	import MapView from '$lib/views/MapView.svelte';

	let { children } = $props();

	let isHome = $derived($page.route.id === '/');

	onMount(async () => {
		const user = await fetchCurrentUser();
		appState.currentUser = user;
	});

	// Resize map when switching from home to map view
	$effect(() => {
		if (!isHome && appState.map) {
			// Small delay to let the DOM unhide before resize
			setTimeout(() => appState.map?.resize(), 50);
		}
	});
</script>

<AuthBar />

{@render children()}

<div class:hidden={isHome}>
	<MapView />
</div>

<LoginModal />
<RegisterModal />
<ForgotPasswordModal />
<ResetPasswordModal />
<Toast />
