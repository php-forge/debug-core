import assert from "node:assert/strict";
import { test } from "vitest";

import {
  closest,
  HOST_THEME_PROPAGATION_KEYS,
  HOST_THEME_STORAGE_KEYS,
  normalizeThemeToken,
  THEME_STORAGE_KEY,
  themeCookie,
} from "../src/core/shared.js";

/** Minimal element stand-in: `matches` answers for one selector only. */
function elementNode(selector, parentElement) {
  return {
    matches(candidate) {
      return candidate === selector;
    },
    nodeType: 1,
    parentElement: parentElement || null,
  };
}

test("the debugger storage identifier is the one both bundles persist under", () => {
  assert.equal(THEME_STORAGE_KEY, "yii-debug-toolbar-theme");
});

test("normalizeThemeToken resolves each alias family to its canonical token", () => {
  assert.equal(normalizeThemeToken("dark"), "dark");
  assert.equal(normalizeThemeToken("night"), "dark");
  assert.equal(normalizeThemeToken("black"), "dark");
  assert.equal(normalizeThemeToken("light"), "light");
  assert.equal(normalizeThemeToken("day"), "light");
  assert.equal(normalizeThemeToken("white"), "light");
});

test("normalizeThemeToken reads a token out of a whitespace-separated class list", () => {
  assert.equal(normalizeThemeToken("  H-FULL   DARK  "), "dark");
  assert.equal(normalizeThemeToken("theme-a light theme-b"), "light");
});

test("normalizeThemeToken rejects empty, ambiguous and unrecognized values", () => {
  assert.equal(normalizeThemeToken(""), null);
  assert.equal(normalizeThemeToken(null), null);
  assert.equal(normalizeThemeToken(undefined), null);
  assert.equal(normalizeThemeToken(0), null);
  assert.equal(normalizeThemeToken("dark light"), null);
  assert.equal(normalizeThemeToken("dark:bg-slate-900"), null);
});

test("themeCookie writes the debugger key for a year, path-wide and same-site", () => {
  assert.equal(
    themeCookie("dark"),
    "yii-debug-toolbar-theme=dark;path=/;max-age=31536000;SameSite=Lax",
  );
});

test("themeCookie percent-encodes the value it carries", () => {
  assert.equal(
    themeCookie("dark theme"),
    "yii-debug-toolbar-theme=dark%20theme;path=/;max-age=31536000;SameSite=Lax",
  );
});

test("the host storage keys are read most-specific-first and include the Vite convention", () => {
  assert.deepEqual(HOST_THEME_STORAGE_KEYS, [
    "theme",
    "color-theme",
    "colorScheme",
    "color-scheme",
    "data-bs-theme",
    "bs-theme",
    "ui-theme",
    "preferred-theme",
    "vite-ui-theme",
    "vueuse-color-scheme",
  ]);
});

test("the propagated host storage keys stay a subset of the keys read", () => {
  assert.deepEqual(HOST_THEME_PROPAGATION_KEYS, [
    "theme",
    "color-theme",
    "color-scheme",
    "vueuse-color-scheme",
    "vite-ui-theme",
  ]);
  assert.deepEqual(
    HOST_THEME_PROPAGATION_KEYS.filter(
      (key) => HOST_THEME_STORAGE_KEYS.indexOf(key) === -1,
    ),
    [],
  );
});

test("closest matches the element itself and walks up to ancestors", () => {
  var grandparent = elementNode(".panel");
  var parent = elementNode(".item", grandparent);
  var child = elementNode(".label", parent);

  assert.equal(closest(child, ".label"), child);
  assert.equal(closest(child, ".panel"), grandparent);
  assert.equal(closest(child, ".missing"), null);
});

test("closest starts from the parent element for non-element nodes", () => {
  var parent = elementNode(".item");
  var textNode = { nodeType: 3, parentElement: parent };
  var detachedTextNode = { nodeType: 3, parentElement: null };

  assert.equal(closest(textNode, ".item"), parent);
  assert.equal(closest(detachedTextNode, ".item"), null);
});

test("closest returns null for missing nodes and non-element ancestors", () => {
  var documentNode = { nodeType: 9, parentElement: null };
  var rootElement = elementNode(".root", documentNode);

  assert.equal(closest(null, ".item"), null);
  assert.equal(closest(rootElement, ".missing"), null);
});
