import { defineConfig } from "@playwright/test";

import {
  fixtureApplicationPaths,
  fixtureContract,
} from "./e2e/support/environment.js";

const startServers = process.env.DEBUG_UI_START_SERVERS === "1";
const executablePath = process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE;
const applicationPaths = fixtureApplicationPaths();
const webServer = startServers
  ? fixtureContract.apps.map((app, index) => {
      const url = new URL(app.baseURL);

      return {
        command: `php -S ${url.hostname}:${url.port} -t public public/router.php`,
        cwd: applicationPaths[index],
        url: app.baseURL,
        reuseExistingServer: true,
        timeout: 30_000,
        stdout: "pipe",
        stderr: "pipe",
      };
    })
  : undefined;

export default defineConfig({
  testDir: "./e2e",
  testMatch: /.*\.spec\.js/,
  globalSetup: "./e2e/global-setup.js",
  outputDir: "./artifacts/playwright/results",
  snapshotPathTemplate: "{testDir}/snapshots/{projectName}/{arg}{ext}",
  fullyParallel: false,
  forbidOnly: Boolean(process.env.CI),
  retries: process.env.CI ? 1 : 0,
  workers: process.env.CI ? 2 : 4,
  timeout: 45_000,
  expect: {
    timeout: 7_500,
    toHaveScreenshot: {
      animations: "disabled",
      caret: "hide",
      maxDiffPixelRatio: 0.003,
      scale: "css",
    },
  },
  reporter: [
    ["list"],
    ["html", { outputFolder: "artifacts/playwright/report", open: "never" }],
  ],
  use: {
    browserName: "chromium",
    colorScheme: "light",
    locale: "en-US",
    timezoneId: "UTC",
    reducedMotion: "reduce",
    trace: "retain-on-failure",
    screenshot: "only-on-failure",
    video: executablePath ? "off" : "retain-on-failure",
    launchOptions: executablePath ? { executablePath } : {},
  },
  projects: [
    {
      name: "desktop-1440",
      use: { viewport: { width: 1440, height: 900 } },
    },
    {
      name: "tablet-1024",
      use: { viewport: { width: 1024, height: 768 } },
    },
    {
      name: "mobile-390",
      use: { viewport: { width: 390, height: 844 }, isMobile: true },
    },
  ],
  webServer,
});
