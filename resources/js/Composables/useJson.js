// Session-authenticated JSON fetch for /ajax helpers (CSRF via meta tag, cookies included).
export async function getJson(url, params = {}) {
    const qs = new URLSearchParams(Object.entries(params).filter(([, v]) => v !== undefined && v !== null && v !== '')).toString();
    const res = await fetch(qs ? `${url}?${qs}` : url, {
        headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        credentials: 'same-origin',
    });
    if (!res.ok) throw new Error(`Request failed (${res.status})`);
    return res.json();
}
