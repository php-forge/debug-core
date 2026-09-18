import {
  normalizeThemeToken as normalizeTheme,
  THEME_STORAGE_KEY,
  themeCookie,
} from "./shared.js";

export const THEME_PARAM = "yii_debug_theme";

export { normalizeTheme, THEME_STORAGE_KEY };

export function themeToggleLabel(theme) {
  return normalizeTheme(theme) === "dark"
    ? "Switch to light theme"
    : "Switch to dark theme";
}

export function bindThemeToggle(button, onThemeChange) {
  if (!button) {
    return;
  }

  const icon = button.querySelector(".yii-debug-brand-icon");
  const updateToggle = (theme) => {
    const normalized = normalizeTheme(theme) || "light";
    const label = themeToggleLabel(normalized);

    button.setAttribute("data-current-theme", normalized);
    button.setAttribute("aria-label", label);
    button.setAttribute(
      "aria-pressed",
      normalized === "dark" ? "true" : "false",
    );
    button.setAttribute("title", label);

    if (icon) {
      icon.innerHTML =
        normalized === "dark"
          ? button.getAttribute("data-icon-sun")
          : button.getAttribute("data-icon-moon");
    }
  };
  const initial = normalizeTheme(
    document.documentElement.getAttribute("data-yii-debug-theme"),
  );

  updateToggle(initial);

  button.addEventListener("click", () => {
    const current = normalizeTheme(
      document.documentElement.getAttribute("data-yii-debug-theme"),
    );
    const next = current === "dark" ? "light" : "dark";

    document.documentElement.setAttribute("data-yii-debug-theme", next);
    updateToggle(next);
    writeTheme(next);
    preserveThemeInLinks(next);

    if (onThemeChange) {
      onThemeChange(next);
    }
  });
}

export function readStoredTheme(key = THEME_STORAGE_KEY) {
  try {
    return window.localStorage
      ? normalizeTheme(localStorage.getItem(key))
      : null;
  } catch {
    return null;
  }
}

export function readThemeCookie() {
  const prefix = `${THEME_STORAGE_KEY}=`;
  const cookie = (document.cookie || "")
    .split(";")
    .map((part) => part.trim())
    .find((part) => part.startsWith(prefix));

  if (!cookie) {
    return null;
  }

  try {
    return normalizeTheme(decodeURIComponent(cookie.slice(prefix.length)));
  } catch {
    return null;
  }
}

export function writeTheme(theme) {
  const normalized = normalizeTheme(theme);

  if (!normalized) {
    return;
  }

  try {
    localStorage.setItem(THEME_STORAGE_KEY, normalized);
  } catch {
    // Storage can be unavailable in private or sandboxed browsing contexts.
  }

  try {
    document.cookie = themeCookie(normalized);
  } catch {
    // Cookie writes can be blocked by the browser or iframe sandbox.
  }
}

function parseDebugUrl(url) {
  let parsed;

  try {
    parsed = new URL(url, window.location.href);
  } catch {
    return null;
  }

  if (parsed.origin !== window.location.origin) {
    return null;
  }

  const route = parsed.searchParams.get("r") || "";

  if (
    !parsed.pathname.includes("/debug/") &&
    !parsed.pathname.endsWith("/debug") &&
    !route.startsWith("debug/") &&
    !route.startsWith("debug%2F")
  ) {
    return null;
  }

  return parsed;
}

export function addThemeToDebugUrl(url, theme) {
  const normalized = normalizeTheme(theme);

  if (!normalized) {
    return url;
  }

  const parsed = parseDebugUrl(url);

  if (!parsed) {
    return url;
  }

  parsed.searchParams.set(THEME_PARAM, normalized);

  return parsed.href;
}

export function preserveThemeInLinks(theme) {
  const links = document.querySelectorAll("a[href]");
  const forms = document.querySelectorAll("form[action]");
  let input;

  for (let i = 0; i < links.length; i++) {
    const href = links[i].getAttribute("href");

    if (href && !href.startsWith("#") && !href.startsWith("javascript:")) {
      links[i].setAttribute("href", addThemeToDebugUrl(href, theme));
    }
  }

  for (let i = 0; i < forms.length; i++) {
    const action = forms[i].getAttribute("action") || window.location.href;
    const isDebugAction = parseDebugUrl(action) !== null;

    forms[i].setAttribute("action", addThemeToDebugUrl(action, theme));

    if (
      !isDebugAction ||
      (forms[i].getAttribute("method") || "get").toLowerCase() !== "get"
    ) {
      continue;
    }

    input = forms[i].querySelector(`input[name="${THEME_PARAM}"]`);

    if (!input) {
      input = document.createElement("input");
      input.type = "hidden";
      input.name = THEME_PARAM;
      forms[i].appendChild(input);
    }

    input.value = theme;
  }
}

/**
 * Reads the theme of the toolbar hosting this page in its drawer iframe.
 *
 * The frame lives inside the toolbar's shadow root, so the host element of that
 * root carries the live authority for the page rendered inside it.
 */
function getParentToolbarTheme() {
  let root;
  let host;

  try {
    if (!window.frameElement) {
      return null;
    }

    root = window.frameElement.getRootNode
      ? window.frameElement.getRootNode()
      : null;
    host = root && root.host ? root.host : null;

    return host ? normalizeTheme(host.getAttribute("data-theme")) : null;
  } catch {
    return null;
  }
}

/** Reads the theme a deep link froze into the address bar. */
function getUrlTheme() {
  try {
    return normalizeTheme(
      new URL(window.location.href).searchParams.get(THEME_PARAM),
    );
  } catch {
    return null;
  }
}

/**
 * Resolves the theme the debugger page must render, marks the document with it
 * and mirrors the choice to the client memory the next page reads.
 *
 * @returns {string} The applied theme.
 */
export function applyTheme() {
  // Priority is "what the client most recently chose, regardless of stack":
  //   1. Parent toolbar theme (drawer iframe) — the live authority NOW.
  //   2. Cookie (last client write — survives reloads + backend staleness).
  //   3. localStorage fallback (cookie may be blocked in some sandboxes).
  //   4. Explicit `?yii_debug_theme=` query — deep links with no client
  //      state yet. The query is a snapshot frozen at link-render time,
  //      so it must NEVER outrank a later client choice: that is exactly
  //      how a stale `dark` link used to revert a fresh `light` pick.
  //   5. Server-rendered `data-yii-debug-theme` attribute.
  //   6. `prefers-color-scheme` media query as the very last resort.
  const theme =
    getParentToolbarTheme() ||
    readThemeCookie() ||
    readStoredTheme() ||
    getUrlTheme() ||
    normalizeTheme(
      document.documentElement.getAttribute("data-yii-debug-theme"),
    ) ||
    (window.matchMedia &&
    window.matchMedia("(prefers-color-scheme: dark)").matches
      ? "dark"
      : "light");

  document.documentElement.setAttribute("data-yii-debug-theme", theme);

  writeTheme(theme);

  return theme;
}

/**
 * Binds the page's theme switcher and reports every flip to the toolbar hosting
 * the page, so the drawer and its host stay on the same theme.
 */
export function bindThemeToggleButton() {
  bindThemeToggle(
    document.querySelector("[data-yii-debug-theme-toggle]"),
    function (next) {
      if (window.parent && window.parent !== window) {
        window.parent.postMessage(
          { source: "yii-debug-toolbar", type: "theme", theme: next },
          window.location.origin,
        );
      }
    },
  );
}
