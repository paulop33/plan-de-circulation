import { appConfig } from './config.js';
import { getMap, setData, clearUserChanges, clearUserSplits, setActiveTool, updateSource } from './state.js';
import { loadGeoJSON } from './api.js';

export function updateZoomOverlay() {
    const map = getMap();
    const overlay = document.getElementById('zoom-overlay');
    if (!overlay || !map) return;

    if (map.getZoom() < appConfig.maxZoomRefresh) {
        overlay.classList.remove('hidden');
    } else {
        overlay.classList.add('hidden');
    }
}

export function initToolbar() {
    const buttons = document.querySelectorAll('[data-tool]');
    buttons.forEach(btn => {
        btn.addEventListener('click', () => {
            buttons.forEach(b => b.classList.remove('ring-2', 'ring-blue-500', 'bg-blue-50'));
            btn.classList.add('ring-2', 'ring-blue-500', 'bg-blue-50');
            setActiveTool(btn.dataset.tool);
        });
    });
}

export function initResetButton() {
    const btn = document.getElementById('btn-reset');
    if (!btn) return;

    btn.addEventListener('click', async () => {
        const map = getMap();
        clearUserChanges();
        clearUserSplits();
        const data = await loadGeoJSON(map.getBounds());
        setData(data);
        updateSource();
    });
}

export function showConnectivityResult(result) {
    let banner = document.getElementById('connectivity-banner');
    if (!banner) {
        banner = document.createElement('div');
        banner.id = 'connectivity-banner';
        banner.className = 'absolute top-16 left-1/2 -translate-x-1/2 z-30 px-6 py-3 rounded-lg shadow-lg text-white font-medium transition-opacity duration-300';
        document.body.appendChild(banner);
    }

    if (result.connected) {
        banner.textContent = 'Réseau connecté';
        banner.classList.remove('bg-red-600');
        banner.classList.add('bg-green-600');
    } else {
        banner.textContent = `${result.componentCount} zones déconnectées`;
        banner.classList.remove('bg-green-600');
        banner.classList.add('bg-red-600');
    }

    banner.classList.remove('hidden');
    setTimeout(() => banner.classList.add('hidden'), 5000);
}
