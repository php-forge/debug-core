import assert from "node:assert/strict";
import test from "node:test";

import { renderPhpBrand } from "../src/toolbar/brand.js";
import { builtinIconUrl } from "../src/toolbar/icons.js";
import {
  isToolbarLoadCurrent,
  resolveToolbarLoadGeneration,
  resolveToolbarLoadRollback,
  toolbarDataUrlForTag,
  toolbarRetryDelay,
} from "../src/toolbar/loading.js";
import {
  renderAjaxProfileLink,
  toolbarItemTag,
  toolbarPanelContainerTag,
} from "../src/toolbar/panel.js";
import {
  normalizeToolbarPosition,
  toolbarDrawerHeight,
} from "../src/toolbar/position.js";

test("builtinIconUrl provides self-contained toolbar control icons", () => {
  assert.match(builtinIconUrl("sun"), /^data:image\/svg\+xml,/);
  assert.match(builtinIconUrl("external-link"), /^data:image\/svg\+xml,/);
  assert.equal(builtinIconUrl("request"), "");
});

test("toolbarRetryDelay retries missing snapshots with bounded backoff", () => {
  assert.equal(toolbarRetryDelay(404, 0), 75);
  assert.equal(toolbarRetryDelay(404, 4), 900);
  assert.equal(toolbarRetryDelay(404, 5), null);
  assert.equal(toolbarRetryDelay(500, 0), null);
});

test("toolbar load generations reject stale responses and retries", () => {
  var activeGeneration = resolveToolbarLoadGeneration(0);
  var staleGeneration = activeGeneration;

  activeGeneration = resolveToolbarLoadGeneration(activeGeneration);

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

test("toolbar data URLs follow tags across query and pretty-path routes", () => {
  var baseUrl = "https://example.test/app";

  assert.equal(
    toolbarDataUrlForTag(
      "/debug/toolbar?tag=request-1&panel=summary",
      "request-1",
      "request-2",
      baseUrl,
    ),
    "https://example.test/debug/toolbar?tag=request-2&panel=summary",
  );
  assert.equal(
    toolbarDataUrlForTag(
      "/debug/toolbar/request-1?panel=summary",
      "request-1",
      "request-2",
      baseUrl,
    ),
    "https://example.test/debug/toolbar/request-2?panel=summary",
  );
  assert.equal(
    toolbarDataUrlForTag(
      "/debug/toolbar?panel=summary",
      null,
      "request-2",
      baseUrl,
    ),
    "https://example.test/debug/toolbar?panel=summary&tag=request-2",
  );
});

test("toolbar data URL resolution rejects unusable inputs", () => {
  assert.equal(toolbarDataUrlForTag("", "request-1", "request-2", ""), null);
  assert.equal(
    toolbarDataUrlForTag("/debug/toolbar", "request-1", "", ""),
    null,
  );
  assert.equal(
    toolbarDataUrlForTag(
      "https://[invalid",
      "request-1",
      "request-2",
      "https://example.test/",
    ),
    null,
  );
});

test("toolbar item links remain focusable without nested interactive elements", () => {
  var itemOnlyPanel = { items: [{ url: "/debug/request" }], url: null };
  var panelAndItemLinks = {
    items: [{ url: "/debug/request/status" }],
    url: "/debug/request",
  };

  assert.equal(toolbarPanelContainerTag(itemOnlyPanel), "div");
  assert.equal(toolbarItemTag(itemOnlyPanel.items[0]), "a");
  assert.equal(toolbarPanelContainerTag(panelAndItemLinks), "div");
  assert.equal(toolbarPanelContainerTag({ items: [], url: "/debug" }), "a");
  assert.equal(toolbarItemTag({ url: null }), "span");
});

test("AJAX profile URLs render as native links", () => {
  var html = renderAjaxProfileLink(
    "request-profile",
    "/debug/view?tag=request-profile",
    String,
  );

  assert.match(html, /^<a class="ajax-link"/);
  assert.match(html, /href="\/debug\/view\?tag=request-profile"/);
  assert.match(html, /data-debug-url="\/debug\/view\?tag=request-profile"/);
  assert.equal(renderAjaxProfileLink(null, null, String), "n/a");
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

test("renderPhpBrand renders unavailable PHP info as static content", () => {
  var html = renderPhpBrand(
    "8.5.9",
    null,
    '<span class="icon"></span>',
    String,
  );

  assert.match(html, /^<span class="brand-link brand-link-php brand-static"/);
  assert.match(html, /PHP 8\.5\.9 — phpinfo unavailable/);
  assert.doesNotMatch(html, /href=|target=/);
});

test("renderPhpBrand links available PHP info", () => {
  var html = renderPhpBrand(
    "8.5.9",
    "/debug/php-info",
    '<span class="icon"></span>',
    String,
  );

  assert.match(html, /^<a class="brand-link brand-link-php"/);
  assert.match(html, /href="\/debug\/php-info"/);
  assert.match(html, /target="_blank"/);
});
