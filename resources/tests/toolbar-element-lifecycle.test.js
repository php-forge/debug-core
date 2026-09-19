// @vitest-environment jsdom
import assert from "node:assert/strict";
import { afterEach, test, vi } from "vitest";

import { toolbars } from "../src/toolbar/state.js";
import {
  connectToolbar,
  createToolbar,
  installLocalStorage,
  installMatchMedia,
  installXmlHttpRequest,
  removeMatchMedia,
  renderToolbar,
  teardownToolbars,
  toolbarPayload,
} from "./toolbar-element-harness.js";

var mutationObserver = window.MutationObserver;

installLocalStorage({ "yii-debug-toolbar-expanded": "1" });

var transport = installXmlHttpRequest();

/**
 * Restores what a thrown assertion would leave behind: a connected toolbar, a
 * fake clock, the deleted `MutationObserver` and the removed media query. The
 * detached `createToolbar()` fixtures stay untouched, so the tests that drive
 * `disconnectedCallback()` themselves keep proving what it does.
 */
afterEach(() => {
  teardownToolbars();
  vi.useRealTimers();
  window.MutationObserver = mutationObserver;
  installMatchMedia();
});

function snapshot(tag) {
  return JSON.stringify({ items: [], tag: tag });
}

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
  var observer = element.themeController.observer;

  assert.equal(
    toolbars.indexOf(element),
    0,
    "Toolbar must be registered once.",
  );
  assert.equal(
    element.themeController.systemQuery,
    media,
    "System query must be retained.",
  );
  assert.equal(
    media.listeners.length,
    1,
    "System query must be observed once.",
  );

  element.themeController.watchTheme();

  assert.equal(
    element.themeController.observer,
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
  assert.equal(
    element.themeController.observer,
    null,
    "Observer must be released.",
  );
  assert.equal(
    element.themeController.systemQuery,
    null,
    "System query must be released.",
  );
  assert.equal(
    media.listeners.length,
    0,
    "System query listener must be removed.",
  );
  assert.equal(
    element.themeController.themeMessage,
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
  assert.equal(
    element.themeController.observer,
    null,
    "No observer may be released.",
  );
  assert.equal(element.resizing, false, "No drag may be in flight.");
});

