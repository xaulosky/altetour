import { defineConfig } from 'astro/config';
import { existsSync } from 'node:fs';
if (existsSync('.env')) process.loadEnvFile('.env');
// SITE_URL se define al compilar. No contiene credenciales ni claves privadas.
export default defineConfig({
  site: process.env.SITE_URL || 'https://example.com',
  output: 'static',
  trailingSlash: 'always',
  build: { format: 'directory' },
  vite: { server: { proxy: { '/api': 'http://127.0.0.1:8766', '/admin': 'http://127.0.0.1:8766' } } }
});
