import {
  readStorageItem,
  requestStack,
  sameUrl,
  storageKey,
  toolbars,
} from "./state.js";
import { createToolbarDrawer } from "./drawer.js";
import { splitToolbarPanels } from "./extensions.js";
import { createToolbarLoader } from "./loader.js";
import { resolveToolbarLoadRollback, toolbarDataUrlForTag } from "./loading.js";
import { normalizeToolbarPosition } from "./position.js";
import {
  createToolbarView,
  renderAjaxPanel,
  renderBrand,
  renderCollapsedOpener,
  renderControls,
  renderErrorMessage,
  renderExtensions,
  renderPanel,
  renderPanels,
} from "./render.js";
import { createToolbarThemeController } from "./theme-controller.js";
import { normalizeToolbarUrl } from "./url.js";

/**
 * Styles injected into the toolbar's open shadow DOM. Authored in
 * toolbar-shadow.css (shared design tokens via tokens.css) and inlined into
 * this bundle at build time so the Web Component remains self-contained and
 * isolated from the host page's CSS.
 */
import toolbarStyles from "./toolbar-shadow.css?inline";

/**
 * The `<yii-debug-toolbar>` custom element.
 *
 * The element owns the view state the adapter payload renders from, the shadow
 * skeleton and the render cycle; every other responsibility belongs to a
 * controller it composes and whose lifecycle it drives:
 *
 *   - loader.js           one load lifecycle per connection.
 *   - theme-controller.js theme resolution and the host signals that flip it.
 *   - drawer.js           the panel drawer, its resize affordances and the menu.
 *   - render.js           the stateless HTML builders the render cycle calls.
 */
export function YiiDebugToolbar() {
  var self = Reflect.construct(HTMLElement, [], YiiDebugToolbar);
  self.attachShadow({ mode: "open" });
  self.data = null;
  self.ajaxRequests = requestStack;
  self.activeUrl = "";
  self.expanded = readStorageItem(storageKey) === "1";
  self.drawerOpen = false;
  /* Menu state is per page view: never persisted, never restored. */
  self.extensionsOpen = false;
  self.restoreFocusUrl = null;
  self.resizing = false;
  self.currentTag = null;
  /* Replaced by a fresh one on every connect; see `connectedCallback()`. */
  self.loader = createToolbarLoader(self);
  self.drawer = createToolbarDrawer(self);
  self.themeController = createToolbarThemeController(self);
  self.toolbarRoot = null;
  self.barRoot = null;
  self.drawerRoot = null;
  self.drawerPosition = null;
  self.theme = null;

  return self;
}

YiiDebugToolbar.prototype = Object.create(HTMLElement.prototype);
YiiDebugToolbar.prototype.constructor = YiiDebugToolbar;
Object.setPrototypeOf(YiiDebugToolbar, HTMLElement);

YiiDebugToolbar.prototype.connectedCallback = function () {
  if (toolbars.indexOf(this) === -1) {
    toolbars.push(this);
  }

  this.drawer.bind();
  this.themeController.start();

  /**
   * One lifecycle per connection: whatever the previous one still had in
   * flight is invalidated before the new controller takes over.
   */
  this.loader.dispose();
  (this.loader = createToolbarLoader(this)).load();
};

YiiDebugToolbar.prototype.disconnectedCallback = function () {
  var index = toolbars.indexOf(this);
  if (index !== -1) {
    toolbars.splice(index, 1);
  }

  this.loader.dispose();
  this.themeController.dispose();
  this.drawer.dispose();
};

YiiDebugToolbar.prototype.setAjaxRequests = function (requests) {
  this.ajaxRequests = requests;

  if (!this.data || !this.expanded || !this.barRoot) {
    return;
  }

  var current = this.barRoot.querySelector(".ajax-panel");

  if (!current) {
    this.render();

    return;
  }

  var staging = document.createElement("div");
  staging.innerHTML = this.renderAjaxPanel();

  if (staging.firstElementChild) {
    current.replaceWith(staging.firstElementChild);
  }
};

YiiDebugToolbar.prototype.normalizeUrl = function (url) {
  return normalizeToolbarUrl(url);
};

