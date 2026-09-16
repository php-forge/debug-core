// @vitest-environment jsdom
import assert from "node:assert/strict";
import { test } from "vitest";

import { resetHostThemeControlCache } from "../src/toolbar/theme.js";
import {
  installLocalStorage,
  installMatchMedia,
  removeMatchMedia,
  renderToolbar,
  stubFrameWindow,
  toolbarPayload,
} from "./toolbar-element-harness.js";

var storage = installLocalStorage();
var media = installMatchMedia();

function reset() {
  var html = document.documentElement;

  storage.clear();
  storage.set("yii-debug-toolbar-expanded", "1");
  html.className = "";
  html.removeAttribute("data-bs-theme");
  html.removeAttribute("data-theme");
  html.removeAttribute("data-yii-debug-theme");
  html.style.removeProperty("color-scheme");
  document.body.className = "";
  document.body.removeAttribute("data-theme");
  media.matches = false;
  resetHostThemeControlCache();
}

function payload() {
  return toolbarPayload({
    items: [
      {
        id: "db",
        items: [{ value: "3" }],
        title: "Database",
        url: "/debug/db",
      },
    ],
  });
}

/** Adds a host theme switcher jsdom would otherwise report as invisible. */
function installHostThemeControl() {
  var control = document.createElement("button");

  control.clicks = 0;
  control.setAttribute("aria-label", "Toggle theme");
  control.getClientRects = function () {
    return [{ height: 20, width: 20 }];
  };
  control.addEventListener("click", function () {
    control.clicks += 1;
  });
  document.body.appendChild(control);
  resetHostThemeControlCache();

  return control;
}

test("a theme marked on the document outvotes stale storage", () => {
  reset();
  storage.set("yii-debug-toolbar-theme", "dark");
  document.documentElement.className = "light";

  var element = renderToolbar(payload());

  assert.equal(element.theme, "light", "Rendered document state must win.");

  document.documentElement.className = "";
  document.body.setAttribute("data-theme", "dark");

  assert.equal(
    element.detectTheme(),
    "dark",
    "The body marker must be honoured.",
  );

  element.remove();
  reset();
});

test("a host-owned theme reads the rendered color scheme", () => {
  reset();
  storage.set("yii-debug-toolbar-theme", "dark");

  var control = installHostThemeControl();
  var element = renderToolbar(payload());

  assert.equal(
    element.ownsTheme,
    false,
    "A visible switcher must claim the theme.",
  );
  assert.equal(
    element.theme,
    "light",
    "An unmarked document is the host's light state.",
  );

  document.documentElement.style.colorScheme = "dark";
  resetHostThemeControlCache();

  assert.equal(
    element.detectTheme(),
    "dark",
    "A declared color scheme must be honoured.",
  );

  control.remove();
  element.remove();
  reset();
});

test("an owned theme prefers the pinned attribute, then its own key, then host keys", () => {
  reset();

  var element = renderToolbar(payload(), { "data-yii-debug-theme": "dark" });

  assert.equal(element.theme, "dark", "The pinned attribute must win.");

  element.removeAttribute("data-yii-debug-theme");
  storage.set("yii-debug-toolbar-theme", "dark");

  assert.equal(
    element.detectTheme(),
    "dark",
    "The toolbar key must be honoured.",
  );

  storage.delete("yii-debug-toolbar-theme");
  storage.set("vite-ui-theme", "dark");

  assert.equal(element.detectTheme(), "dark", "A host key must be honoured.");

  element.remove();
  reset();
});

test("an owned theme falls back to the system preference and then to light", () => {
  reset();
  media.matches = true;

  var element = renderToolbar(payload());

  assert.equal(
    element.theme,
    "dark",
    "A dark system preference must be adopted.",
  );

  media.matches = false;
  storage.delete("yii-debug-toolbar-theme");
  removeMatchMedia();

  assert.equal(
    element.detectTheme(),
    "light",
    "Without a media query light is the default.",
  );

  installMatchMedia();
  element.remove();
  reset();
});

