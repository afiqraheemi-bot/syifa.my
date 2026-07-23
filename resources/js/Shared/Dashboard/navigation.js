const supportedNavigationKinds = new Set(['link', 'group']);

const freezeItems = (items) => Object.freeze(items.map((item) => Object.freeze({ ...item })));

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
        href,
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
    const normalized = entries.filter((entry) => supportedNavigationKinds.has(entry?.kind));

    return freezeItems(normalized);
};

export const emptyDashboardNavigation = Object.freeze([]);
