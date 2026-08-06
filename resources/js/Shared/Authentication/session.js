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
