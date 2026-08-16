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

const {
  addThemeToUrl,
  delegateThemeToHost,
  getHostThemeControl,
  hostHasThemeControl,
  normalizeTheme,
  resetHostThemeControlCache,
  writeThemeCookie,
} = await import("../src/toolbar/theme.js");

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

test("host theme control detection stays cached until reset", () => {
  var sweeps = 0;
  var nodes = [];
  var themeControl = {
    clicks: 0,
    dataset: {},
    click() {
      this.clicks += 1;
    },
    getAttribute(name) {
      return name === "aria-label" ? "Toggle dark mode" : null;
    },
    getClientRects() {
      return [];
    },
    offsetParent: {},
    textContent: "",
  };

  document.querySelectorAll = function () {
    sweeps += 1;

    return nodes;
  };

  resetHostThemeControlCache();

  assert.equal(hostHasThemeControl(), false);
  assert.equal(hostHasThemeControl(), false);
  assert.equal(sweeps, 1);
  assert.equal(delegateThemeToHost("dark", "light"), false);

  nodes = [themeControl];

  assert.equal(hostHasThemeControl(), false);
  assert.equal(sweeps, 1);

  resetHostThemeControlCache();

  assert.equal(hostHasThemeControl(), true);
  assert.equal(getHostThemeControl(), themeControl);
  assert.equal(hostHasThemeControl(), true);
  assert.equal(sweeps, 2);

  assert.equal(delegateThemeToHost("dark", "light"), true);
  assert.equal(themeControl.clicks, 1);

  assert.equal(delegateThemeToHost("dark", "dark"), true);
  assert.equal(themeControl.clicks, 1);
});
