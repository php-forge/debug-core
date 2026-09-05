import assert from "node:assert/strict";
import { test } from "vitest";

import { renderPhpBrand, renderYiiBrand } from "../src/toolbar/brand.js";
import { builtinIconUrl } from "../src/toolbar/icons.js";
import {
  focusToolbarElement,
  focusToolbarTrigger,
  isToolbarDrawerCloseMessage,
  isToolbarDrawerThemeMessage,
  requestParentToolbarDrawerClose,
  shouldCloseToolbarDrawer,
} from "../src/toolbar/focus.js";
import {
  isToolbarLoadCurrent,
  resolveToolbarLoadGeneration,
  resolveToolbarLoadRollback,
  toolbarDataUrlForTag,
  toolbarRetryDelay,
} from "../src/toolbar/loading.js";
import {
  renderAjaxProfileLink,
  renderToolbarItemIdentifier,
  renderToolbarLinkAttributes,
  shouldOpenToolbarDrawer,
  toolbarItemTag,
  toolbarPanelContainerTag,
} from "../src/toolbar/panel.js";
import {
  normalizeToolbarPosition,
  toolbarDrawerHeight,
  toolbarDrawerHeightForKey,
} from "../src/toolbar/position.js";
import { normalizeToolbarUrl, sameToolbarUrl } from "../src/toolbar/url.js";

function focusable(attributes = {}) {
  return {
    attributes,
    focused: false,
    focus() {
      this.focused = true;
    },
    getAttribute(name) {
      return this.attributes[name] ?? null;
    },
  };
}

test("builtinIconUrl provides self-contained shared toolbar icons", () => {
  var iconNames = [
    "ajax",
    "asset",
    "chevron-left",
    "chevron-right",
    "clock",
    "close",
    "config",
    "db",
    "dots",
    "dump",
    "events",
    "external-link",
    "identity",
    "inertia",
    "logs",
    "mail",
    "moon",
    "php-alt",
    "profiling",
    "queue",
    "request",
    "router",
    "security",
    "sun",
    "timeline",
    "user",
  ];

  iconNames.forEach(function (iconName) {
    assert.match(builtinIconUrl(iconName), /^data:image\/svg\+xml,/);
  });
  assert.equal(builtinIconUrl("adapter-specific"), "");
});

test("toolbarRetryDelay retries missing snapshots with bounded backoff", () => {
  assert.equal(toolbarRetryDelay(404, 0), 75);
  assert.equal(toolbarRetryDelay(404, 1), 150);
  assert.equal(toolbarRetryDelay(404, 2), 300);
  assert.equal(toolbarRetryDelay(404, 3), 600);
  assert.equal(toolbarRetryDelay(404, 4), 900);
  assert.equal(toolbarRetryDelay(404, 5), null);
  assert.equal(toolbarRetryDelay(404, -1), null);
  assert.equal(toolbarRetryDelay(404, Number.NaN), null);
  assert.equal(toolbarRetryDelay(404, 0.5), null);
  assert.equal(toolbarRetryDelay(500, 0), null);
});

test("toolbar load generations reject stale responses and retries", () => {
  var activeGeneration = resolveToolbarLoadGeneration(0);
  var staleGeneration = activeGeneration;

  assert.equal(activeGeneration, 1);

  activeGeneration = resolveToolbarLoadGeneration(activeGeneration);

  assert.equal(activeGeneration, 2);
  assert.equal(isToolbarLoadCurrent(activeGeneration, staleGeneration), false);
  assert.equal(isToolbarLoadCurrent(activeGeneration, activeGeneration), true);
  assert.equal(
    resolveToolbarLoadGeneration(activeGeneration, staleGeneration),
    staleGeneration,
  );
});

test("toolbar load rollback prefers the last successful snapshot", () => {
  assert.deepEqual(
    resolveToolbarLoadRollback(
      "/debug/toolbar?tag=loaded",
      "loaded",
      "/debug/toolbar?tag=pending-a",
      "pending-a",
    ),
    {
      url: "/debug/toolbar?tag=loaded",
      tag: "loaded",
      reload: false,
    },
  );
  assert.deepEqual(
    resolveToolbarLoadRollback(
      null,
      null,
      "/debug/toolbar?tag=pending-a",
      "pending-a",
    ),
    {
      url: "/debug/toolbar?tag=pending-a",
      tag: "pending-a",
      reload: true,
    },
  );
});

