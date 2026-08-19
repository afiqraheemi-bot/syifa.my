function csrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';
}

function csrfHeaders() {
    return { 'X-CSRF-TOKEN': csrfToken() };
}

function sameOriginUrl(url) {
    if (typeof window === 'undefined') return url;

    try {
        const parsed = new URL(url, window.location.origin);

        // All calls through this helper are application routes. Keeping their
        // path while using the active browser origin prevents an APP_URL
        // www/non-www mismatch from dropping the authenticated session cookie.
        return `${window.location.origin}${parsed.pathname}${parsed.search}${parsed.hash}`;
    } catch {
        return url;
    }
}

function requestHeaders(headers = {}, csrfTokenOverride = '') {
    const result = new Headers(headers);
    if (!result.has('Accept')) result.set('Accept', 'application/json');
    result.delete('X-CSRF-TOKEN');
    result.delete('X-XSRF-TOKEN');
    const csrf = csrfTokenOverride ? { 'X-CSRF-TOKEN': csrfTokenOverride } : csrfHeaders();
    for (const [name, value] of Object.entries(csrf)) result.set(name, value);

    return result;
}

async function refreshCsrfToken() {
    if (typeof window === 'undefined' || typeof DOMParser === 'undefined') return '';

    try {
        const response = await fetch(window.location.href, {
            method: 'GET',
            credentials: 'same-origin',
            cache: 'no-store',
            headers: { Accept: 'text/html' },
        });
        if (!response.ok) return '';

        const html = await response.text();
        const documentFromServer = new DOMParser().parseFromString(html, 'text/html');
        const freshToken = documentFromServer
            .querySelector('meta[name="csrf-token"]')
            ?.getAttribute('content');
        const csrfMeta = document.querySelector('meta[name="csrf-token"]');
        if (!freshToken || !csrfMeta) return '';

        csrfMeta.setAttribute('content', freshToken);
        return freshToken;
    } catch {
        return '';
    }
}

async function performBrowserRequest(url, options, csrfTokenOverride = '') {
    const token = csrfTokenOverride || csrfToken();
    if (options.body instanceof FormData && token) options.body.set('_token', token);

    return fetch(sameOriginUrl(url), {
        credentials: 'same-origin',
        ...options,
        headers: requestHeaders(options.headers, csrfTokenOverride),
    });
}

export async function browserHttpRequest(url, options = {}) {
    let response = await performBrowserRequest(url, options);
    if (response.status === 419) {
        const freshToken = await refreshCsrfToken();
        if (freshToken) response = await performBrowserRequest(url, options, freshToken);
    }
    const contentType = response.headers.get('content-type') ?? '';

    return {
        ok: response.ok,
        status: response.status,
        body: contentType.includes('json') ? await response.json().catch(() => ({})) : {},
    };
}

export async function createBrowserSession(url, credentials, remember = false) {
    return browserHttpRequest(url, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            email: credentials.email,
            password: credentials.password,
            remember,
        }),
    });
}

export async function createUnifiedBrowserSession(urls, credentials, remember = false) {
    const attempts = [
        ['clinic_registration', urls.clinicRegistration],
        ['clinic_owner', urls.clinicOwner],
        ['platform_identity', urls.platform],
    ];
    let lastResult = null;

    for (const [boundary, url] of attempts) {
        const result = await createBrowserSession(url, credentials, remember);

        if (result.status === 401) {
            lastResult = result;
            continue;
        }

        return { ...result, boundary };
    }

    return { ...(lastResult ?? { ok: false, status: 401, body: {} }), boundary: null };
}

export async function completePlatformMfa(url, code) {
    return browserHttpRequest(url, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({ code }),
    });
}

export async function requestClinicOwnerPasswordReset(url, email) {
    return browserHttpRequest(url, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({ email }),
    });
}

export async function requestPlatformPasswordReset(url, email) {
    return browserHttpRequest(url, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({ email }),
    });
}

export async function requestUnifiedPasswordReset(urls, email) {
    const requests = await Promise.allSettled([
        requestClinicOwnerPasswordReset(urls.clinicOwner, email),
        requestPlatformPasswordReset(urls.platform, email),
    ]);

    return {
        ok: requests.some((request) => request.status === 'fulfilled' && request.value.ok),
    };
}

export async function submitPlatformPasswordReset(
    url,
    { email, token, password, passwordConfirmation },
) {
    return browserHttpRequest(url, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            email,
            token,
            password,
            password_confirmation: passwordConfirmation,
        }),
    });
}

export async function deleteBrowserSession(url) {
    return fetch(url, {
        method: 'DELETE',
        credentials: 'same-origin',
        headers: requestHeaders(),
    });
}
