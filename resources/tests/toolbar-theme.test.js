import assert from "node:assert/strict";
import test from "node:test";

globalThis.window = {
  location: new URL("https://example.test/"),
  localStorage: {
    getItem() {
      return null;
    },
    setItem() {},
  },
};
globalThis.localStorage = window.localStorage;
globalThis.document = { cookie: "" };
globalThis.XMLHttpRequest = function XMLHttpRequest() {};
globalThis.XMLHttpRequest.prototype.open = function open() {};

const { addThemeToUrl, normalizeTheme, writeThemeCookie } =
  await import("../src/toolbar/theme.js");

test("normalizeTheme accepts explicit aliases", () => {
  assert.equal(normalizeTheme("night"), "dark");
  assert.equal(normalizeTheme("day"), "light");
  assert.equal(normalizeTheme("dark light"), null);
});

test("addThemeToUrl stamps same-origin debug links", () => {
  assert.equal(
    addThemeToUrl("/debug/view?tag=1", "dark"),
    "https://example.test/debug/view?tag=1&yii_debug_theme=dark",
  );
  assert.equal(
    addThemeToUrl("https://other.test/debug/view?tag=1", "dark"),
    "https://other.test/debug/view?tag=1",
  );
});

test("writeThemeCookie persists the shared toolbar preference", () => {
  writeThemeCookie("dark");

  assert.match(document.cookie, /^yii-debug-toolbar-theme=dark;/);
});
