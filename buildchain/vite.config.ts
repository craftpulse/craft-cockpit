import {defineConfig} from 'vite';
import checker from 'vite-plugin-checker';
import viteCompressionPlugin from 'vite-plugin-compression';
import * as path from 'path';

// https://vitejs.dev/config/
export default defineConfig(({command}) => ({
    base: command === 'serve' ? '' : '/dist/',
    build: {
        emptyOutDir: true,
        manifest: 'manifest.json',
        outDir: '../src/web/assets/dist',
        rollupOptions: {
            input: {
                'cockpit': 'src/js/Cockpit.js',
                'matchfields': 'src/js/MatchFields.js',
            },
        },
        sourcemap: true
    },
    plugins: [
        viteCompressionPlugin({
            filter: /\.(js|mjs|json|css|map)$/i
        }),
    ],
    resolve: {
        alias: [
            {find: '~', replacement: path.resolve(__dirname, './src')},
        ],
        preserveSymlinks: true,
    },
    server: {
        // Allow cross-origin requests -- https://github.com/vitejs/vite/security/advisories/GHSA-vg6x-rcgg-rjx6
        allowedHosts: true,
        cors: {
            origin: /https?:\/\/([A-Za-z0-9\-\.]+)?(localhost|\.local|\.ddev\.test|\.site)(?::\d+)?$/
        },
        fs: {
            strict: false
        },
        headers: {
            "Access-Control-Allow-Private-Network": "true",
        },
        host: '0.0.0.0',
        origin: 'http://localhost:' + process.env.DEV_PORT,
        port: parseInt(process.env.DEV_PORT),
        strictPort: true,
    }
}));
