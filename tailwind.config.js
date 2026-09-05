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
                sans: ['DM Sans', 'Figtree', ...defaultTheme.fontFamily.sans],
                display: ['Lora', 'ui-serif', 'Georgia', 'serif'],
            },

            colors: {
                primary: {
                    DEFAULT: '#2563eb',
                    hover: '#1d4ed8',
                    soft: '#eff6ff',
                },
                success: {
                    DEFAULT: '#16a34a',
                    soft: '#f0fdf4',
                },
                warning: {
                    DEFAULT: '#d97706',
                    soft: '#fffbeb',
                },
                danger: {
                    DEFAULT: '#dc2626',
                    soft: '#fef2f2',
                },
                info: {
                    DEFAULT: '#0284c7',
                    soft: '#f0f9ff',
                },
                neutral: {
                    DEFAULT: '#64748b',
                    soft: '#f1f5f9',
                },
            },
        },
    },

    plugins: [forms],
};
