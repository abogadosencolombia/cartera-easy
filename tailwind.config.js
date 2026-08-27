import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        './resources/js/**/*.vue',
    ],

    theme: {
        extend: {
            fontFamily: {
                sans: ['DM Sans', ...defaultTheme.fontFamily.sans],
                display: ['Manrope', ...defaultTheme.fontFamily.sans],
            },
            colors: {
                brand: {
                    50: '#effcf9',
                    100: '#d7f7ef',
                    200: '#b3eddf',
                    300: '#7dddc9',
                    400: '#43c4ae',
                    500: '#24a894',
                    600: '#188274',
                    700: '#176c62',
                    800: '#17574f',
                    900: '#174841',
                    950: '#082b28',
                },
            },
            keyframes: {
                blink: {
                    '0%, 100%': { opacity: '1' },
                    '50%': { opacity: '0.5' },
                }
            },
            animation: {
                blink: 'blink 2s ease-in-out infinite',
            }
        },
    },

    plugins: [forms],
};
