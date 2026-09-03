import assert from "node:assert/strict";
import { test } from "vitest";

import {
  addThemeToDebugUrl,
  bindThemeToggle,
  normalizeTheme,
  preserveThemeInLinks,
  readStoredTheme,
  readThemeCookie,
  themeToggleLabel,
  writeTheme,
} from "../src/core/theme.js";

function installBrowserGlobals() {
  const values = new Map();

  globalThis.window = {
    location: new URL("https://example.test/debug/default/view?tag=1"),
    localStorage: {
      getItem(key) {
        return values.get(key) ?? null;
      },
      setItem(key, value) {
        values.set(key, value);
      },
    },
  };
  globalThis.localStorage = window.localStorage;
  globalThis.document = { cookie: "" };
}

test("normalizeTheme accepts explicit aliases without matching modifier classes", () => {
  assert.equal(normalizeTheme("night"), "dark");
  assert.equal(normalizeTheme("LIGHT"), "light");
  assert.equal(normalizeTheme("dark:bg-slate-900"), null);
  assert.equal(normalizeTheme("light dark"), null);
});

test("themeToggleLabel describes the action for the current theme", () => {
  assert.equal(themeToggleLabel("dark"), "Switch to light theme");
  assert.equal(themeToggleLabel("light"), "Switch to dark theme");
  assert.equal(themeToggleLabel(null), "Switch to dark theme");
});

test("bindThemeToggle synchronizes accessible state before and after a click", () => {
  installBrowserGlobals();

  function element(attributes = {}) {
    const values = new Map(Object.entries(attributes));
    const listeners = new Map();

    return {
      innerHTML: "",
      addEventListener(type, listener) {
        listeners.set(type, listener);
      },
      click() {
        listeners.get("click")();
      },
      getAttribute(name) {
        return values.get(name) ?? null;
      },
      setAttribute(name, value) {
        values.set(name, value);
      },
    };
  }

  const root = element({ "data-yii-debug-theme": "dark" });
  const icon = element();
  const button = element({
    "data-icon-moon": "<svg>moon</svg>",
    "data-icon-sun": "<svg>sun</svg>",
  });
  const themes = [];

  button.querySelector = () => icon;
  document.documentElement = root;
  document.querySelectorAll = () => [];

  bindThemeToggle(button, (theme) => themes.push(theme));

  assert.equal(button.getAttribute("aria-label"), "Switch to light theme");
  assert.equal(button.getAttribute("aria-pressed"), "true");
  assert.equal(button.getAttribute("title"), "Switch to light theme");
  assert.equal(button.getAttribute("data-current-theme"), "dark");
  assert.equal(icon.innerHTML, "<svg>sun</svg>");

  button.click();

  assert.equal(root.getAttribute("data-yii-debug-theme"), "light");
  assert.equal(button.getAttribute("aria-label"), "Switch to dark theme");
  assert.equal(button.getAttribute("aria-pressed"), "false");
  assert.equal(button.getAttribute("title"), "Switch to dark theme");
  assert.equal(button.getAttribute("data-current-theme"), "light");
  assert.equal(icon.innerHTML, "<svg>moon</svg>");
  assert.deepEqual(themes, ["light"]);
});

test("addThemeToDebugUrl updates same-origin debug routes only", () => {
  installBrowserGlobals();

  assert.equal(
    addThemeToDebugUrl("/debug/default/view?tag=2", "dark"),
    "https://example.test/debug/default/view?tag=2&yii_debug_theme=dark",
  );
  assert.equal(
    addThemeToDebugUrl("/debug", "dark"),
    "https://example.test/debug?yii_debug_theme=dark",
  );
  assert.equal(addThemeToDebugUrl("/site/index", "dark"), "/site/index");
  assert.equal(
    addThemeToDebugUrl("https://external.test/debug/default/view", "dark"),
    "https://external.test/debug/default/view",
  );
});

