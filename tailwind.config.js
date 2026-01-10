import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
    ],

    theme: {
        extend: {
            fontFamily: {
                sans: ['Figtree', ...defaultTheme.fontFamily.sans],
            },
            colors: {
                brand: {
                    DEFAULT: '#1E7CCF',
                    hover: '#1866AD',
                    active: '#14578F',
                    soft: '#E9F2FB',
                },
                surface: {
                    page: '#F8FAFC',
                    DEFAULT: '#FFFFFF',
                    alt: '#F1F5F9',
                },
                border: {
                    light: '#E2E8F0',
                    medium: '#CBD5E1',
                },
                ink: {
                    heading: '#0F172A',
                    body: '#334155',
                    muted: '#64748B',
                    disabled: '#94A3B8',
                },
                status: {
                    success: {
                        DEFAULT: '#16A34A',
                        light: '#DCFCE7',
                    },
                    warning: {
                        DEFAULT: '#F59E0B',
                        light: '#FEF3C7',
                    },
                    error: {
                        DEFAULT: '#DC2626',
                        light: '#FEE2E2',
                    },
                    info: {
                        DEFAULT: '#0EA5E9',
                        light: '#E0F2FE',
                    },
                },
                accent: {
                    purple: '#7C3AED',
                    teal: '#14B8A6',
                    orange: '#FB923C',
                },
            },
        },
    },

    plugins: [forms],
};
