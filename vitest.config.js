import { defineConfig } from "vitest/config";

export default defineConfig({
  test: {
    coverage: {
      provider: "v8",
      include: ["resources/src/**/*.js"],
      /**
       * Nothing is hidden from the report: every file matched by `include` is
       * measured, bootstraps included.
       */
      exclude: [],
      reporter: ["text"],
      reportsDirectory: "runtime/coverage-js",
      thresholds: {
        branches: 100,
        functions: 100,
        lines: 100,
      },
    },
    /**
     * `element.js` imports its shadow styles with `?inline`; without CSS
     * processing Vitest would hand it an empty string.
     */
    css: true,
    environment: "node",
    include: ["resources/tests/**/*.test.js"],
    isolate: true,
    pool: "threads",
  },
});