test("preserveThemeInLinks restamps navigation with the latest theme", () => {
  installBrowserGlobals();

  function element(attributes) {
    const values = new Map(Object.entries(attributes));

    return {
      getAttribute(name) {
        return values.get(name) ?? null;
      },
      setAttribute(name, value) {
        values.set(name, value);
      },
    };
  }

  const debugLink = element({ href: "/debug/view?tag=request-1" });
  const externalLink = element({ href: "https://external.test/debug/view" });
  const hashLink = element({ href: "#top" });
  const scriptLink = element({ href: "javascript:void(0)" });
  const emptyLink = element({ href: null });
  const bareForm = element({});
  let bareInput = null;

  bareForm.querySelector = () => bareInput;
  bareForm.appendChild = (input) => {
    bareInput = input;
  };
  const getForm = element({ action: "/debug/search", method: "get" });
  const postForm = element({ action: "/debug/update", method: "post" });
  const siteGetForm = element({ action: "/site/search", method: "get" });
  const externalGetForm = element({
    action: "https://external.test/debug/search",
    method: "get",
  });
  let themeInput = null;

  getForm.querySelector = () => themeInput;
  getForm.appendChild = (input) => {
    themeInput = input;
  };
  postForm.querySelector = () => null;
  postForm.appendChild = () => {
    assert.fail("POST forms must not receive a query parameter input.");
  };
  [siteGetForm, externalGetForm].forEach((form) => {
    form.querySelector = () => null;
    form.appendChild = () => {
      assert.fail("Unrelated GET forms must not receive a debug theme input.");
    };
  });

  document.querySelectorAll = (selector) =>
    selector === "a[href]"
      ? [debugLink, externalLink, hashLink, scriptLink, emptyLink]
      : [getForm, postForm, siteGetForm, externalGetForm, bareForm];
  document.createElement = () => ({});

  preserveThemeInLinks("dark");
  preserveThemeInLinks("light");

  assert.equal(
    debugLink.getAttribute("href"),
    "https://example.test/debug/view?tag=request-1&yii_debug_theme=light",
  );
  assert.equal(
    externalLink.getAttribute("href"),
    "https://external.test/debug/view",
  );
  assert.equal(
    getForm.getAttribute("action"),
    "https://example.test/debug/search?yii_debug_theme=light",
  );
  assert.equal(
    postForm.getAttribute("action"),
    "https://example.test/debug/update?yii_debug_theme=light",
  );
  assert.equal(siteGetForm.getAttribute("action"), "/site/search");
  assert.equal(
    externalGetForm.getAttribute("action"),
    "https://external.test/debug/search",
  );
  assert.equal(themeInput.type, "hidden");
  assert.equal(themeInput.name, "yii_debug_theme");
  assert.equal(themeInput.value, "light");
  assert.equal(hashLink.getAttribute("href"), "#top");
  assert.equal(scriptLink.getAttribute("href"), "javascript:void(0)");
  assert.equal(emptyLink.getAttribute("href"), null);
  assert.equal(
    bareForm.getAttribute("action"),
    "https://example.test/debug/default/view?tag=1&yii_debug_theme=light",
  );
  assert.equal(bareInput.value, "light");
});

test("writeTheme persists the normalized theme", () => {
  installBrowserGlobals();

  writeTheme("night");

  assert.equal(readStoredTheme(), "dark");
  assert.match(document.cookie, /^yii-debug-toolbar-theme=dark;/);
});

test("theme cookies parse defensively and invalid writes are ignored", () => {
  installBrowserGlobals();

  assert.equal(readThemeCookie(), null);

  document.cookie = "other=1; yii-debug-toolbar-theme=dark";
  assert.equal(readThemeCookie(), "dark");

  document.cookie = "yii-debug-toolbar-theme=%E0%A4%A";
  assert.equal(readThemeCookie(), null);

  writeTheme("not-a-theme");
  assert.equal(readStoredTheme(), null);
});

test("theme reads and writes survive blocked or missing storage backends", () => {
  installBrowserGlobals();

  const blocked = {
    getItem() {
      throw new Error("blocked");
    },
    setItem() {
      throw new Error("blocked");
    },
  };

  globalThis.localStorage = blocked;
  window.localStorage = blocked;
  assert.equal(readStoredTheme(), null);
  writeTheme("dark");

  installBrowserGlobals();
  Object.defineProperty(globalThis.document, "cookie", {
    get() {
      return "";
    },
    set() {
      throw new Error("blocked");
    },
  });
  writeTheme("dark");
  assert.equal(readStoredTheme(), "dark");

  delete window.localStorage;
  assert.equal(readStoredTheme(), null);

  assert.equal(addThemeToDebugUrl("https://[bad", "dark"), "https://[bad");
  assert.equal(addThemeToDebugUrl("/debug", "sparkle"), "/debug");
});

test("theme toggle handles missing icons, callbacks, and repeated toggles", () => {
  installBrowserGlobals();

  assert.equal(bindThemeToggle(null), undefined);

  const listeners = new Map();
  const rootValues = new Map();
  const root = {
    getAttribute(name) {
      return rootValues.get(name) ?? null;
    },
    setAttribute(name, value) {
      rootValues.set(name, value);
    },
  };
  const buttonValues = new Map();
  const button = {
    addEventListener(type, listener) {
      listeners.set(type, listener);
    },
    getAttribute(name) {
      return buttonValues.get(name) ?? null;
    },
    querySelector() {
      return null;
    },
    setAttribute(name, value) {
      buttonValues.set(name, value);
    },
  };

  document.documentElement = root;
  document.querySelectorAll = () => [];

  bindThemeToggle(button);

  assert.equal(button.getAttribute("aria-pressed"), "false");

  listeners.get("click")();
  assert.equal(rootValues.get("data-yii-debug-theme"), "dark");
  assert.equal(button.getAttribute("aria-pressed"), "true");

  listeners.get("click")();
  assert.equal(rootValues.get("data-yii-debug-theme"), "light");
});
