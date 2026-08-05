function csrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';
}

function cookieValue(name) {
    const prefix = `${encodeURIComponent(name)}=`;
    const cookie = document.cookie
        .split('; ')
        .find((entry) => entry.startsWith(prefix))
        ?.slice(prefix.length);

    return cookie ? decodeURIComponent(cookie) : '';
}

function csrfHeaders() {
    const xsrfToken = cookieValue('XSRF-TOKEN');

    return xsrfToken ? { 'X-XSRF-TOKEN': xsrfToken } : { 'X-CSRF-TOKEN': csrfToken() };
}

function requestHeaders(headers = {}) {
    const result = new Headers(headers);
    if (!result.has('Accept')) result.set('Accept', 'application/json');
    result.delete('X-CSRF-TOKEN');
    result.delete('X-XSRF-TOKEN');
    for (const [name, value] of Object.entries(csrfHeaders())) result.set(name, value);

    return result;
}

export async function browserHttpRequest(url, options = {}) {
    const response = await fetch(url, {
        credentials: 'same-origin',
        ...options,
        headers: requestHeaders(options.headers),
    });
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
