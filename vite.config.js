import { defineConfig, loadEnv } from 'vite';
import laravel from 'laravel-vite-plugin';
import react from '@vitejs/plugin-react';
import path from 'path';

export default defineConfig(({ mode }) => {
    const env = loadEnv(mode, process.cwd(), '');
    const devHost = env.VITE_DEV_HOST || new URL(env.APP_URL || 'http://sici-bonds.local').hostname;

    return {
        resolve: {
            alias: {
                '@': path.resolve(__dirname, 'resources/js'),
            },
        },
        server: {
            // Bind locally — avoids ENOTFOUND if the hosts entry is not set up yet.
            host: '127.0.0.1',
            port: 5173,
            strictPort: true,
            hmr: {
                // Browser loads the app from APP_URL; HMR must use that hostname.
                host: devHost,
            },
        },
        plugins: [
            laravel({
                input: 'resources/js/app.jsx',
                refresh: true,
            }),
            react(),
        ],
    };
});
