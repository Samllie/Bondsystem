import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        './resources/js/**/*.jsx',
    ],

    theme: {
        extend: {
            fontFamily: {
                sans: ['Figtree', ...defaultTheme.fontFamily.sans],
                serif: ['Georgia', 'Times New Roman', ...defaultTheme.fontFamily.serif],
            },
            colors: {
                sterling: {
                    green: {
                        DEFAULT: '#1A6333',
                        dark: '#134d28',
                        darker: '#0f3d1f',
                        light: '#22804a',
                        50: '#f0f7f3',
                    },
                    gold: {
                        DEFAULT: '#D99E1A',
                        light: '#e5b03d',
                        dark: '#b88416',
                        50: '#fdf8ee',
                    },
                },
            },
        },
    },

    plugins: [
        forms,
    ],
};
