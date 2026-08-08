const templateThemes = Object.freeze({
    SYIFA_ESSENTIAL: Object.freeze({
        primary: '#176B50',
        primaryHover: '#10543F',
        primaryActive: '#0C4434',
        secondary: '#E8F0EA',
        onSecondary: '#18221F',
        border: '#CBD7D1',
    }),
    SYIFA_CARE: Object.freeze({
        primary: '#0B2A1F',
        primaryHover: '#0F3D2E',
        primaryActive: '#061A12',
        secondary: '#DDF0C3',
        onSecondary: '#122019',
        border: '#9AB5A3',
    }),
    SYIFA_DENTAL: Object.freeze({
        primary: '#0F6E96',
        primaryHover: '#0B5675',
        primaryActive: '#094560',
        secondary: '#E7F3F7',
        onSecondary: '#102D36',
        border: '#BFD2D9',
    }),
    SYIFA_AESTHETIC: Object.freeze({
        primary: '#302824',
        primaryHover: '#241E1B',
        primaryActive: '#1A1512',
        secondary: '#ECE3DC',
        onSecondary: '#302824',
        border: '#D8CBC2',
    }),
    SYIFA_SPECIALIST: Object.freeze({
        primary: '#1D2C3B',
        primaryHover: '#16222E',
        primaryActive: '#0F171F',
        secondary: '#E2E8EE',
        onSecondary: '#1D2C3B',
        border: '#AEBCCA',
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
