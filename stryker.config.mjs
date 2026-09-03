// @ts-check

/** @type {import("@stryker-mutator/api/core").PartialStrykerOptions} */
const config = {
    ignorePatterns: ["/runtime", "/artifacts"],
    mutate: [
        "resources/src/core/clipboard.js",
        "resources/src/core/deep-links.js",
        "resources/src/core/dropdown.js",
        "resources/src/core/features.js",
        "resources/src/core/tabs.js",
        "resources/src/toolbar/brand.js",
        "resources/src/toolbar/focus.js",
        "resources/src/toolbar/icons.js",
        "resources/src/toolbar/loading.js",
        "resources/src/toolbar/panel.js",
        "resources/src/toolbar/position.js",
        "resources/src/toolbar/url.js",
    ],
    testRunner: "vitest",
    vitest: {
        configFile: "vitest.config.js",
    },
    concurrency: 4,
    reporters: ["clear-text", "progress", "html"],
    thresholds: {
        high: 100,
        low: 100,
        break: 100,
    },
};

export default config;
