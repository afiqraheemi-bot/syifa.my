const supportedNavigationKinds = new Set(['link', 'group']);

const freezeItems = (items) => Object.freeze(items.map((item) => Object.freeze({ ...item })));

const normalizeDashboardHref = (href) => {
    if (typeof href !== 'string' || href === '' || href.startsWith('/')) return href;

    try {
        const parsed = new URL(href, globalThis.location?.origin ?? 'http://localhost');
        if (parsed.pathname === '/dashboard' || parsed.pathname.startsWith('/dashboard/')) {
            return `${parsed.pathname}${parsed.search}${parsed.hash}`;
        }
    } catch {
        return href;
    }

    return href;
};

/**
 * Presentation-only contract consumed by DashboardSidebar.
 *
 * Authorization happens before navigation reaches the shell. The shell never
 * infers a role or filters an item; callers provide the already-approved list.
 */
export const createNavigationItem = ({ key, label, href, icon = null, current = false }) =>
    Object.freeze({
        kind: 'link',
        key,
        label,
        href: normalizeDashboardHref(href),
        icon,
        current: Boolean(current),
    });

export const createNavigationGroup = ({ key, label, items = [] }) =>
    Object.freeze({
        kind: 'group',
        key,
        label,
        items: freezeItems(items),
    });

export const createDashboardNavigation = (entries = []) => {
    const normalized = entries
        .filter((entry) => supportedNavigationKinds.has(entry?.kind))
        .map((entry) =>
            entry.kind === 'group'
                ? {
                      ...entry,
                      items: freezeItems(
                          (entry.items ?? []).map((item) => ({
                              ...item,
                              href: normalizeDashboardHref(item.href),
                          })),
                      ),
                  }
                : { ...entry, href: normalizeDashboardHref(entry.href) },
        );

    return freezeItems(normalized);
};

export const emptyDashboardNavigation = Object.freeze([]);