YiiDebugToolbar.prototype.followTag = function (tag) {
  if (!tag || this.currentTag === tag) {
    return;
  }

  var url = this.normalizeUrl(this.getAttribute("data-url"));

  if (!url) {
    return;
  }

  var previousUrl = url;
  var previousTag = this.currentTag;
  var nextUrl = toolbarDataUrlForTag(url, tag, window.location.href);

  if (!nextUrl || sameUrl(url, nextUrl)) {
    return;
  }

  this.currentTag = tag;
  this.setAttribute("data-url", nextUrl);
  this.load(function (ok) {
    if (ok) {
      return;
    }

    /**
     * The tag we tried to follow was rejected (404 — rotated out of history,
     * 500, etc.). Roll back so the toolbar keeps showing the last good data
     * instead of leaving the user with a broken state.
     */
    var rollback = resolveToolbarLoadRollback(
      this.loader.lastUrl,
      this.loader.lastTag,
      previousUrl,
      previousTag,
    );

    this.currentTag = rollback.tag;
    this.setAttribute("data-url", rollback.url);

    if (rollback.reload) {
      this.load();
    } else {
      this.render();
    }
  });
};

/**
 * Loads the snapshot at `data-url` through the controller the element
 * lifecycle owns. A detached element keeps its disposed controller, so the
 * call stays inert until the element is connected again.
 */
YiiDebugToolbar.prototype.load = function (done) {
  this.loader.load(done);
};

YiiDebugToolbar.prototype.dispatchAttachedEvent = function () {
  var event;

  if (typeof Event === "function") {
    event = new Event("yii.debug.toolbar_attached", { bubbles: true });
  } else {
    event = document.createEvent("Event");
    event.initEvent("yii.debug.toolbar_attached", true, true);
  }

  this.dispatchEvent(event);
};

YiiDebugToolbar.prototype.ensureShadowSkeleton = function () {
  if (this.toolbarRoot) {
    return;
  }

  var style = document.createElement("style");
  style.textContent = toolbarStyles;
  this.shadowRoot.appendChild(style);

  this.toolbarRoot = document.createElement("div");
  this.barRoot = document.createElement("div");
  this.drawerRoot = document.createElement("div");
  this.barRoot.className = "bar";
  this.drawerRoot.style.display = "contents";
  this.toolbarRoot.appendChild(this.barRoot);
  this.toolbarRoot.appendChild(this.drawerRoot);
  this.shadowRoot.appendChild(this.toolbarRoot);

  // Retain the historical internal alias for adapter-side diagnostics.
  this.contentRoot = this.toolbarRoot;

  this.drawer.bindDelegatedEvents();
};

YiiDebugToolbar.prototype.renderError = function (message) {
  this.ensureShadowSkeleton();
  this.toolbarRoot.className = "toolbar expanded";
  this.barRoot.innerHTML = renderErrorMessage(message);
  this.drawerRoot.innerHTML = "";
  this.drawerPosition = null;
};

/**
 * Renders the AJAX chip the tracker refreshes on its own, out of the render
 * cycle: `setAjaxRequests()` swaps this fragment in place so the surrounding
 * bar keeps its nodes.
 */
YiiDebugToolbar.prototype.renderAjaxPanel = function () {
  return renderAjaxPanel(createToolbarView(this));
};

YiiDebugToolbar.prototype.getPosition = function () {
  return normalizeToolbarPosition(
    this.getAttribute("data-position") || (this.data && this.data.position),
  );
};

YiiDebugToolbar.prototype.render = function () {
  if (!this.data) {
    return;
  }

  this.ensureShadowSkeleton();

  var position = this.getPosition();
  this.setAttribute("data-position", position);
  var classes = ["toolbar", "position-" + position];

  if (this.expanded) {
    classes.push("expanded");
  }
  if (this.drawerOpen) {
    classes.push("drawer-open");
  }

  var view = createToolbarView(this);
  var profilingPanel = (this.data.items || []).find(function (p) {
    return p && p.id === "profiling";
  });
  var profilingChip = profilingPanel ? renderPanel(view, profilingPanel) : "";
  var split = splitToolbarPanels(this.data.items, ["profiling"]);

  this.toolbarRoot.className = classes.join(" ");
  this.barRoot.innerHTML = this.expanded
    ? renderBrand(view) +
      profilingChip +
      this.renderAjaxPanel() +
      renderPanels(view, split.inline) +
      renderExtensions(view, split.extensions) +
      renderControls(view)
    : renderCollapsedOpener(view);
  this.drawer.syncDrawer(view, position);

  this.drawer.bindEvents();
  this.drawer.applyDrawerHeight();
};

YiiDebugToolbar.prototype.getStyles = function () {
  return toolbarStyles;
};
