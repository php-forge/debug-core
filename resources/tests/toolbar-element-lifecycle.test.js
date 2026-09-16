// @vitest-environment jsdom
import assert from "node:assert/strict";
import { test, vi } from "vitest";

import { toolbars } from "../src/toolbar/state.js";
import {
  createToolbar,
  installLocalStorage,
  installMatchMedia,
  removeMatchMedia,
  renderToolbar,
  toolbarPayload,
} from "./toolbar-element-harness.js";

installLocalStorage({ "yii-debug-toolbar-expanded": "1" });

function payload() {
  return toolbarPayload({
    items: [
      { extension: true, id: "queue", items: [{ value: "0" }], title: "Queue" },
      {
        id: "db",
        items: [{ value: "3" }],
        title: "Database",
        url: "/debug/db",
      },
    ],
  });
}

test("connecting registers the toolbar and watching the theme is idempotent", () => {
  var media = installMatchMedia();
  var element = renderToolbar(payload());
  var observer = element.themeObserver;

  assert.equal(
    toolbars.indexOf(element),
    0,
    "Toolbar must be registered once.",
  );
  assert.equal(
    element.systemThemeQuery,
    media,
    "System query must be retained.",
  );
  assert.equal(
    media.listeners.length,
    1,
    "System query must be observed once.",
  );

  element.watchTheme();

  assert.equal(
    element.themeObserver,
    observer,
    "Observer must not be rebuilt.",
  );
  assert.equal(
    media.listeners.length,
    1,
    "System query must not be observed twice.",
  );

  element.remove();

  assert.equal(toolbars.indexOf(element), -1, "Toolbar must be unregistered.");
  assert.equal(element.themeObserver, null, "Observer must be released.");
  assert.equal(
    element.systemThemeQuery,
    null,
    "System query must be released.",
  );
  assert.equal(
    media.listeners.length,
    0,
    "System query listener must be removed.",
  );
  assert.equal(
    element.boundThemeMessage,
    null,
    "Message listener must be released.",
  );
});

test("reconnecting an already registered toolbar does not register it twice", () => {
  vi.useFakeTimers();
  installMatchMedia();

  var element = renderToolbar(payload());

  try {
    element.connectedCallback();

    assert.equal(
      toolbars.filter(function (candidate) {
        return candidate === element;
      }).length,
      1,
      "Registry must hold a single entry.",
    );
  } finally {
    element.remove();
    vi.useRealTimers();
  }
});

test("disconnecting a toolbar that was never connected is inert", () => {
  installMatchMedia();

  var element = createToolbar();

  element.disconnectedCallback();

  assert.equal(toolbars.indexOf(element), -1, "Registry must stay empty.");
  assert.equal(element.themeObserver, null, "No observer may be released.");
  assert.equal(element.resizing, false, "No drag may be in flight.");
});

test("the host theme control is re-evaluated after the page settles", () => {
  vi.useFakeTimers();
  installMatchMedia();

  var element = renderToolbar(payload());
  var refreshes = 0;

  try {
    element.refreshTheme = function () {
      refreshes += 1;
    };

    window.dispatchEvent(new window.Event("load"));

    assert.equal(refreshes, 1, "The load event must re-evaluate the host.");

    vi.advanceTimersByTime(1500);

    assert.equal(
      refreshes,
      2,
      "The settling checkpoint must re-evaluate the host.",
    );

    assert.equal(
      element.themeRefreshTimer !== null,
      true,
      "Timer must be recorded.",
    );
  } finally {
    element.remove();
    vi.useRealTimers();
  }

  assert.equal(
    element.themeRefreshTimer,
    null,
    "Timer must be cleared on disconnect.",
  );
});

test("a legacy media query is observed and released through the deprecated API", () => {
  var media = installMatchMedia({ legacy: true });
  var element = renderToolbar(payload());

  assert.equal(media.listeners.length, 1, "Legacy listener must be attached.");

  element.remove();

  assert.equal(media.listeners.length, 0, "Legacy listener must be detached.");
});

test("a media query without listener support is tolerated", () => {
  var media = installMatchMedia({ inert: true });
  var element = renderToolbar(payload());

  assert.equal(
    element.systemThemeQuery,
    media,
    "System query must still be retained.",
  );

  element.remove();

  assert.equal(
    element.systemThemeQuery,
    null,
    "System query must still be released.",
  );
});

test("a host without media queries or mutation observers is tolerated", () => {
  var observer = window.MutationObserver;

  removeMatchMedia();
  delete window.MutationObserver;

  var element = renderToolbar(payload());

  try {
    assert.equal(element.themeObserver, null, "No observer may be built.");
    assert.equal(
      element.systemThemeQuery,
      null,
      "No system query may be built.",
    );
  } finally {
    window.MutationObserver = observer;
    installMatchMedia();
  }

  element.remove();
});

test("a document without a body is observed on its root only", () => {
  installMatchMedia();

  var element = createToolbar();

  Object.defineProperty(document, "body", { configurable: true, value: null });

  try {
    element.watchTheme();

    assert.ok(element.themeObserver, "The root must still be observed.");
  } finally {
    delete document.body;
  }

  element.disconnectedCallback();

  assert.equal(element.themeObserver, null, "Observer must be released.");
});

test("the document pointer listener follows the element lifecycle", () => {
  installMatchMedia();

  var element = renderToolbar(payload());

  element.shadowRoot.querySelector(".extensions-toggle").click();

  assert.equal(element.extensionsOpen, true, "Menu must be open.");

  document.dispatchEvent(
    new window.MouseEvent("pointerdown", { bubbles: true, composed: true }),
  );

  assert.equal(
    element.extensionsOpen,
    false,
    "An attached listener must dismiss the menu.",
  );

  element.remove();
  element.extensionsOpen = true;
  document.dispatchEvent(
    new window.MouseEvent("pointerdown", { bubbles: true, composed: true }),
  );

  assert.equal(
    element.extensionsOpen,
    true,
    "A detached listener must leave the menu alone.",
  );
});

test("disconnecting during a drag stops the resize", () => {
  installMatchMedia();

  var element = renderToolbar(payload());

  element.openPanel("/debug/db");
  element.shadowRoot
    .querySelector(".resize-handle")
    .dispatchEvent(
      new window.MouseEvent("pointerdown", { bubbles: true, cancelable: true }),
    );

  assert.equal(element.resizing, true, "Drag must be in flight.");

  element.remove();

  assert.equal(element.resizing, false, "Drag must be abandoned.");

  document.dispatchEvent(
    new window.MouseEvent("pointermove", { clientY: 100 }),
  );

  assert.equal(
    element.style.getPropertyValue("--yii-debug-toolbar-drawer-height"),
    "50vh",
    "A detached toolbar must not be resized.",
  );
});