test("toggling pins the theme, persists it and fans it out to the host", () => {
  reset();

  var element = renderToolbar(payload());
  var html = document.documentElement;

  element.shadowRoot.querySelector(".toggle-theme").click();

  assert.equal(
    element.getAttribute("data-theme"),
    "dark",
    "Host attribute must be flipped.",
  );
  assert.equal(
    element.getAttribute("data-yii-debug-theme"),
    "dark",
    "Explicit choice must be pinned.",
  );
  assert.equal(
    storage.get("yii-debug-toolbar-theme"),
    "dark",
    "Choice must be persisted.",
  );
  assert.ok(
    document.cookie.indexOf("yii-debug-toolbar-theme=dark") !== -1,
    "Cookie must carry the choice to the backend.",
  );
  assert.equal(
    html.classList.contains("dark"),
    true,
    "Tailwind class must be added.",
  );
  assert.equal(
    html.classList.contains("light"),
    false,
    "Opposite class must be removed.",
  );
  assert.equal(
    html.getAttribute("data-bs-theme"),
    "dark",
    "Bootstrap marker must be set.",
  );
  assert.equal(
    html.style.colorScheme,
    "dark",
    "Color scheme must be declared.",
  );
  assert.equal(
    storage.get("vueuse-color-scheme"),
    "dark",
    "Host keys must be written.",
  );
  assert.equal(
    element.shadowRoot.activeElement,
    element.shadowRoot.querySelector(".toggle-theme"),
    "Focus must stay on the toggle.",
  );
  assert.equal(
    element.shadowRoot.querySelector(".toggle-theme").getAttribute("title"),
    "Switch to light theme",
    "Toggle must now offer the opposite theme.",
  );

  element.shadowRoot.querySelector(".toggle-theme").click();

  assert.equal(element.theme, "light", "A second toggle must return to light.");
  assert.equal(
    html.classList.contains("light"),
    true,
    "Tailwind class must flip back.",
  );
  assert.equal(
    html.classList.contains("dark"),
    false,
    "Dark class must be removed.",
  );

  element.remove();
  reset();
});

test("a host switcher receives the toggle instead of the document", () => {
  reset();

  var control = installHostThemeControl();
  var element = renderToolbar(payload());

  element.shadowRoot.querySelector(".toggle-theme").click();

  assert.equal(control.clicks, 1, "The host control must be driven.");
  assert.equal(
    element.getAttribute("data-yii-debug-theme"),
    null,
    "No choice may be pinned on the toolbar.",
  );
  assert.equal(
    document.documentElement.getAttribute("data-bs-theme"),
    null,
    "The document must be left to the host.",
  );

  element.theme = "light";
  document.documentElement.setAttribute("data-theme", "dark");
  element.toggleTheme();

  assert.equal(
    control.clicks,
    1,
    "A host already showing the target must not be clicked.",
  );

  control.remove();
  element.remove();
  reset();
});

test("a host without a usable document element still absorbs the propagation", () => {
  reset();

  var element = renderToolbar(payload());
  var descriptor = Object.getOwnPropertyDescriptor(
    Document.prototype,
    "documentElement",
  );

  try {
    Object.defineProperty(document, "documentElement", {
      configurable: true,
      value: null,
    });
    element.propagateThemeToHost("dark");

    assert.equal(
      storage.get("theme"),
      "dark",
      "Storage keys must still be written.",
    );

    Object.defineProperty(document, "documentElement", {
      configurable: true,
      value: { setAttribute() {}, style: {} },
    });
    element.propagateThemeToHost("light");

    assert.equal(
      storage.get("theme"),
      "light",
      "Storage keys must still be written.",
    );
  } finally {
    delete document.documentElement;
    Object.defineProperty(Document.prototype, "documentElement", descriptor);
  }

  assert.ok(document.documentElement, "The document element must be restored.");

  element.remove();
  reset();
});

test("an unchanged theme is neither rewritten nor re-rendered", () => {
  reset();

  var element = renderToolbar(payload());
  var bar = element.shadowRoot.querySelector(".panels");

  storage.delete("yii-debug-toolbar-theme");
  element.refreshTheme();

  assert.equal(
    storage.get("yii-debug-toolbar-theme"),
    undefined,
    "An unchanged theme must not be persisted again.",
  );
  assert.equal(
    element.shadowRoot.querySelector(".panels"),
    bar,
    "An unchanged theme must not redraw the bar.",
  );

  element.remove();
  reset();
});

test("a theme change on the host redraws the bar", async () => {
  reset();

  var element = renderToolbar(payload());
  var bar = element.shadowRoot.querySelector(".panels");

  document.documentElement.setAttribute("data-theme", "dark");

  await new Promise(function (resolve) {
    setTimeout(resolve, 0);
  });

  assert.equal(element.theme, "dark", "The observer must follow the host.");
  assert.notEqual(
    element.shadowRoot.querySelector(".panels"),
    bar,
    "The bar must be redrawn for the new theme.",
  );

  element.remove();
  reset();
});

