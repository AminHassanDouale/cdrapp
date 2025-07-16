/** @type {import('tailwindcss').Config} */
export default {
    darkMode: 'class',
    content: [
        // You will probably also need these lines
        "./resources/**/**/*.blade.php",
        "./resources/**/**/*.js",
        "./app/View/Components/**/**/*.php",
        "./app/Livewire/**/**/*.php",

        // Add mary
        "./vendor/robsontenorio/mary/src/View/Components/**/*.php",

        // Laravel built in pagination
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',

    ],
    theme: {
        extend: {},
    },
    safelist: [{
        pattern: /badge-|(bg-primary|bg-success|bg-info|bg-error|bg-warning|bg-neutral|bg-purple|bg-yellow)/
    }],
    // Add daisyUI
    plugins: [require("daisyui")],

    // Change theme primary color (TODO: better way?)
    daisyui: {
        themes: [
            {
                light: {
                    ...require("daisyui/src/theming/themes")["light"],
                    primary: '#9b3bf5'
                },
                dark: {
                    ...require("daisyui/src/theming/themes")["dark"],
                    primary: '#9b3bf5'
                }
            }
        ]
    }
}
