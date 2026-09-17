/**
 * Dependency-light helpers shared by the page bundle and the toolbar bundle.
 *
 * Both entries import this module, so the bundler emits it once as the
 * `js/shared.min.js` chunk instead of inlining a copy in each entry. Keep it
 * free of imports and of module-level side effects: the toolbar's `state.js`
 * captures `window.fetch` and `XMLHttpRequest.prototype.open` while it is
 * evaluated, which is exactly what page code must never pull in.
 */

/** Storage key and cookie name the debugger persists the chosen theme under. */
export const THEME_STORAGE_KEY = "yii-debug-toolbar-theme";

/**
 * Host application storage keys the toolbar reads when it has to follow the
 * surrounding page's theme, most specific convention first.
 */
export const HOST_THEME_STORAGE_KEYS = [
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
];

/**
 * Subset of host storage keys the toolbar writes back when it owns the theme.
 *
 * Deliberately narrower than {@link HOST_THEME_STORAGE_KEYS}: reading a stale
 * key is harmless, writing one the host never owned is not.
 */
export const HOST_THEME_PROPAGATION_KEYS = [
  "theme",
  "color-theme",
  "color-scheme",
  "vueuse-color-scheme",
  "vite-ui-theme",
];

/**
 * Reduces any host theme signal to `"dark"`, `"light"` or `null`.
 *
 * A value carrying both families (or neither) is ambiguous and resolves to
 * `null` so the caller falls through to the next, less authoritative source.
 */
export function normalizeThemeToken(value) {
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

/**
 * Builds the `document.cookie` assignment persisting the theme for a year.
 *
 * The backend resolves the theme from this cookie, so panel pages reached
 * without a `?yii_debug_theme=` query still render what the client last chose.
 */
export function themeCookie(value) {
  return `${THEME_STORAGE_KEY}=${encodeURIComponent(value)};path=/;max-age=31536000;SameSite=Lax`;
}

/**
 * Walks up from `element` to the nearest ancestor matching `selector`.
 *
 * Text nodes start at their parent element, so an event target inside a label
 * resolves the same way a click on the element itself does.
 */
export function closest(element, selector) {
  if (element && element.nodeType !== 1) {
    element = element.parentElement;
  }

  while (element && element.nodeType === 1) {
    if (element.matches(selector)) {
      return element;
    }
    element = element.parentElement;
  }

  return null;
}
