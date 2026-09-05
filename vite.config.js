import { defineConfig } from "vite";
import { utimes } from "node:fs/promises";
import { resolve, dirname } from "node:path";
import { fileURLToPath } from "node:url";

const here = dirname(fileURLToPath(import.meta.url));
const assetRoot = resolve(here, "resources/assets");

export default defineConfig({
  root: here,
  base: "",
  plugins: [
    {
      name: "refresh-directory-publication-key",
      apply: "build",
      async closeBundle(error) {
        if (error) {
          return;
        }

        // Yii2 keys published directories by their root mtime, while Vite only
        // rewrites nested dist files. Refresh the root after a successful build.
        var now = new Date();

        await utimes(assetRoot, now, now);
      },
    },
  ],
  build: {
    outDir: "resources/assets/dist",
    emptyOutDir: true,
    cssCodeSplit: true,
    target: "es2022",
    minify: "oxc",
    rolldownOptions: {
      input: {
        debug: resolve(here, "resources/src/core/debug.js"),
        toolbar: resolve(here, "resources/src/toolbar/index.js"),
      },
      output: {
        entryFileNames: "js/[name].min.js",
        chunkFileNames: "js/[name].min.js",
        assetFileNames: (info) => {
          const name = info.names?.[0] ?? info.name ?? "";

          if (name.endsWith(".css")) {
            const base = name.replace(/^css-/, "").replace(/\.css$/, "");

            return `css/${base}.min.css`;
          }
          if (/\.(woff2?|ttf)$/.test(name)) {
            return "fonts/[name][extname]";
          }

          return "[ext]/[name].[ext]";
        },
        manualChunks: undefined,
      },
    },
  },
});