test("toolbar data URLs follow tags through query parameters", () => {
  var baseUrl = "https://example.test/app";

  assert.equal(
    toolbarDataUrlForTag(
      "/debug/toolbar?tag=request-1&panel=summary",
      "request-2",
      baseUrl,
    ),
    "https://example.test/debug/toolbar?tag=request-2&panel=summary",
  );
  assert.equal(
    toolbarDataUrlForTag("/debug/toolbar?panel=summary", "request-2", baseUrl),
    "https://example.test/debug/toolbar?panel=summary&tag=request-2",
  );
});

test("toolbar data URL resolution rejects unusable inputs", () => {
  var baseUrl = "https://example.test/";

  assert.equal(toolbarDataUrlForTag("", "request-2", baseUrl), null);
  assert.equal(toolbarDataUrlForTag("/debug/toolbar", "", baseUrl), null);
  assert.equal(
    toolbarDataUrlForTag("https://[invalid", "request-2", baseUrl),
    null,
  );
});

test("toolbar item links remain focusable without nested interactive elements", () => {
  var location = "https://example.test/";
  var linkedItem = { url: "/debug/request/status" };
  var unlinkedItem = { value: "200" };
  var itemOnlyPanel = { items: [linkedItem] };
  var panelAndItemLinks = {
    items: [linkedItem],
    url: "/debug/request",
  };

  assert.equal(toolbarPanelContainerTag(itemOnlyPanel, location), "div");
  assert.equal(toolbarItemTag(linkedItem, location), "a");
  assert.equal(toolbarItemTag(unlinkedItem, location), "span");
  assert.equal(toolbarPanelContainerTag(panelAndItemLinks, location), "div");
  assert.equal(
    toolbarPanelContainerTag(
      {
        items: [unlinkedItem, linkedItem],
        url: "/debug/request",
      },
      location,
    ),
    "div",
  );
  assert.equal(
    toolbarPanelContainerTag(
      { items: [unlinkedItem], url: "/debug" },
      location,
    ),
    "a",
  );
  assert.equal(toolbarPanelContainerTag({ url: "/debug" }, location), "a");
  assert.equal(
    toolbarPanelContainerTag({ items: [unlinkedItem] }, location),
    "div",
  );
  assert.equal(
    toolbarPanelContainerTag(
      { items: [unlinkedItem], url: "https://attacker.test/debug" },
      location,
    ),
    "div",
  );
  assert.equal(
    toolbarItemTag({ url: "javascript:alert(1)" }, location),
    "span",
  );
});

test("toolbar metric identifiers are escaped and omitted when unavailable", () => {
  var escape = function (value) {
    return value.replaceAll('"', "&quot;");
  };

  assert.equal(
    renderToolbarItemIdentifier({ id: 'route"name' }, escape),
    ' data-item-id="route&quot;name"',
  );
  assert.equal(renderToolbarItemIdentifier({}, escape), "");
  assert.equal(renderToolbarItemIdentifier({ id: "" }, escape), "");
  assert.equal(renderToolbarItemIdentifier(null, escape), "");
});

test("toolbar native links carry the active theme without changing drawer URLs", () => {
  var location = "https://example.test/";

  assert.equal(
    renderToolbarLinkAttributes(
      "/debug/request?tag=1",
      "/debug/request?tag=1&yii_debug_theme=dark",
      String,
    ),
    ' href="/debug/request?tag=1&yii_debug_theme=dark" data-debug-url="/debug/request?tag=1"',
  );
  assert.equal(renderToolbarLinkAttributes(null, null, String), "");
  assert.equal(
    renderToolbarLinkAttributes(null, "/debug/request", String, location),
    "",
  );
  assert.equal(
    renderToolbarLinkAttributes(
      "/debug/request",
      "javascript:alert(1)",
      String,
      location,
    ),
    "",
  );
});

test("toolbar URL normalization enforces the same-origin HTTP boundary", () => {
  var location = "https://example.test/app";

  assert.equal(
    normalizeToolbarUrl("/custom-debug/view?tag=1", location),
    "/custom-debug/view?tag=1",
  );
  assert.equal(
    normalizeToolbarUrl("https://example.test/debug/view", location),
    "https://example.test/debug/view",
  );
  assert.equal(
    normalizeToolbarUrl(
      "http://example.test/debug/view",
      "http://example.test/app",
    ),
    "http://example.test/debug/view",
  );
  assert.equal(
    normalizeToolbarUrl("/debug/view", "ftp://example.test/app"),
    null,
  );
  assert.equal(
    normalizeToolbarUrl("https://other.test/debug/view", location),
    null,
  );
  assert.equal(
    normalizeToolbarUrl("//example.test/debug/view", location),
    null,
  );
  assert.equal(
    normalizeToolbarUrl("\\\\example.test\\debug\\view", location),
    null,
  );
  assert.equal(normalizeToolbarUrl("javascript:alert(1)", location), null);
  assert.equal(normalizeToolbarUrl("data:text/html,test", location), null);
  assert.equal(
    normalizeToolbarUrl("https://user@example.test/debug/view", location),
    null,
  );
});

