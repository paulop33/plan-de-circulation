import { appConfig } from './config.js';

export function apiUrl(path) {
	return (appConfig.backendUrl || '') + path;
}

export async function apiFetch(path, options = {}) {
	const res = await fetch(apiUrl(path), {
		credentials: 'include',
		headers: { 'Content-Type': 'application/json' },
		...options,
	});
	const data = await res.json().catch(() => null);
	return { ok: res.ok, status: res.status, data };
}