test("the panel frame flips the theme through postMessage", () => {
  reset();

  var element = renderToolbar(payload());

  element.openPanel("/debug/db");

  var frameWindow = stubFrameWindow(
    element.shadowRoot.querySelector(".drawer iframe"),
  );

  window.dispatchEvent(
    new window.MessageEvent("message", {
      data: { source: "yii-debug-toolbar", theme: "dark", type: "theme" },
      origin: window.location.origin,
      source: frameWindow,
    }),
  );

  assert.equal(element.theme, "dark", "The flip must be applied on the host.");
  assert.equal(
    element.getAttribute("data-yii-debug-theme"),
    "dark",
    "The flip must be pinned.",
  );
  assert.equal(
    document.documentElement.getAttribute("data-bs-theme"),
    "dark",
    "The flip must reach the surrounding page.",
  );

  element.remove();
  reset();
});

test("the panel frame closes the drawer through postMessage", () => {
  reset();

  var element = renderToolbar(payload());

  element.openPanel("/debug/db");

  var frameWindow = stubFrameWindow(
    element.shadowRoot.querySelector(".drawer iframe"),
  );

  window.dispatchEvent(
    new window.MessageEvent("message", {
      data: { source: "yii-debug-toolbar", type: "close-drawer" },
      origin: window.location.origin,
      source: frameWindow,
    }),
  );

  assert.equal(element.drawerOpen, false, "The drawer must close.");

  element.remove();
  reset();
});

test("foreign, unknown and redundant messages are ignored", () => {
  reset();

  var element = renderToolbar(payload());

  window.dispatchEvent(
    new window.MessageEvent("message", {
      data: { source: "yii-debug-toolbar", theme: "dark", type: "theme" },
      origin: window.location.origin,
    }),
  );

  assert.equal(
    element.theme,
    "light",
    "A message without the panel frame must be ignored.",
  );

  element.openPanel("/debug/db");

  var frameWindow = stubFrameWindow(
    element.shadowRoot.querySelector(".drawer iframe"),
  );

  function post(data, origin) {
    window.dispatchEvent(
      new window.MessageEvent("message", {
        data: data,
        origin: origin || window.location.origin,
        source: frameWindow,
      }),
    );
  }

  post(
    { source: "yii-debug-toolbar", theme: "dark", type: "theme" },
    "https://evil.test",
  );

  assert.equal(
    element.theme,
    "light",
    "A cross-origin message must be ignored.",
  );

  post({ source: "other-widget", theme: "dark", type: "theme" });

  assert.equal(element.theme, "light", "A foreign sender must be ignored.");

  post({ source: "yii-debug-toolbar", theme: "chartreuse", type: "theme" });

  assert.equal(element.theme, "light", "An unknown theme must be ignored.");

  post({ source: "yii-debug-toolbar", theme: "light", type: "theme" });

  assert.equal(
    element.theme,
    "light",
    "The theme already shown must be ignored.",
  );

  element.remove();
  reset();
});

test("a theme message reaches the host switcher when one exists", () => {
  reset();

  var element = renderToolbar(payload());

  element.openPanel("/debug/db");

  var control = installHostThemeControl();
  var frameWindow = stubFrameWindow(
    element.shadowRoot.querySelector(".drawer iframe"),
  );

  window.dispatchEvent(
    new window.MessageEvent("message", {
      data: { source: "yii-debug-toolbar", theme: "dark", type: "theme" },
      origin: window.location.origin,
      source: frameWindow,
    }),
  );

  assert.equal(control.clicks, 1, "The host control must be driven.");
  assert.equal(
    element.getAttribute("data-yii-debug-theme"),
    null,
    "No choice may be pinned on the toolbar.",
  );

  control.remove();
  element.remove();
  reset();
});

test("a theme message before the first snapshot only records the theme", () => {
  reset();

  var element = renderToolbar(payload());

  element.openPanel("/debug/db");

  var frameWindow = stubFrameWindow(
    element.shadowRoot.querySelector(".drawer iframe"),
  );
  var bar = element.shadowRoot.querySelector(".panels");

  element.data = null;
  window.dispatchEvent(
    new window.MessageEvent("message", {
      data: { source: "yii-debug-toolbar", theme: "dark", type: "theme" },
      origin: window.location.origin,
      source: frameWindow,
    }),
  );

  assert.equal(element.theme, "dark", "The theme must still be recorded.");
  assert.equal(
    element.shadowRoot.querySelector(".panels"),
    bar,
    "Without a payload the bar must not be redrawn.",
  );

  element.remove();
  reset();
});
