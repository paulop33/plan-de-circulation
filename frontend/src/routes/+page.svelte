<script>
	import { onMount } from 'svelte';
	import { listPublicMaps } from '$lib/api/maps-api.js';

	let maps = $state([]);
	let loading = $state(true);

	onMount(async () => {
		const { ok, data } = await listPublicMaps();
		if (ok && data) maps = data;
		loading = false;
	});
</script>

<div class="pt-12">
	<!-- Hero -->
	<section class="bg-gradient-to-br from-blue-50 to-white py-16 px-4">
		<div class="max-w-3xl mx-auto text-center">
			<h1 class="text-4xl font-bold text-gray-800 mb-4">Plan de circulation</h1>
			<p class="text-lg text-gray-600 mb-2">Imaginez le plan de circulation de demain pour Bordeaux Metropole</p>
			<p class="text-sm text-gray-500 mb-8">Un outil collaboratif pour explorer, modifier et partager des scenarios de circulation : sens de rue, zones pietonnes, filtres modaux, bornes...</p>
			<a href="/map/new" class="inline-block px-6 py-3 bg-blue-500 text-white font-medium rounded-lg shadow hover:bg-blue-600 transition-colors">
				Ouvrir l'editeur de carte
			</a>
		</div>
	</section>

	<!-- Velo-Cite -->
	<section class="py-12 px-4 bg-white">
		<div class="max-w-3xl mx-auto flex flex-col sm:flex-row items-center gap-6">
			<img src="/assets/img/logo-VC-carte.png" width="100" height="100" alt="Logo Velo-Cite" class="flex-shrink-0">
			<div>
				<h2 class="text-xl font-semibold text-gray-800 mb-2">Velo-Cite Bordeaux Metropole</h2>
				<p class="text-sm text-gray-600">Velo-Cite est une association loi 1901 qui promeut l'usage du velo comme mode de deplacement quotidien sur Bordeaux Metropole. Cet outil est developpe pour aider a la reflexion collective sur les amenagements de voirie et les plans de circulation.</p>
			</div>
		</div>
	</section>

	<!-- Cartes publiques -->
	<section class="py-12 px-4 bg-gray-50">
		<div class="max-w-4xl mx-auto">
			<h2 class="text-xl font-semibold text-gray-800 mb-6 text-center">Cartes publiques</h2>
			<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
				{#if loading}
					<p class="text-gray-400 text-sm col-span-full text-center">Chargement...</p>
				{:else if maps.length === 0}
					<p class="text-gray-400 text-sm col-span-full text-center">Aucune carte publique pour le moment.</p>
				{:else}
					{#each maps as map (map.id)}
						<a href="/map/{map.id}" class="block p-4 bg-white rounded-lg shadow hover:shadow-md transition-shadow border border-gray-100">
							<div class="font-medium text-gray-800 mb-1">{map.name}</div>
							{#if map.description}
								<p class="text-sm text-gray-500 mb-2 line-clamp-2">{map.description}</p>
							{/if}
							<div class="text-xs text-gray-400 flex items-center justify-between">
								<span>{map.authorEmail || ''}</span>
								<span>{new Date(map.updatedAt).toLocaleDateString('fr-FR')}</span>
							</div>
						</a>
					{/each}
				{/if}
			</div>
		</div>
	</section>

	<!-- Sources -->
	<section class="py-12 px-4 bg-white">
		<div class="max-w-3xl mx-auto">
			<h2 class="text-xl font-semibold text-gray-800 mb-4 text-center">Sources de donnees</h2>
			<div class="grid grid-cols-1 sm:grid-cols-2 gap-6 text-sm text-gray-600">
				<div>
					<h3 class="font-medium text-gray-700 mb-1">OpenStreetMap</h3>
					<p>Reseau routier et donnees geographiques issues d'OpenStreetMap via l'API Overpass.</p>
				</div>
				<div>
					<h3 class="font-medium text-gray-700 mb-1">Open Data Bordeaux Metropole</h3>
					<p>Lignes de tramway, bus, compteurs de trafic et autres donnees ouvertes de la metropole bordelaise.</p>
				</div>
			</div>
		</div>
	</section>

	<!-- Footer -->
	<footer class="py-6 px-4 bg-gray-100 text-center text-xs text-gray-400">
		Plan de circulation &mdash; Un projet Velo-Cite Bordeaux Metropole
	</footer>
</div>
