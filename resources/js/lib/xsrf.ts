/**
 * Minimal XSRF-aware JSON helpers for non-Inertia endpoints (device-code
 * login/link): the web middleware sets the XSRF-TOKEN cookie, which must
 * be echoed back as the X-XSRF-TOKEN header on unsafe methods.
 */
export function xsrfToken(): string {
    const match = document.cookie.match(/(?:^|;\s*)XSRF-TOKEN=([^;]*)/);

    return match ? decodeURIComponent(match[1]) : '';
}

export async function postJson<T>(url: string, body?: unknown): Promise<T> {
    const response = await fetch(url, {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            'X-XSRF-TOKEN': xsrfToken(),
        },
        body: body === undefined ? undefined : JSON.stringify(body),
    });

    if (!response.ok) {
        throw Object.assign(new Error(`request failed: ${response.status}`), {
            status: response.status,
            body: (await response.json().catch(() => null)) as unknown,
        });
    }

    return (await response.json()) as T;
}

export async function delJson<T>(url: string): Promise<T> {
    const response = await fetch(url, {
        method: 'DELETE',
        credentials: 'same-origin',
        headers: {
            Accept: 'application/json',
            'X-XSRF-TOKEN': xsrfToken(),
        },
    });

    if (!response.ok) {
        throw Object.assign(new Error(`request failed: ${response.status}`), {
            status: response.status,
            body: (await response.json().catch(() => null)) as unknown,
        });
    }

    return (await response.json()) as T;
}
