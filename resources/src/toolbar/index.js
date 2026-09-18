/**
 * Floating debug toolbar entry point. Wires the Web Component class and the
 * AJAX tracker together; the heavy logic lives in the sibling modules:
 *
 *   - element.js          the YiiDebugToolbar HTMLElement: view state, the
 *                         shadow skeleton, the render cycle, and the lifecycle
 *                         of the controllers below (+ shadow DOM styles).
 *   - loader.js           one snapshot load lifecycle per connection.
 *   - theme-controller.js theme resolution, the host signals that flip it, and
 *                         the listeners disposal releases.
 *   - drawer.js           the panel drawer, its resize affordances, and the
 *                         Extensions menu.
 *   - render.js           stateless HTML builders the render cycle composes.
 *   - state.js            module state, constants, and pure utility helpers.
 *   - theme.js            theme detection, normalization, and URL/cookie
 *                         stamping.
 *   - messaging.js        XMLHttpRequest / fetch interception that feeds the
 *                         chips.
 *
 * The remaining modules (brand, extensions, focus, icons, loading, panel,
 * position, url) hold the pure helpers those modules call.
 */

import { YiiDebugToolbar } from "./element.js";
import { trackRequests } from "./messaging.js";
import { tagName } from "./state.js";

/**
 * Guard against duplicate registration when the toolbar script ends up in
 * the page more than once (e.g. multi-frame layouts that include both the
 * mini-bar and a panel iframe). The custom element name is the canonical
 * lock — once it's defined, the rest of the bootstrap is a no-op aside from
 * making sure AJAX tracking is in place.
 */
if (window.customElements && !window.customElements.get(tagName)) {
  window.customElements.define(tagName, YiiDebugToolbar);
}

trackRequests();
