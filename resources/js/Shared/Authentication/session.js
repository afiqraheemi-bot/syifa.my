function csrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';
}

export async function browserHttpRequest(url, options = {}) {
    const response = await fetch(url, {
        credentials: 'same-origin',
        ...options,
        headers: {
            Accept: 'application/json',
            'X-CSRF-TOKEN': csrfToken(),
            ...options.headers,
        },
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

export async function deleteBrowserSession(url) {
    return fetch(url, {
        method: 'DELETE',
        credentials: 'same-origin',
        headers: {
            Accept: 'application/json',
            'X-CSRF-TOKEN': csrfToken(),
        },
    });
}
