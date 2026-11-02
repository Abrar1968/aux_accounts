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

// POST JSON and get text/HTML back (email preview). Laravel refreshes the XSRF-TOKEN cookie on
// every response, so it stays valid after login/session regeneration (unlike the meta tag).
export async function postForHtml(url, body) {
    const xsrf = decodeURIComponent(document.cookie.split('; ').find((c) => c.startsWith('XSRF-TOKEN='))?.split('=')[1] ?? '');
    const res = await fetch(url, {
        method: 'POST',
        headers: { Accept: 'text/html', 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-XSRF-TOKEN': xsrf },
        credentials: 'same-origin',
        body: JSON.stringify(body),
    });
    if (!res.ok) throw new Error(`Request failed (${res.status})`);
    return res.text();
}
