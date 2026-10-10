import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import { local } from 'laravel-vite-plugin/fonts';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js'],
            refresh: true,
            fonts: [
                // Bundle the font so Docker builds do not depend on a remote font server.
                local('Instrument Sans', {
                    variants: [400, 500, 600].map((weight) => ({
                        src: `resources/fonts/instrument-sans-${weight}-normal.woff2`,
                        weight,
                        style: 'normal',
                    })),
                    optimizedFallbacks: false,
                }),
            ],
        }),
        tailwindcss(),
    ],
    server: {
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});
