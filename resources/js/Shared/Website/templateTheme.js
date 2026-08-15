const templateThemes = Object.freeze({
    SYIFA_ESSENTIAL: Object.freeze({
        primary: '#0F766E',
        primaryHover: '#0D625D',
        primaryActive: '#0B504C',
        secondary: '#E8F4F2',
        onSecondary: '#123B38',
        border: '#BAD5D1',
    }),
    SYIFA_CARE: Object.freeze({
        primary: '#15803D',
        primaryHover: '#116B33',
        primaryActive: '#0E5729',
        secondary: '#EDF7EE',
        onSecondary: '#173D25',
        border: '#BCD7C1',
    }),
    SYIFA_DENTAL: Object.freeze({
        primary: '#0369A1',
        primaryHover: '#025985',
        primaryActive: '#024A70',
        secondary: '#EAF4FA',
        onSecondary: '#123B54',
        border: '#B9D2E1',
    }),
    SYIFA_AESTHETIC: Object.freeze({
        primary: '#9D174D',
        primaryHover: '#831843',
        primaryActive: '#701A3D',
        secondary: '#F9EDF2',
        onSecondary: '#4D1D30',
        border: '#DDB8C7',
    }),
    SYIFA_SPECIALIST: Object.freeze({
        primary: '#1E3A8A',
        primaryHover: '#172F70',
        primaryActive: '#12245A',
        secondary: '#EDF1FA',
        onSecondary: '#172554',
        border: '#B9C4E0',
    }),
});

export function websiteTemplateThemeStyle(templateId) {
    const theme = templateThemes[templateId] ?? templateThemes.SYIFA_ESSENTIAL;

    return {
        '--website-theme-primary': theme.primary,
        '--website-theme-primary-hover': theme.primaryHover,
        '--website-theme-primary-active': theme.primaryActive,
        '--website-theme-secondary': theme.secondary,
        '--website-theme-on-secondary': theme.onSecondary,
        '--website-theme-border': theme.border,
    };
}