test("toolbar link renderers drop unsafe navigation targets", () => {
  var location = "https://example.test/";

  assert.equal(
    renderToolbarLinkAttributes(
      "javascript:alert(1)",
      "javascript:alert(1)",
      String,
      location,
    ),
    "",
  );
  assert.equal(
    renderAjaxProfileLink(
      "hostile",
      "https://other.test/debug/view",
      "https://other.test/debug/view",
      String,
      location,
    ),
    "n/a",
  );
  assert.equal(
    shouldOpenToolbarDrawer(
      { button: 0, ctrlKey: false, metaKey: false, shiftKey: false },
      "data:text/html,test",
      location,
    ),
    false,
  );
});

test("toolbar URL fallbacks cover missing browser locations and bases", () => {
  assert.equal(normalizeToolbarUrl("relative/view"), null);
  assert.equal(normalizeToolbarUrl("/debug/view"), "/debug/view");
  assert.equal(normalizeToolbarUrl("/debug/view", "https://[invalid"), null);
  assert.equal(
    normalizeToolbarUrl(
      "https://:secret@example.test/debug",
      "https://example.test/",
    ),
    null,
  );
  assert.equal(normalizeToolbarUrl("   ", "https://example.test/"), null);

  assert.equal(sameToolbarUrl("/debug/a", "/debug/a"), true);
  assert.equal(sameToolbarUrl("/debug/a", "/debug/b"), false);
  assert.equal(
    sameToolbarUrl("javascript:x", "/debug/a", "https://example.test/"),
    false,
  );

  globalThis.window = { location: null };
  assert.equal(normalizeToolbarUrl("/debug/view"), "/debug/view");

  globalThis.window = {
    location: {
      href: "",
      toString() {
        return "https://example.test/app";
      },
    },
  };
  assert.equal(
    normalizeToolbarUrl("https://example.test/debug/view"),
    "https://example.test/debug/view",
  );
  delete globalThis.window;
});

test("equivalent drawer URLs keep the existing iframe browsing context", () => {
  var location = "https://example.test/app";

  assert.equal(
    sameToolbarUrl(
      "/debug/view?tag=1",
      "https://example.test/debug/view?tag=1",
      location,
    ),
    true,
  );
  assert.equal(
    sameToolbarUrl("/debug/view?tag=1", "/debug/view?tag=2", location),
    false,
  );
  assert.equal(sameToolbarUrl("javascript:x", "/null", location), false);
  assert.equal(sameToolbarUrl("/null", "javascript:x", location), false);
});

test("toolbar drawer preserves native modified-click navigation", () => {
  var click = { button: 0, ctrlKey: false, metaKey: false, shiftKey: false };

  assert.equal(shouldOpenToolbarDrawer(click, "/debug/request"), true);
  assert.equal(
    shouldOpenToolbarDrawer({ ...click, button: 1 }, "/debug"),
    false,
  );
  assert.equal(
    shouldOpenToolbarDrawer({ ...click, ctrlKey: true }, "/debug"),
    false,
  );
  assert.equal(
    shouldOpenToolbarDrawer({ ...click, metaKey: true }, "/debug"),
    false,
  );
  assert.equal(
    shouldOpenToolbarDrawer({ ...click, shiftKey: true }, "/debug"),
    false,
  );
  assert.equal(shouldOpenToolbarDrawer(click, null), false);
});

test("drawer focus enters the close control and returns to its trigger", () => {
  var close = focusable();
  var first = focusable({ "data-debug-url": "/debug/request" });
  var second = focusable({ "data-debug-url": "/debug/log" });
  var root = {
    querySelector(selector) {
      return selector === ".close-drawer" ? close : null;
    },
    querySelectorAll(selector) {
      return selector === "[data-debug-url]" ? [first, second] : [];
    },
  };

  assert.equal(focusToolbarElement(root, ".close-drawer"), true);
  assert.equal(close.focused, true);
  assert.equal(focusToolbarTrigger(root, "/debug/log"), true);
  assert.equal(second.focused, true);
  assert.equal(first.focused, false);
  assert.equal(focusToolbarElement(null, ".close-drawer"), false);
  assert.equal(focusToolbarElement(root, ".missing"), false);
  assert.equal(
    focusToolbarElement({ querySelector: () => ({}) }, ".close-drawer"),
    false,
  );
  assert.equal(focusToolbarTrigger(null, "/debug/log"), false);
  assert.equal(focusToolbarTrigger(root, ""), false);
  assert.equal(focusToolbarTrigger(root, "/debug/missing"), false);
});

