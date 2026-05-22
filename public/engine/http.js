function csrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';
}

export function csrfHeaders(headers = {}) {
    const token = csrfToken();

    return token ? { ...headers, 'X-CSRF-Token': token } : { ...headers };
}

export async function postJson(url, body, options = {}) {
    const response = await fetch(url, {
        ...options,
        method: 'POST',
        headers: csrfHeaders({
            ...(options.headers ?? {}),
            'Content-Type': 'application/json',
        }),
        body: JSON.stringify(body),
    });
    const data = await response.json().catch(() => ({}));

    return { response, data };
}

export async function postJsonOk(url, body, options = {}) {
    const { response, data } = await postJson(url, body, options);

    if (!response.ok) {
        throw new Error(data.error || `HTTP ${response.status}`);
    }

    return data;
}
