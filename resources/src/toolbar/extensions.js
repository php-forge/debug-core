/**
 * Pure helpers backing the toolbar "Extensions" menu.
 *
 * Provider-owned and optional panels carry `extension: true` in the toolbar
 * payload; the key is absent for built-ins. They are folded into a single chip
 * at the end of the bar so the inline gauge strip keeps its fixed vocabulary
 * no matter how many extensions an application registers.
 */

/**
 * Splits the toolbar payload into the chips rendered inline and the ones the
 * Extensions menu groups, preserving payload order in both lists.
 *
 * @returns {{inline: Array, extensions: Array}} Inline and extension panels.
 */
export function splitToolbarPanels(items, excludeIds) {
  var inline = [];
  var extensions = [];

  (items || []).forEach(function (panel) {
    if (!panel || (excludeIds && excludeIds.indexOf(panel.id) !== -1)) {
      return;
    }

    if (panel.extension === true) {
      extensions.push(panel);

      return;
    }

    inline.push(panel);
  });

  return { inline: inline, extensions: extensions };
}

/**
 * Returns the badge status for the Extensions counter, so a hidden failure
 * still surfaces on the collapsed chip.
 *
 * @returns {string} `"danger"` when any grouped panel reports it.
 */
export function extensionsBadgeStatus(extensions) {
  var danger = extensions.some(function (panel) {
    var items = panel.items;

    return (
      Boolean(items) &&
      items.some(function (item) {
        return item.status === "danger";
      })
    );
  });

  return danger ? "danger" : "default";
}

/**
 * Returns `true` for an unhandled Escape that must close an open menu.
 *
 * Mirrors `shouldCloseToolbarDrawer()`; the menu answers first so a single
 * Escape never closes both the menu and the drawer.
 */
export function shouldCloseExtensionsMenu(event, open) {
  return Boolean(
    open && event && event.key === "Escape" && !event.defaultPrevented,
  );
}

/**
 * Returns whether an event target sits inside the Extensions wrapper.
 *
 * The `closest` helper is injected so the check stays free of a live DOM.
 */
export function isInsideExtensions(target, closestFn) {
  return closestFn(target, ".extensions") !== null;
}
