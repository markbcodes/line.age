import { dirname, resolve } from 'node:path'
import { fileURLToPath } from 'node:url'
import tailwindcss from '@tailwindcss/vite'
import { defineConfig } from 'vite'

const rootDir = dirname(fileURLToPath(import.meta.url))

export default defineConfig({
  // Fonts and images the CSS uses are linked from app.css's own folder. WordPress serves the build from the
  // theme's assets/build/, so the default '/' would point them at the site root, where they aren't.
  base: './',
  plugins: [tailwindcss()],
  build: {
    outDir: 'assets/build',
    emptyOutDir: true,
    cssCodeSplit: false,
    rollupOptions: {
      input: resolve(rootDir, 'resources/js/app.js'),
      output: {
        entryFileNames: 'app.js',
        assetFileNames: (assetInfo) => {
          const names = assetInfo.names ?? (assetInfo.name ? [assetInfo.name] : [])
          if (names.some((name) => name.endsWith('.css'))) {
            return 'app.css'
          }
          // Fonts keep their names, so functions.php can preload the ones the first screen uses.
          if (names.some((name) => /\.woff2?$/.test(name))) {
            return 'fonts/[name][extname]'
          }
          return '[name]-[hash][extname]'
        }
      }
    }
  }
})
