/**
 * Sub-path support, e.g. for the l0 app manager, which serves the app at https://l0.ee/<path>/.
 *
 * The frontend uses root-absolute URLs ('/expenses') throughout. Behind a trusted proxy that
 * forwards a prefix, the layout puts it in <meta name="base-path">, and installBasePath()
 * prefixes same-origin URLs for Inertia visits, fetch, XHR and plain links. Without a prefix
 * (local dev, own domain) everything here is a no-op.
 */

const basePath = (document.querySelector<HTMLMetaElement>('meta[name="base-path"]')?.content ?? '').replace(/\/+$/, '')

function isUnderBasePath(pathname: string): boolean {
    return pathname === basePath || pathname.startsWith(`${basePath}/`)
}

/** Adds the base path to a same-origin URL that doesn't have it yet. */
export function withBasePath(url: string): string {
    if (!basePath) {
        return url
    }

    let resolved: URL
    try {
        resolved = new URL(url, window.location.href)
    } catch {
        return url
    }

    if (resolved.origin !== window.location.origin || isUnderBasePath(resolved.pathname)) {
        return url
    }

    resolved.pathname = `${basePath}${resolved.pathname}`

    return resolved.href
}

/** Removes the base path from a path, e.g. Inertia's page URL, so it can be compared with app routes. */
export function withoutBasePath(path: string): string {
    if (!basePath || !isUnderBasePath(path.split(/[?#]/)[0])) {
        return path
    }

    const rest = path.slice(basePath.length)

    return rest.startsWith('/') ? rest : `/${rest}`
}

/** Routes every same-origin request and link through withBasePath(). Call once, before Inertia starts. */
export function installBasePath(): void {
    if (!basePath) {
        return
    }

    const nativeFetch = window.fetch.bind(window)
    window.fetch = (input: RequestInfo | URL, init?: RequestInit): Promise<Response> => {
        if (input instanceof Request) {
            const url = withBasePath(input.url)

            return nativeFetch(url === input.url ? input : new Request(url, input), init)
        }

        return nativeFetch(withBasePath(input.toString()), init)
    }

    // Keep the caller's argument count: open(method, url, undefined) would make the request synchronous.
    const nativeOpen = XMLHttpRequest.prototype.open as (this: XMLHttpRequest, ...args: unknown[]) => void
    XMLHttpRequest.prototype.open = function (this: XMLHttpRequest, method: string, url: string | URL, ...rest: unknown[]): void {
        nativeOpen.call(this, method, withBasePath(url.toString()), ...rest)
    } as typeof XMLHttpRequest.prototype.open

    // Inertia intercepts plain clicks on <Link>, but new-tab clicks, context menus and plain <a>
    // tags use the DOM href, so fix it right before the browser acts on it.
    const prefixLink = (event: Event): void => {
        const anchor = event.target instanceof Element ? event.target.closest('a[href]') : null
        const href = anchor?.getAttribute('href')

        if (anchor && href) {
            const prefixed = withBasePath(href)

            if (prefixed !== href) {
                anchor.setAttribute('href', prefixed)
            }
        }
    }

    for (const type of ['mousedown', 'click', 'auxclick', 'contextmenu']) {
        document.addEventListener(type, prefixLink, true)
    }
}
