// @ts-check

/** @type {import("@stryker-mutator/api/core").PartialStrykerOptions} */
const config = {
    mutate: [
        "resources/src/toolbar/brand.js",
        "resources/src/toolbar/icons.js",
        "resources/src/toolbar/loading.js",
        "resources/src/toolbar/panel.js",
        "resources/src/toolbar/position.js",
    ],
    testRunner: "command",
    commandRunner: {
        command: "node --test resources/tests/toolbar-runtime.test.js",
    },
    coverageAnalysis: "off",
    concurrency: 4,
    reporters: ["clear-text", "progress", "html"],
    thresholds: {
        high: 100,
        low: 100,
        break: 100,
    },
};

export default config;
