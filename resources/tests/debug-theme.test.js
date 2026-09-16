// @vitest-environment jsdom
import assert from "node:assert/strict";
import { test } from "vitest";

import {
  bootDebugPage,
  fire,
  hostlessFrame,
  rootlessFrame,
  shadowHostFrame,
} from "./debug-page-harness.js";

const TOGGLE =
  '<button type="button" data-yii-debug-theme-toggle data-icon-moon="MOON" ' +
  'data-icon-sun="SUN"><span class="yii-debug-brand-icon"></span></button>';

function theme(page) {
  return page.document.documentElement.getAttribute("data-yii-debug-theme");
}

test("the parent toolbar outranks every stored preference", async () => {
  const page = await bootDebugPage({
    cookie: "yii-debug-toolbar-theme=light",
    documentTheme: "light",
    frameElement: shadowHostFrame("dark"),
    matchMedia: "light",
    storage: { "yii-debug-toolbar-theme": "light" },
    url: "https://debug.test/debug/index?yii_debug_theme=light",
  });

  assert.equal(theme(page), "dark", "Drawer must follow its host toolbar.");
});

test("the cookie wins for a page that is not framed", async () => {
  const page = await bootDebugPage({
    cookie: "yii-debug-toolbar-theme=dark",
    documentTheme: "light",
    storage: { "yii-debug-toolbar-theme": "light" },
    url: "https://debug.test/debug/index?yii_debug_theme=light",
  });

  assert.equal(theme(page), "dark", "Cookie is the last client write.");
});

test("the stored theme is used when the cookie is missing", async () => {
  const page = await bootDebugPage({
    documentTheme: "light",
    storage: { "yii-debug-toolbar-theme": "dark" },
    url: "https://debug.test/debug/index?yii_debug_theme=light",
  });

  assert.equal(theme(page), "dark", "Storage covers blocked cookies.");
});

test("the query parameter is used when no client choice is stored", async () => {
  const page = await bootDebugPage({
    documentTheme: "light",
    url: "https://debug.test/debug/index?yii_debug_theme=dark",
  });

  assert.equal(theme(page), "dark", "Deep link must seed a fresh client.");
});

test("the server-rendered attribute is used when the query has no theme", async () => {
  const page = await bootDebugPage({
    documentTheme: "dark",
    matchMedia: "light",
  });

  assert.equal(theme(page), "dark", "Server render outranks the system.");
});

test("a dark system preference is the last resort", async () => {
  const page = await bootDebugPage({ matchMedia: "dark" });

  assert.equal(theme(page), "dark", "System preference must apply.");
});

test("a light system preference resolves to the light theme", async () => {
  const page = await bootDebugPage({ matchMedia: "light" });

  assert.equal(theme(page), "light", "System preference must apply.");
});

test("a host without matchMedia resolves to the light theme", async () => {
  const page = await bootDebugPage({});

  assert.equal(theme(page), "light", "Missing query support means light.");
});

test("an unreadable frame element falls through to the next source", async () => {
  const page = await bootDebugPage({
    cookie: "yii-debug-toolbar-theme=dark",
    frameElement: "throws",
  });

  assert.equal(theme(page), "dark", "Cross-origin denial must not throw.");
});

test("a frame element without a root node falls through", async () => {
  const page = await bootDebugPage({
    cookie: "yii-debug-toolbar-theme=dark",
    frameElement: rootlessFrame(),
  });

  assert.equal(theme(page), "dark", "No root node means no host theme.");
});

test("a frame outside a shadow root falls through", async () => {
  const page = await bootDebugPage({
    cookie: "yii-debug-toolbar-theme=dark",
    frameElement: hostlessFrame(),
  });

  assert.equal(theme(page), "dark", "A document root carries no host.");
});

test("a shadow host without a usable theme falls through", async () => {
  const page = await bootDebugPage({
    cookie: "yii-debug-toolbar-theme=dark",
    frameElement: shadowHostFrame("mauve"),
  });

  assert.equal(theme(page), "dark", "Unknown host theme must be ignored.");
});

test("an unparsable address drops the query source and leaves links alone", async () => {
  const page = await bootDebugPage({
    body: '<a href="/debug/db">Database</a>',
    documentTheme: "dark",
    href: "not a url",
  });

  assert.equal(theme(page), "dark", "Server render must still apply.");
  assert.equal(
    page.document.querySelector("a").getAttribute("href"),
    "/debug/db",
    "Link must stay untouched.",
  );
});

test("the resolved theme is persisted and carried by debug links and forms", async () => {
  const page = await bootDebugPage({
    body:
      '<a href="/debug/db">Database</a><a href="#top">Top</a>' +
      '<form action="/debug/db" method="get"></form>',
    matchMedia: "dark",
  });

  const links = page.document.querySelectorAll("a");

  assert.equal(
    page.storage.items.get("yii-debug-toolbar-theme"),
    "dark",
    "Storage must record the resolved theme.",
  );
  assert.match(
    page.document.cookie,
    /yii-debug-toolbar-theme=dark/,
    "Cookie must record the resolved theme.",
  );
  assert.equal(
    links[0].getAttribute("href"),
    "https://debug.test/debug/db?yii_debug_theme=dark",
    "Debug links must carry the theme.",
  );
  assert.equal(
    links[1].getAttribute("href"),
    "#top",
    "Fragment links must stay untouched.",
  );
  assert.equal(
    page.document.querySelector('form input[name="yii_debug_theme"]').value,
    "dark",
    "GET forms must resubmit the theme.",
  );
});

test("the toggle tells the parent toolbar about the new theme", async () => {
  const page = await bootDebugPage({
    body: TOGGLE,
    matchMedia: "light",
    parent: "frame",
  });

  fire(page.document.querySelector("[data-yii-debug-theme-toggle]"), "click");

  assert.equal(theme(page), "dark", "Toggle must flip the page theme.");
  assert.deepEqual(
    page.posts,
    [
      {
        data: {
          source: "yii-debug-toolbar",
          theme: "dark",
          type: "theme",
        },
        origin: "https://debug.test",
      },
    ],
    "Host toolbar must receive the new theme.",
  );
});

test("the toggle stays silent on a standalone page", async () => {
  const page = await bootDebugPage({ body: TOGGLE, matchMedia: "light" });

  fire(page.document.querySelector("[data-yii-debug-theme-toggle]"), "click");

  assert.equal(theme(page), "dark", "Toggle must flip the page theme.");
  assert.equal(page.posts.length, 0, "Nothing must be posted to itself.");
});

test("the toggle stays silent when the window has no parent", async () => {
  const page = await bootDebugPage({
    body: TOGGLE,
    matchMedia: "light",
    parent: "none",
  });

  fire(page.document.querySelector("[data-yii-debug-theme-toggle]"), "click");

  assert.equal(theme(page), "dark", "Toggle must flip the page theme.");
  assert.equal(page.posts.length, 0, "A parentless window must be skipped.");
});
