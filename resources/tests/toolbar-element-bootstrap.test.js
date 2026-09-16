// @vitest-environment jsdom
import assert from "node:assert/strict";
import { test, vi } from "vitest";

const tagName = "yii-debug-toolbar";

test("the entry point defines the element and installs AJAX tracking", async () => {
  var browserOpen = window.XMLHttpRequest.prototype.open;

  await import("../src/toolbar/index.js");

  const { YiiDebugToolbar } = await import("../src/toolbar/element.js");

  assert.equal(
    window.customElements.get(tagName),
    YiiDebugToolbar,
    "Registry must hold the toolbar constructor.",
  );
  assert.equal(
    window.__yiiDebugToolbarTracking,
    true,
    "Tracking flag must be raised.",
  );
  assert.notEqual(
    window.XMLHttpRequest.prototype.open,
    browserOpen,
    "`open` must be replaced by the tracker.",
  );
});

test("a second bootstrap keeps the first registration and the installed hooks", async () => {
  var registered = window.customElements.get(tagName);
  var trackedOpen = window.XMLHttpRequest.prototype.open;

  vi.resetModules();

  await import("../src/toolbar/index.js");

  const { YiiDebugToolbar } = await import("../src/toolbar/element.js");

  assert.equal(
    window.customElements.get(tagName),
    registered,
    "Registry entry must survive the duplicate bootstrap.",
  );
  assert.notEqual(
    window.customElements.get(tagName),
    YiiDebugToolbar,
    "Reloaded constructor must not replace the registered one.",
  );
  assert.equal(
    window.XMLHttpRequest.prototype.open,
    trackedOpen,
    "`open` must not be hooked twice.",
  );
});

test("a host without custom element support still installs AJAX tracking", async () => {
  var registry = window.customElements;
  var trackedOpen = window.XMLHttpRequest.prototype.open;

  vi.resetModules();
  delete window.customElements;

  try {
    await import("../src/toolbar/index.js");

    assert.equal(
      window.customElements,
      undefined,
      "Registry must stay absent.",
    );
    assert.equal(
      window.XMLHttpRequest.prototype.open,
      trackedOpen,
      "`open` must not be hooked twice.",
    );
  } finally {
    window.customElements = registry;
  }
});
