const freezeContracts = (contracts) =>
    Object.freeze(contracts.map((contract) => Object.freeze({ ...contract })));

export const createDashboardSummaries = (summaries = []) => freezeContracts(summaries);

export const createDashboardQuickActions = (actions = []) => freezeContracts(actions);

export const createDashboardActivity = (activity = []) => freezeContracts(activity);
