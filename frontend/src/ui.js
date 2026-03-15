import { appConfig } from './config.js';
import { getMap, setData, clearUserChanges, clearUserSplits, setActiveTool, isRoutingActive, setRoutingActive, updateSource } from './state.js';
import { loadGeoJSON } from './api.js';
import { setRoutingMode, clearRoute } from './routing.js';

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

            // Deactivate routing mode when selecting a toolbar tool
            if (isRoutingActive()) {
                setRoutingActive(false);
                const routingBtn = document.getElementById('btn-routing');
                if (routingBtn) routingBtn.classList.remove('ring-2', 'ring-blue-500', 'bg-blue-50');
                const routingPanel = document.getElementById('routing-panel');
                if (routingPanel) routingPanel.classList.add('hidden');
            }
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

export function initRoutingUI() {
    const btn = document.getElementById('btn-routing');
    const panel = document.getElementById('routing-panel');
    if (!btn || !panel) return;

    btn.addEventListener('click', () => {
        const active = !isRoutingActive();
        setRoutingActive(active);

        if (active) {
            btn.classList.add('ring-2', 'ring-blue-500', 'bg-blue-50');
            panel.classList.remove('hidden');
            // Deselect toolbar tools visually
            document.querySelectorAll('[data-tool]').forEach(b => b.classList.remove('ring-2', 'ring-blue-500', 'bg-blue-50'));
        } else {
            btn.classList.remove('ring-2', 'ring-blue-500', 'bg-blue-50');
            panel.classList.add('hidden');
            clearRoute();
        }
    });

    // Mode buttons
    const modeButtons = panel.querySelectorAll('[data-routing-mode]');
    modeButtons.forEach(mbtn => {
        mbtn.addEventListener('click', () => {
            modeButtons.forEach(b => b.classList.remove('ring-2', 'ring-blue-500', 'bg-blue-50'));
            mbtn.classList.add('ring-2', 'ring-blue-500', 'bg-blue-50');
            mbtn.classList.remove('bg-gray-100');
            modeButtons.forEach(b => { if (b !== mbtn) b.classList.add('bg-gray-100'); });
            setRoutingMode(mbtn.dataset.routingMode);
        });
    });

    // Clear button
    const clearBtn = document.getElementById('btn-clear-route');
    if (clearBtn) {
        clearBtn.addEventListener('click', () => clearRoute());
    }
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
