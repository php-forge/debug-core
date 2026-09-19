/**
 * Pure helpers backing the bar menus: the AJAX inspector and the Extensions
 * group. Each folds a list behind one chip at the end of the bar, and the
 * drawer controller drives their shared lifecycle through these definitions.
 *
 * Only one menu is open at a time; the element records which one in
 * `openMenu`, so the two can never be open together.
 */

/**
 * Selectors every menu is wired with, keyed by the name `openMenu` records.
 */
export var toolbarMenus = Object.freeze({
  ajax: Object.freeze({
    menu: ".ajax-menu",
    toggle: ".ajax-toggle",
    wrapper: ".ajax",
  }),
  extensions: Object.freeze({
    menu: ".extensions-menu",
    toggle: ".extensions-toggle",
    wrapper: ".extensions",
  }),
});

/**
 * Returns the definition of a menu, or `null` for a name the bar does not
 * render, such as an attribute a stale toggle still carries.
 */
export function toolbarMenu(name) {
  return Object.prototype.hasOwnProperty.call(toolbarMenus, name)
    ? toolbarMenus[name]
    : null;
}

/**
 * Returns `true` for an unhandled Escape that must close an open menu.
 *
 * Mirrors `shouldCloseToolbarDrawer()`; the menu answers first so a single
 * Escape never closes both the menu and the drawer.
 */
export function shouldCloseToolbarMenu(event, open) {
  return Boolean(
    open && event && event.key === "Escape" && !event.defaultPrevented,
  );
}

/**
 * Returns whether an event target sits inside the wrapper of `menu`.
 *
 * The `closest` helper is injected so the check stays free of a live DOM.
 */
export function isInsideToolbarMenu(target, menu, closestFn) {
  return closestFn(target, menu.wrapper) !== null;
}
