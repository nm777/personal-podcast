import { useCallback, useEffect, useState } from 'react';

export type ColorScheme = 'default' | 'ocean' | 'forest' | 'ember';

const VALID_SCHEMES: ColorScheme[] = ['default', 'ocean', 'forest', 'ember'];

const isValidScheme = (value: string | null): value is ColorScheme =>
    value !== null && VALID_SCHEMES.includes(value as ColorScheme);

const getStoredScheme = (): ColorScheme => {
    const stored = localStorage.getItem('color-scheme');
    return isValidScheme(stored) ? stored : 'default';
};

const setCookie = (name: string, value: string, days = 365) => {
    if (typeof document === 'undefined') {
        return;
    }

    const maxAge = days * 24 * 60 * 60;
    document.cookie = `${name}=${value};path=/;max-age=${maxAge};SameSite=Lax`;
};

const applyScheme = (scheme: ColorScheme) => {
    if (scheme === 'default') {
        document.documentElement.removeAttribute('data-theme');
    } else {
        document.documentElement.setAttribute('data-theme', scheme);
    }
};

export function initializeColorScheme() {
    applyScheme(getStoredScheme());
}

export function useColorScheme() {
    const [colorScheme, setColorScheme] = useState<ColorScheme>('default');

    const updateColorScheme = useCallback((scheme: ColorScheme) => {
        setColorScheme(scheme);
        localStorage.setItem('color-scheme', scheme);
        setCookie('color-scheme', scheme);
        applyScheme(scheme);
    }, []);

    useEffect(() => {
        updateColorScheme(getStoredScheme());
    }, [updateColorScheme]);

    return { colorScheme, updateColorScheme } as const;
}
