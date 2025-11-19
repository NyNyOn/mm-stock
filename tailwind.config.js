import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        './resources/js/**/*.js',
    ],

    // ✅ --- ADD THIS SAFELIST SECTION --- ✅
    safelist: [
        {
            // เราใช้ Regular Expression เพื่อบอกให้เก็บ class สี gradient ทั้งหมดไว้
            pattern: /^(from|via|to|ring|shadow)-(yellow|orange|red|purple|pink|blue|teal|green|indigo)-(300|400|500|300\/50)$/,
        },
    ],
    // ✅ --- END SAFELIST SECTION --- ✅

    theme: {
        extend: {
            fontFamily: {
                sans: ['Figtree', ...defaultTheme.fontFamily.sans],
            },
            animation: {
                'spin-slow': 'spin 3s linear infinite',
            }
        },
    },

    plugins: [forms],
};
