const completedStatuses = new Set(['completed', 'waived']);

const statusLabels = {
    not_ready: 'Waiting',
    ready: 'Ready',
    in_progress: 'In progress',
    blocked: 'Blocked',
    awaiting_clinic_owner: 'Waiting for Clinic Owner',
    awaiting_website_designer: 'Waiting for Website Designer',
    completed: 'Completed',
    waived: 'Waived',
    reopened: 'Reopened',
    cancelled: 'Cancelled',
};

const label = (value) =>
    String(value ?? '')
        .replaceAll('_', ' ')
        .replace(/\b\w/g, (character) => character.toUpperCase());

export function onboardingTaskKey(task) {
    return task?.key ?? task?.taskKey ?? '';
}

export function isOnboardingTaskComplete(task) {
    return completedStatuses.has(task?.status);
}

export function createOnboardingCheckpoints(tasks, destinations = {}) {
    const orderedTasks = Array.isArray(tasks) ? tasks : [];
    const currentIndex = orderedTasks.findIndex(
        (task) => !isOnboardingTaskComplete(task) && task.status !== 'cancelled',
    );

    return orderedTasks.map((task, index) => {
        const key = onboardingTaskKey(task) || task.id;
        const complete = isOnboardingTaskComplete(task);
        const state = complete ? 'complete' : index === currentIndex ? 'current' : 'upcoming';
        const responsibilityLabel = label(task.responsibility);
        const statusLabel = statusLabels[task.status] ?? label(task.status);

        return {
            ...task,
            key,
            label: task.title,
            complete,
            state,
            statusLabel,
            responsibilityLabel,
            description: `${responsibilityLabel} · ${statusLabel}`,
            detail:
                state === 'complete'
                    ? task.status === 'waived'
                        ? `Waived by an authorized Super Admin.`
                        : `Completed by ${responsibilityLabel}.`
                    : state === 'current'
                      ? `${statusLabel} · ${responsibilityLabel}.`
                      : `Waiting for the previous checkpoint · ${responsibilityLabel}.`,
            href: destinations[key] ?? null,
        };
    });
}
