import type { Ref } from 'vue';
import { ref } from 'vue';

/**
 * Theme/appearance for future use (e.g. named themes: default, ocean).
 * Dark mode is removed; swap themes by changing CSS variables or a theme class.
 */
export type Theme = 'default';

export type UseAppearanceReturn = {
    theme: Ref<Theme>;
};

const theme = ref<Theme>('default');

export function useAppearance(): UseAppearanceReturn {
    return {
        theme,
    };
}
