<script>
	import { appState, uiState } from '$lib/stores.svelte.js';
	import { getMap as getMapApi, updateMap, deleteMap, duplicateMap, shareMap } from '$lib/api/maps-api.js';
	import { applyMapData } from '$lib/domain/map-actions.js';

	let { mapData, isOwner, onRefresh } = $props();

	const statusBadges = {
		draft: { class: 'bg-gray-100 text-gray-500', label: 'brouillon' },
		private: { class: 'bg-blue-100 text-blue-600', label: 'privee' },
		public: { class: 'bg-green-100 text-green-600', label: 'publique' },
	};

	let badge = $derived(statusBadges[mapData.status]);

	async function loadMap() {
		const { ok, data } = await getMapApi(mapData.id);
		if (!ok) return;

		applyMapData(data);
		uiState.mapsPanelOpen = false;
	}

	async function handleStatus(e) {
		e.stopPropagation();
		const statuses = ['draft', 'private', 'public'];
		const current = statuses.indexOf(mapData.status);
		const next = statuses[(current + 1) % statuses.length];
		await updateMap(mapData.id, { status: next });
		if (appState.currentMapId === mapData.id && appState.currentMapMeta) {
			appState.currentMapMeta = { ...appState.currentMapMeta, status: next };
		}
		await onRefresh();
	}

	async function handleShare(e) {
		e.stopPropagation();
		const email = prompt("Email de l'utilisateur a qui partager (laisser vide pour un lien) :");
		const shareData = email ? { email, canEdit: false } : { canEdit: false };
		const { ok, data } = await shareMap(mapData.id, shareData);
		if (ok) {
			if (data.token) {
				const link = `${window.location.origin}/shared/${data.token}`;
				prompt('Lien de partage (copiez-le) :', link);
			} else {
				alert('Carte partagee avec ' + email);
			}
		} else {
			alert(data?.error || 'Erreur lors du partage');
		}
	}

	async function handleDuplicate(e) {
		e.stopPropagation();
		const { ok } = await duplicateMap(mapData.id);
		if (ok) await onRefresh();
	}

	async function handleDelete(e) {
		e.stopPropagation();
		if (!confirm(`Supprimer la carte "${mapData.name}" ?`)) return;
		const { ok } = await deleteMap(mapData.id);
		if (ok) {
			if (appState.currentMapId === mapData.id) {
				appState.currentMapId = null;
				appState.currentMapMeta = null;
			}
			await onRefresh();
		}
	}
</script>

<div class="p-2 rounded hover:bg-gray-50 cursor-pointer border border-gray-100 mb-1">
	<div class="flex items-center justify-between">
		<button onclick={loadMap} class="text-sm font-medium truncate text-left">{mapData.name}</button>
		{#if badge}
			<span class="text-[10px] px-1 py-0.5 rounded {badge.class}">{badge.label}</span>
		{/if}
	</div>
	<div class="text-[10px] text-gray-400 mt-0.5">{new Date(mapData.updatedAt).toLocaleDateString('fr-FR')}</div>
	{#if isOwner}
		<div class="flex gap-1 mt-1">
			<button onclick={handleStatus} class="text-[10px] px-1.5 py-0.5 bg-gray-100 hover:bg-gray-200 rounded">Visibilite</button>
			<button onclick={handleShare} class="text-[10px] px-1.5 py-0.5 bg-gray-100 hover:bg-gray-200 rounded">Partager</button>
			<button onclick={handleDuplicate} class="text-[10px] px-1.5 py-0.5 bg-gray-100 hover:bg-gray-200 rounded">Dupliquer</button>
			<button onclick={handleDelete} class="text-[10px] px-1.5 py-0.5 bg-red-50 hover:bg-red-100 text-red-600 rounded">Suppr.</button>
		</div>
	{/if}
</div>