test("the host theme control is re-evaluated after the page settles", () => {
  vi.useFakeTimers();
  installMatchMedia();

  var element = renderToolbar(payload());
  var refreshes = 0;

  try {
    element.themeController.refreshTheme = function () {
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
      element.themeController.refreshTimer !== null,
      true,
      "Timer must be recorded.",
    );
  } finally {
    element.remove();
    vi.useRealTimers();
  }

  assert.equal(
    element.themeController.refreshTimer,
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
    element.themeController.systemQuery,
    media,
    "System query must still be retained.",
  );

  element.remove();

  assert.equal(
    element.themeController.systemQuery,
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
    assert.equal(
      element.themeController.observer,
      null,
      "No observer may be built.",
    );
    assert.equal(
      element.themeController.systemQuery,
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
    element.themeController.watchTheme();

    assert.ok(
      element.themeController.observer,
      "The root must still be observed.",
    );
  } finally {
    delete document.body;
  }

  element.disconnectedCallback();

  assert.equal(
    element.themeController.observer,
    null,
    "Observer must be released.",
  );
});

test("the document pointer listener follows the element lifecycle", () => {
  installMatchMedia();

  var element = renderToolbar(payload());

  element.shadowRoot.querySelector(".extensions-toggle").click();

  assert.equal(element.openMenu, "extensions", "Menu must be open.");

  document.dispatchEvent(
    new window.MouseEvent("pointerdown", { bubbles: true, composed: true }),
  );

  assert.equal(
    element.openMenu,
    null,
    "An attached listener must dismiss the menu.",
  );

  element.remove();
  element.openMenu = "extensions";
  document.dispatchEvent(
    new window.MouseEvent("pointerdown", { bubbles: true, composed: true }),
  );

  assert.equal(
    element.openMenu,
    "extensions",
    "A detached listener must leave the menu alone.",
  );
});

test("disconnecting during a drag stops the resize", () => {
  installMatchMedia();

  var element = renderToolbar(payload());

  element.drawer.openPanel("/debug/db");
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

test("a response arriving after the toolbar was detached is dropped", () => {
  var element = connectToolbar({ "data-url": "/debug/toolbar" });
  var request = transport.last();
  var announcements = 0;

  element.addEventListener("yii.debug.toolbar_attached", function () {
    announcements += 1;
  });
  element.remove();
  request.respond(200, snapshot("after-detach"));

  assert.equal(request.aborted, true, "Disposal must abort the request.");
  assert.equal(element.data, null, "No payload may be applied.");
  assert.equal(
    element.shadowRoot.querySelector(".bar"),
    null,
    "Nothing may be rendered.",
  );
  assert.equal(announcements, 0, "No attachment may be announced.");
});

test("a retry scheduled before the toolbar was detached never fires", () => {
  vi.useFakeTimers();

  var element = connectToolbar({ "data-url": "/debug/toolbar" });

  try {
    transport.last().respond(404, "{}");

    var issued = transport.requests.length;

    element.remove();
    vi.advanceTimersByTime(5000);

    assert.equal(
      transport.requests.length,
      issued,
      "A cleared retry must not reach the network.",
    );
  } finally {
    vi.useRealTimers();
  }
});

test("a detached toolbar refuses a new load", () => {
  var element = connectToolbar({ "data-url": "/debug/toolbar" });

  element.remove();

  var issued = transport.requests.length;

  element.load();

  assert.equal(transport.requests.length, issued, "No request may be issued.");
});

test("reconnecting starts a single fresh load lifecycle", () => {
  var element = connectToolbar({ "data-url": "/debug/toolbar" });
  var stale = transport.last();
  var announcements = 0;

  element.addEventListener("yii.debug.toolbar_attached", function () {
    announcements += 1;
  });
  element.remove();
  document.body.appendChild(element);

  var fresh = transport.last();

  assert.notEqual(fresh, stale, "Reconnecting must issue its own request.");
  assert.equal(
    toolbars.filter(function (candidate) {
      return candidate === element;
    }).length,
    1,
    "Registry must hold a single entry.",
  );

  stale.respond(200, snapshot("stale"));

  assert.equal(element.data, null, "A previous lifecycle must not settle.");

  fresh.respond(200, snapshot("fresh"));

  assert.equal(element.data.tag, "fresh", "The new lifecycle must apply.");
  assert.equal(announcements, 1, "Attachment must be announced once.");

  element.remove();
});

test("a superseded response cannot overwrite the newer tag", () => {
  var element = connectToolbar({ "data-url": "/debug/toolbar" });
  var stale = transport.last();

  element.load();

  var fresh = transport.last();

  fresh.respond(200, snapshot("fresh"));
  stale.respond(200, snapshot("stale"));

  assert.equal(element.data.tag, "fresh", "Newer tag must survive.");

  element.remove();
});

test("two toolbars settling out of order keep their own state", () => {
  var first = connectToolbar({ "data-url": "/debug/toolbar?tag=one" });
  var firstRequest = transport.last();
  var second = connectToolbar({ "data-url": "/debug/toolbar?tag=two" });
  var secondRequest = transport.last();
  var announcements = [];

  first.addEventListener("yii.debug.toolbar_attached", function () {
    announcements.push("first");
  });
  second.addEventListener("yii.debug.toolbar_attached", function () {
    announcements.push("second");
  });

  secondRequest.respond(200, snapshot("two"));
  firstRequest.respond(200, snapshot("one"));

  assert.equal(first.data.tag, "one", "First toolbar must keep its tag.");
  assert.equal(second.data.tag, "two", "Second toolbar must keep its tag.");
  assert.deepEqual(
    announcements,
    ["second", "first"],
    "Each toolbar must announce only its own completion.",
  );

  first.remove();
  second.remove();
});
