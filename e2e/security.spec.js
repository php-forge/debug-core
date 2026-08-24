import { readFileSync } from "node:fs";

import { expect, test } from "@playwright/test";

import {
  collectRuntimeDiagnostics,
  expectNoRuntimeDiagnostics,
  openDebugPage,
} from "./support/debug-ui.js";
import {
  debugApps,
  fixtureContract,
  fixtureSnapshotPath,
  panelEntries,
  standaloneEntries,
} from "./support/environment.js";

function capturedValuesForKey(node, target, values = []) {
  if (node === null || typeof node !== "object") {
    return values;
  }

  if (
    node.key === target &&
    node.value !== null &&
    typeof node.value === "object" &&
    Object.hasOwn(node.value, "value")
  ) {
    values.push(node.value.value);
  }

  for (const value of Object.values(node)) {
    capturedValuesForKey(value, target, values);
  }

  return values;
}

const requestPanel = panelEntries.find((entry) => entry.id === "request");
const comparisonPage = standaloneEntries.find(
  (entry) => entry.kind === "compare",
);
const security = fixtureContract.security;

if (!requestPanel || !comparisonPage || !security) {
  throw new Error("The security fixture contract is incomplete.");
}

for (const [appIndex, app] of debugApps().entries()) {
  test(`${app.name} never persists or renders synthetic sensitive sentinels`, async ({
    page,
  }) => {
    const snapshotPath = fixtureSnapshotPath(appIndex);
    const rawSnapshot = readFileSync(snapshotPath, "utf8");
    const snapshot = JSON.parse(rawSnapshot);

    for (const sentinel of [
      security.exactSentinel,
      security.prefixedSentinel,
    ]) {
      expect(
        rawSnapshot,
        `${snapshotPath} must not contain ${sentinel}`,
      ).not.toContain(sentinel);
    }

    for (const key of [security.exactKey, security.prefixedKey]) {
      expect(
        capturedValuesForKey(snapshot, key),
        `${key} must be persisted only as the documented placeholder`,
      ).toContain(security.placeholder);
    }

    expect(capturedValuesForKey(snapshot, security.controlKey)).toContain(
      security.controlValue,
    );

    const diagnostics = collectRuntimeDiagnostics(page);

    await openDebugPage(page, app, requestPanel, {
      state: "dense",
      theme: "light",
    });

    const html = await page.content();
    const body = page.locator("body");
    const bodyText = await body.innerText();

    for (const sentinel of [
      security.exactSentinel,
      security.prefixedSentinel,
    ]) {
      expect(html).not.toContain(sentinel);
      expect(bodyText).not.toContain(sentinel);
    }

    await expect(body).toContainText(security.exactKey);
    await expect(body).toContainText(security.prefixedKey);
    await expect(body).toContainText(security.placeholder);
    await expect(body).toContainText(security.controlValue);

    await openDebugPage(page, app, comparisonPage, { theme: "light" });

    const comparisonHtml = await page.content();
    const comparisonText = await page.locator("body").innerText();

    for (const sentinel of [
      security.exactSentinel,
      security.prefixedSentinel,
    ]) {
      expect(comparisonHtml).not.toContain(sentinel);
      expect(comparisonText).not.toContain(sentinel);
    }

    await expectNoRuntimeDiagnostics(
      diagnostics,
      `${app.name} redaction sentinel`,
    );
  });
}