test("Escape closes only an active toolbar drawer", () => {
  assert.equal(
    shouldCloseToolbarDrawer({ key: "Escape", defaultPrevented: false }, true),
    true,
  );
  assert.equal(
    shouldCloseToolbarDrawer({ key: "Enter", defaultPrevented: false }, true),
    false,
  );
  assert.equal(
    shouldCloseToolbarDrawer({ key: "Escape", defaultPrevented: true }, true),
    false,
  );
  assert.equal(
    shouldCloseToolbarDrawer({ key: "Escape", defaultPrevented: false }, false),
    false,
  );
});

test("drawer close messages are bound to the active same-origin iframe", () => {
  var frameWindow = {};
  var message = {
    data: { source: "yii-debug-toolbar", type: "close-drawer" },
    origin: "https://example.test",
    source: frameWindow,
  };

  assert.equal(
    isToolbarDrawerCloseMessage(message, "https://example.test", frameWindow),
    true,
  );
  assert.equal(
    isToolbarDrawerCloseMessage(
      { ...message, origin: "https://attacker.test" },
      "https://example.test",
      frameWindow,
    ),
    false,
  );
  assert.equal(
    isToolbarDrawerCloseMessage(message, "https://example.test", {}),
    false,
  );
  assert.equal(
    isToolbarDrawerCloseMessage(
      { ...message, data: { ...message.data, type: "theme" } },
      "https://example.test",
      frameWindow,
    ),
    false,
  );
  assert.equal(
    isToolbarDrawerCloseMessage(
      { ...message, data: { source: "another-app", type: "close-drawer" } },
      "https://example.test",
      frameWindow,
    ),
    false,
  );
  var callableData = function () {};
  callableData.source = "yii-debug-toolbar";
  callableData.type = "close-drawer";

  assert.equal(
    isToolbarDrawerCloseMessage(
      { ...message, data: callableData },
      "https://example.test",
      frameWindow,
    ),
    false,
  );
});

test("theme messages are bound to the active same-origin iframe", () => {
  var frameWindow = {};
  var message = {
    data: { source: "yii-debug-toolbar", type: "theme", theme: "dark" },
    origin: "https://example.test",
    source: frameWindow,
  };

  assert.equal(
    isToolbarDrawerThemeMessage(message, "https://example.test", frameWindow),
    true,
  );
  assert.equal(
    isToolbarDrawerThemeMessage(
      { ...message, origin: "https://attacker.test" },
      "https://example.test",
      frameWindow,
    ),
    false,
  );
  assert.equal(
    isToolbarDrawerThemeMessage(message, "https://example.test", {}),
    false,
  );
  assert.equal(
    isToolbarDrawerThemeMessage(
      { ...message, data: { ...message.data, type: "close-drawer" } },
      "https://example.test",
      frameWindow,
    ),
    false,
  );
  assert.equal(
    isToolbarDrawerThemeMessage(
      { ...message, data: { ...message.data, source: "another-app" } },
      "https://example.test",
      frameWindow,
    ),
    false,
  );
  assert.equal(
    isToolbarDrawerThemeMessage(
      { ...message, data: null },
      "https://example.test",
      frameWindow,
    ),
    false,
  );
  var callableData = function () {};
  callableData.source = "yii-debug-toolbar";
  callableData.type = "theme";

  assert.equal(
    isToolbarDrawerThemeMessage(
      { ...message, data: callableData },
      "https://example.test",
      frameWindow,
    ),
    false,
  );
});

test("embedded debug pages request drawer closure after an unhandled Escape", () => {
  var messages = [];
  var parent = {
    postMessage(message, origin) {
      messages.push({ message, origin });
    },
  };
  var browserWindow = {
    location: { origin: "https://example.test" },
    parent,
  };
  var escape = { key: "Escape", defaultPrevented: false };

  assert.equal(
    requestParentToolbarDrawerClose(escape, browserWindow, false),
    true,
  );
  assert.deepEqual(messages, [
    {
      message: { source: "yii-debug-toolbar", type: "close-drawer" },
      origin: "https://example.test",
    },
  ]);
  assert.equal(
    requestParentToolbarDrawerClose(
      { ...escape, defaultPrevented: true },
      browserWindow,
      false,
    ),
    false,
  );
  assert.equal(
    requestParentToolbarDrawerClose(escape, browserWindow, true),
    false,
  );
  var topWindow = { location: browserWindow.location };
  topWindow.parent = topWindow;

  assert.equal(
    requestParentToolbarDrawerClose(escape, topWindow, false),
    false,
  );
  assert.equal(
    requestParentToolbarDrawerClose(
      { key: "Enter", defaultPrevented: false },
      browserWindow,
      false,
    ),
    false,
  );
});

