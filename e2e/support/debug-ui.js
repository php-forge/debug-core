import { expect } from "@playwright/test";

import { debugPageURL } from "./environment.js";

const ignoredRequestFailure = /(?:ERR_ABORTED|NS_BINDING_ABORTED)/i;

export function collectRuntimeDiagnostics(page) {
  const diagnostics = [];

  page.on("console", (message) => {
    if (message.type() === "error") {
      const location = message.location();
      const source = location.url
        ? ` (${location.url}:${location.lineNumber ?? 0})`
        : "";

      diagnostics.push(`console${source}: ${message.text()}`);
    }
  });
  page.on("pageerror", (error) => {
    diagnostics.push(`pageerror: ${error.message}`);
  });
  page.on("requestfailed", (request) => {
    const resourceType = request.resourceType();
    const failure = request.failure()?.errorText ?? "unknown failure";

    if (
      ["document", "script", "stylesheet", "font"].includes(resourceType) &&
      !ignoredRequestFailure.test(failure)
    ) {
      diagnostics.push(
        `requestfailed (${resourceType}): ${request.url()} — ${failure}`,
      );
    }
  });

  return diagnostics;
}

export async function expectNoRuntimeDiagnostics(diagnostics, context) {
  await expect(
    diagnostics,
    `${context} must not emit console, page, or critical resource errors`,
  ).toEqual([]);
}

export async function waitForStableUI(page) {
  await page.evaluate(async () => {
    if (document.fonts?.ready) {
      await document.fonts.ready;
    }

    const animations = document.getAnimations?.() ?? [];

    await Promise.race([
      Promise.allSettled(animations.map((animation) => animation.finished)),
      new Promise((resolve) => window.setTimeout(resolve, 250)),
    ]);

    await new Promise((resolve) => {
      requestAnimationFrame(() => requestAnimationFrame(resolve));
    });
  });
}

export async function freezeVisualMotion(page) {
  await page.addStyleTag({
    content: `
      *, *::before, *::after {
        animation-delay: 0s !important;
        animation-duration: 0s !important;
        caret-color: transparent !important;
        scroll-behavior: auto !important;
        transition-delay: 0s !important;
        transition-duration: 0s !important;
      }
    `,
  });
}

export async function openDebugPage(page, app, entry, options = {}) {
  const url = debugPageURL(app, entry, options);
  const response = await page.goto(url, { waitUntil: "domcontentloaded" });

  expect(
    response,
    `Navigation to ${url} must return a response`,
  ).not.toBeNull();
  expect(response?.ok(), `Navigation to ${url} must succeed`).toBe(true);
  await expect(page.locator(".yii-debug-page").first()).toBeVisible();
  await waitForStableUI(page);

  return url;
}

export async function expectDebuggerLayout(page, entry, theme) {
  await expect(page.locator("html")).toHaveAttribute(
    "data-yii-debug-theme",
    theme,
  );
  await expect(page.locator(".yii-debug-page").first()).toBeVisible();
  await expect(page.locator(".yii-debug-page").first()).not.toBeEmpty();

  const overflow = await page.evaluate(() => ({
    clientWidth: document.documentElement.clientWidth,
    scrollWidth: document.documentElement.scrollWidth,
  }));

  expect(
    overflow.scrollWidth,
    `${entry.id} must not overflow the document viewport horizontally`,
  ).toBeLessThanOrEqual(overflow.clientWidth + 1);
}

export async function waitForToolbar(page) {
  const toolbar = page.locator("yii-debug-toolbar");

  await expect(toolbar).toBeAttached();
  await page.waitForFunction(() => {
    const element = document.querySelector("yii-debug-toolbar");
    const root = element?.shadowRoot;

    return Boolean(
      root?.querySelector(".toolbar") &&
      (element.data || root.querySelector(".error-message")),
    );
  });
  await expect(toolbar.locator(".error-message")).toHaveCount(0);

  return toolbar;
}

export async function expandToolbar(toolbar) {
  const expand = toolbar.getByRole("button", { name: "Expand debug toolbar" });

  if ((await expand.count()) > 0) {
    await expand.click();
  }

  await expect(
    toolbar.getByRole("button", { name: "Collapse toolbar" }),
  ).toBeVisible();
}

export function seriousAxeViolations(results) {
  return results.violations.filter((violation) =>
    ["serious", "critical"].includes(violation.impact ?? ""),
  );
}

export function formatAxeViolations(violations) {
  return violations
    .map((violation) => {
      const targets = violation.nodes
        .flatMap((node) => node.target)
        .map((target) => String(target))
        .join(", ");

      return `${violation.id} [${violation.impact}]: ${violation.help} (${targets})`;
    })
    .join("\n");
}
