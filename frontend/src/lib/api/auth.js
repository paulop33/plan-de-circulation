import { apiFetch, apiUrl } from './helpers.js';

export async function fetchCurrentUser() {
	try {
		const res = await fetch(apiUrl('/api/auth/me'), { credentials: 'include' });
		if (res.ok) {
			return await res.json();
		}
	} catch (e) {
		// Not logged in
	}
	return null;
}

export async function login(email, password) {
	return await apiFetch('/api/auth/login', {
		method: 'POST',
		body: JSON.stringify({ email, password }),
	});
}

export async function register(email, password) {
	const { ok, data } = await apiFetch('/api/auth/register', {
		method: 'POST',
		body: JSON.stringify({ email, password }),
	});
	if (ok) {
		return await login(email, password);
	}
	return { ok, data };
}

export async function logout() {
	await fetch(apiUrl('/api/auth/logout'), {
		method: 'POST',
		credentials: 'include',
	});
}

export async function forgotPassword(email, resetBaseUrl) {
	return await apiFetch('/api/auth/forgot-password', {
		method: 'POST',
		body: JSON.stringify({ email, resetBaseUrl }),
	});
}

export async function resetPassword(token, password) {
	return await apiFetch('/api/auth/reset-password', {
		method: 'POST',
		body: JSON.stringify({ token, password }),
	});
}
