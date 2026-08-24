import { readFileSync } from "node:fs";

import { expect, test } from "@playwright/test";

import {
  collectRuntimeDiagnostics,
  expectNoRuntimeDiagnostics,
  openDebugPage,
} from "./support/debug-ui.js";
import { debugApps, panelEntries } from "./support/environment.js";

const budget = JSON.parse(
  readFileSync(
    new URL("../tools/quality/performance-budget.json", import.meta.url),
    "utf8",
  ),
).livePanel;

for (const app of debugApps()) {
  test(`${app.name} dense panels stay inside the live performance envelope`, async ({
    page,
  }, testInfo) => {
    const diagnostics = collectRuntimeDiagnostics(page);
    const results = [];

    for (const entry of panelEntries) {
      await openDebugPage(page, app, entry, {
        state: "dense",
        theme: "light",
      });

      const metrics = await page.evaluate(() => {
        const navigation = performance.getEntriesByType("navigation")[0];

        return {
          domContentLoadedMs: navigation
            ? navigation.domContentLoadedEventEnd - navigation.startTime
            : null,
          domNodes: document.getElementsByTagName("*").length,
        };
      });

      results.push({ panel: entry.id, ...metrics });
      expect(
        metrics.domContentLoadedMs,
        `${entry.id} must expose Navigation Timing`,
      ).not.toBeNull();
      expect(metrics.domContentLoadedMs).toBeLessThanOrEqual(
        budget.maxDomContentLoadedMs,
      );
      expect(metrics.domNodes).toBeLessThanOrEqual(budget.maxDomNodes);
      await expectNoRuntimeDiagnostics(
        diagnostics.splice(0),
        `${app.name} performance ${entry.id}`,
      );
    }

    await testInfo.attach(`${app.name}-live-performance.json`, {
      body: Buffer.from(JSON.stringify(results, null, 2)),
      contentType: "application/json",
    });
  });
}
