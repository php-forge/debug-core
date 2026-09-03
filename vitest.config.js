import { defineConfig } from "vitest/config";

export default defineConfig({
  test: {
    coverage: {
      provider: "v8",
      include: ["resources/src/**/*.js"],
      exclude: [
        "resources/src/core/debug.js",
        "resources/src/toolbar/element.js",
        "resources/src/toolbar/index.js",
      ],
      reporter: ["text"],
      reportsDirectory: "runtime/coverage-js",
      thresholds: {
        branches: 100,
        functions: 100,
        lines: 100,
      },
    },
    environment: "node",
    include: ["resources/tests/**/*.test.js"],
    isolate: true,
    pool: "threads",
  },
});
