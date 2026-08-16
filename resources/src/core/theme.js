export const THEME_PARAM = "yii_debug_theme";
export const THEME_STORAGE_KEY = "yii-debug-toolbar-theme";

export function normalizeTheme(value) {
  if (!value) {
    return null;
  }

  const aliases = String(value).toLowerCase().trim().split(/\s+/);
  const hasDark = aliases.some((alias) =>
    ["dark", "night", "black"].includes(alias),
  );
  const hasLight = aliases.some((alias) =>
    ["light", "day", "white"].includes(alias),
  );

  if (hasDark === hasLight) {
    return null;
  }

  return hasDark ? "dark" : "light";
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
    document.cookie = `${THEME_STORAGE_KEY}=${encodeURIComponent(normalized)};path=/;max-age=31536000;SameSite=Lax`;
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