test("AJAX profile URLs separate themed native and drawer navigation", () => {
  var html = renderAjaxProfileLink(
    "request-profile",
    "/debug/view?tag=request-profile",
    "/debug/view?tag=request-profile&yii_debug_theme=dark",
    String,
  );

  assert.equal(
    html,
    '<a class="ajax-link" href="/debug/view?tag=request-profile&yii_debug_theme=dark" data-debug-url="/debug/view?tag=request-profile">request-profile</a>',
  );
  assert.equal(renderAjaxProfileLink(null, null, null, String), "n/a");
});

test("normalizeToolbarPosition honors top and the legacy upper alias", () => {
  assert.equal(normalizeToolbarPosition("top"), "top");
  assert.equal(normalizeToolbarPosition("upper"), "top");
  assert.equal(normalizeToolbarPosition("bottom"), "bottom");
  assert.equal(normalizeToolbarPosition("invalid"), "bottom");
});

test("toolbarDrawerHeight follows the configured resize direction", () => {
  assert.equal(toolbarDrawerHeight("top", 180, 800, null), 180);
  assert.equal(toolbarDrawerHeight("bottom", 180, 800, null), 620);

  var drawerRect = { top: 100, bottom: 700 };

  assert.equal(toolbarDrawerHeight("top", 180, 800, drawerRect), 80);
  assert.equal(toolbarDrawerHeight("bottom", 180, 800, drawerRect), 520);
});

test("toolbar drawer keyboard resizing follows its anchored edge", () => {
  assert.equal(toolbarDrawerHeightForKey("bottom", "ArrowUp", 300, 800), 324);
  assert.equal(toolbarDrawerHeightForKey("bottom", "ArrowDown", 300, 800), 276);
  assert.equal(toolbarDrawerHeightForKey("top", "ArrowUp", 300, 800), 276);
  assert.equal(toolbarDrawerHeightForKey("top", "ArrowDown", 300, 800), 324);
  assert.equal(toolbarDrawerHeightForKey("bottom", "Home", 300, 800), 120);
  assert.equal(toolbarDrawerHeightForKey("bottom", "End", 300, 800), 752);
  assert.equal(toolbarDrawerHeightForKey("bottom", "ArrowDown", 125, 800), 120);
  assert.equal(toolbarDrawerHeightForKey("bottom", "ArrowUp", 750, 800), 752);
  assert.equal(toolbarDrawerHeightForKey("bottom", "PageUp", 300, 800), null);
});

test("renderYiiBrand links available configuration", () => {
  assert.equal(
    renderYiiBrand("3.0", "/debug/config", "<logo></logo>", String),
    '<a class="brand-link brand-link-yii" href="/debug/config" data-debug-url="/debug/config" title="Yii 3.0 — open configuration"><logo></logo><span class="brand-version">3.0</span></a>',
  );
});

test("renderYiiBrand renders unavailable configuration as static content", () => {
  assert.equal(
    renderYiiBrand(null, null, "<logo></logo>", String),
    '<span class="brand-link brand-link-yii brand-static" title="Open configuration"><logo></logo></span>',
  );
});

test("renderPhpBrand renders unavailable PHP info as static content", () => {
  assert.equal(
    renderPhpBrand("8.5.9", null, '<span class="icon"></span>', String),
    '<span class="brand-link brand-link-php brand-static" title="PHP 8.5.9 — phpinfo unavailable"><span class="icon"></span><span class="brand-version">8.5.9</span></span>',
  );
});

test("renderPhpBrand links available PHP info", () => {
  assert.equal(
    renderPhpBrand(
      "8.5.9",
      "/debug/php-info",
      '<span class="icon"></span>',
      String,
    ),
    '<a class="brand-link brand-link-php" href="/debug/php-info" target="_blank" rel="noopener" title="PHP 8.5.9 — open phpinfo in a new tab"><span class="icon"></span><span class="brand-version">8.5.9</span></a>',
  );
});
