import { defineConfig } from 'tsup';

export default defineConfig([
  {
    entry: { index: 'src/index.ts' },
    format: ['esm', 'cjs'],
    dts: true,
    clean: true,
  },
  {
    // Next.js App Router needs the directive or <Pulso /> can't live in a server layout
    entry: { react: 'src/react.ts' },
    format: ['esm', 'cjs'],
    dts: true,
    external: ['react'],
    banner: { js: "'use client';" },
  },
  {
    // <script> tag build for non-React sites
    entry: { pulso: 'src/script.ts' },
    format: ['iife'],
    minify: true,
    outExtension: () => ({ js: '.js' }),
  },
]);
