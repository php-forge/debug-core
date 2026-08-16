import { defineConfig } from "vite";
import { resolve, dirname } from "node:path";
import { fileURLToPath } from "node:url";

const here = dirname(fileURLToPath(import.meta.url));

export default defineConfig({
  root: here,
  base: "",
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
