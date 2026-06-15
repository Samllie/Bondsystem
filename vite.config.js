import { defineConfig, loadEnv } from 'vite';
import laravel from 'laravel-vite-plugin';
import react from '@vitejs/plugin-react';
import fs from 'fs';
import path from 'path';

export default defineConfig(({ mode }) => {
    const env = loadEnv(mode, process.cwd(), '');
    const appUrl = env.APP_URL || 'https://sici-bonds.local';
    const devHost = env.VITE_DEV_HOST || new URL(appUrl).hostname;
    const certDir = path.resolve(__dirname, 'storage/certs/local');
    const certPath = path.join(certDir, 'server.crt');
    const keyPath = path.join(certDir, 'server.key');
    const hasLocalCerts = fs.existsSync(certPath) && fs.existsSync(keyPath);
    const useHttps = appUrl.startsWith('https://') && hasLocalCerts;

    return {
        resolve: {
            alias: {
                '@': path.resolve(__dirname, 'resources/js'),
            },
        },
        server: {
            host: devHost,
            port: 5173,
            strictPort: true,
            https: useHttps
                ? {
                      cert: fs.readFileSync(certPath),
                      key: fs.readFileSync(keyPath),
                  }
                : false,
            hmr: {
                host: devHost,
                protocol: useHttps ? 'wss' : 'ws',
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
