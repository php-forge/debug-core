import { closest, storageKey, writeStorageItem } from "./state.js";
import { isInsideExtensions, shouldCloseExtensionsMenu } from "./extensions.js";
import {
  focusToolbarElement,
  focusToolbarTrigger,
  shouldCloseToolbarDrawer,
} from "./focus.js";
import { shouldOpenToolbarDrawer } from "./panel.js";
import { toolbarDrawerHeight, toolbarDrawerHeightForKey } from "./position.js";
import { renderDrawer } from "./render.js";
import { sameToolbarUrl } from "./url.js";

/** Selects the drawer inside the shadow root. */
var drawerSelector = ".drawer";

/** Custom property the drawer publishes its resolved height through. */
var drawerHeightProperty = "--yii-debug-toolbar-drawer-height";

/** Smallest drawer height a pointer or keyboard resize may leave behind. */
var minimumDrawerHeight = 120;

/**
 * Owns the interactive layers of the toolbar: the panel drawer, its resize
 * affordances and the Extensions menu.
 *
 * `bind()` attaches the document-level listener that dismisses the menu, and
 * `dispose()` releases it together with whatever a drag in flight still holds.
 * Shadow-root wiring stays with the render cycle: `bindDelegatedEvents()` runs
 * once on the skeleton and `bindEvents()` re-binds the controls each render.
 *
 * Usage example:
 *
 * ```js
 * var drawer = createToolbarDrawer(element);
 *
 * drawer.bind();
 * drawer.dispose();
 * ```
 */
export function createToolbarDrawer(toolbar) {
  var controller;

  /** Returns the laid-out viewport height, falling back to the root element. */
  function viewportHeight() {
    return window.innerHeight || document.documentElement.clientHeight;
  }

  function onPointerMove(event) {
    controller.onPointerMove(event);
  }

  function onPointerUp() {
    controller.onPointerUp();
  }

  /**
   * A pointer landing anywhere but the menu dismisses it. Registered on the
   * document by `bind()` and released by `dispose()`, so the registration
   * follows the element lifecycle.
   */
  function extensionsPointerDown(event) {
    if (!toolbar.extensionsOpen) {
      return;
    }

    /**
     * Listening on the document means `event.target` is retargeted to the
     * host element; the composed path still carries the real one.
     */
    var path =
      typeof event.composedPath === "function" ? event.composedPath() : [];
    var target = path.length > 0 ? path[0] : event.target;

    if (!isInsideExtensions(target, closest)) {
      controller.closeExtensions(false);
    }
  }

  controller = {
    /** Document handler dismissing the menu, exposed for the lifecycle tests. */
    extensionsPointerDown: extensionsPointerDown,
    /**
     * Applies the stored drawer height once, then republishes the resize
     * handle's value range.
     */
    applyDrawerHeight: function () {
      var drawer = toolbar.shadowRoot.querySelector(drawerSelector);

      if (!drawer) {
        return;
      }

      if (!toolbar.style.getPropertyValue(drawerHeightProperty)) {
        var height = parseInt(
          toolbar.getAttribute("data-height") ||
            toolbar.data.defaultHeight ||
            50,
          10,
        );
        toolbar.style.setProperty(
          drawerHeightProperty,
          Math.max(20, Math.min(90, height)) + "vh",
        );
      }

      controller.updateResizeHandleAccessibility();
    },
    /** Starts the lifecycle: the menu answers pointers landing outside it. */
    bind: function () {
      document.addEventListener("pointerdown", extensionsPointerDown, false);
    },
    /**
     * Binds the shadow-root delegation installed once on the skeleton: chips
     * open the drawer, the Extensions toggle flips the menu, and Escape closes
     * exactly one layer.
     */
    bindDelegatedEvents: function () {
      var root = toolbar.shadowRoot;

      root.addEventListener("click", function (event) {
        if (closest(event.target, ".extensions-toggle")) {
          event.preventDefault();
          event.stopPropagation();
          controller.toggleExtensions();

          return;
        }

        var target = closest(event.target, "[data-debug-url]");
        var url = target ? target.getAttribute("data-debug-url") : null;

        if (!shouldOpenToolbarDrawer(event, url)) {
          return;
        }

        event.preventDefault();
        event.stopPropagation();
        controller.openPanel(url);
      });

      root.addEventListener("keydown", function (event) {
        /**
         * The menu answers first, so a single Escape never collapses both the
         * menu and the drawer behind it.
         */
        if (shouldCloseExtensionsMenu(event, toolbar.extensionsOpen)) {
          event.preventDefault();
          event.stopPropagation();
          controller.closeExtensions(true);

          return;
        }

        if (!shouldCloseToolbarDrawer(event, toolbar.drawerOpen)) {
          return;
        }

        event.preventDefault();
        event.stopPropagation();
        controller.closeDrawer();
      });
    },
    /** Binds the bar controls a render replaced, and the resize handle once. */
    bindEvents: function () {
      var root = toolbar.shadowRoot;
      var toggle = root.querySelector(".toggle-toolbar");
      var toggleTheme = root.querySelector(".toggle-theme");
      var closeDrawer = root.querySelector(".close-drawer");
      var resizeHandle = root.querySelector(".resize-handle");

      if (toggle) {
        toggle.addEventListener("click", function () {
          controller.toggleExpanded();
        });
      }

      if (toggleTheme) {
        toggleTheme.addEventListener("click", function () {
          toolbar.themeController.toggleTheme();
        });
      }

      if (closeDrawer) {
        closeDrawer.addEventListener("click", function () {
          controller.closeDrawer();
        });
      }

      if (resizeHandle && !resizeHandle.__yiiDebugResizeEventsBound) {
        resizeHandle.__yiiDebugResizeEventsBound = true;
        resizeHandle.addEventListener("keydown", function (event) {
          controller.onResizeKeyDown(event);
        });
        resizeHandle.addEventListener(
          "pointerdown",
          function (event) {
            toolbar.resizing = true;
            event.preventDefault();
            document.addEventListener("pointermove", onPointerMove, false);
            document.addEventListener("pointerup", onPointerUp, false);
          },
          false,
        );
      }
    },
    /** Closes the drawer and returns focus to whatever opened it. */
    closeDrawer: function () {
      var restoreFocusUrl = toolbar.restoreFocusUrl;

      toolbar.drawerOpen = false;
      toolbar.extensionsOpen = false;
      toolbar.restoreFocusUrl = null;
      toolbar.render();

      if (!focusToolbarTrigger(toolbar.shadowRoot, restoreFocusUrl)) {
        focusToolbarElement(toolbar.shadowRoot, ".toggle-toolbar");
      }
    },
    closeExtensions: function (focusToggle) {
      toolbar.extensionsOpen = false;
      controller.syncExtensions();

      if (focusToggle) {
        focusToolbarElement(toolbar.shadowRoot, ".extensions-toggle");
      }
    },
    /**
     * Ends the lifecycle: the menu listener is released and a drag in flight is
     * abandoned, so a detached toolbar can no longer be resized.
     */
    dispose: function () {
      toolbar.resizing = false;
      document.removeEventListener("pointermove", onPointerMove, false);
      document.removeEventListener("pointerup", onPointerUp, false);
      document.removeEventListener("pointerdown", extensionsPointerDown, false);
    },
    onPointerMove: function (event) {
      if (!toolbar.resizing) {
        return;
      }

      var position = toolbar.getPosition();
      var drawer = toolbar.shadowRoot.querySelector(drawerSelector);
      var available = viewportHeight();
      var drawerRect = drawer ? drawer.getBoundingClientRect() : null;
      var height = toolbarDrawerHeight(
        position,
        event.clientY,
        available,
        drawerRect,
      );

      toolbar.style.setProperty(
        drawerHeightProperty,
        Math.max(minimumDrawerHeight, Math.min(available - 48, height)) + "px",
      );
      controller.updateResizeHandleAccessibility();
    },
    onPointerUp: function () {
      toolbar.resizing = false;
      document.removeEventListener("pointermove", onPointerMove, false);
      document.removeEventListener("pointerup", onPointerUp, false);
    },
    onResizeKeyDown: function (event) {
      var drawer = toolbar.shadowRoot.querySelector(drawerSelector);

      if (!drawer) {
        return;
      }

      var height = toolbarDrawerHeightForKey(
        toolbar.getPosition(),
        event.key,
        drawer.getBoundingClientRect().height,
        viewportHeight(),
      );

      if (height === null) {
        return;
      }

      event.preventDefault();
      toolbar.style.setProperty(drawerHeightProperty, height + "px");
      controller.updateResizeHandleAccessibility();
    },
    /** Opens a panel in the drawer, expanding the bar when it was collapsed. */
    openPanel: function (url) {
      var normalizedUrl = toolbar.normalizeUrl(url);

      if (!normalizedUrl) {
        return;
      }

      toolbar.expanded = true;
      toolbar.drawerOpen = true;
      toolbar.extensionsOpen = false;
      toolbar.activeUrl = normalizedUrl;
      toolbar.restoreFocusUrl = normalizedUrl;
      writeStorageItem(storageKey, "1");
      toolbar.render();
      focusToolbarElement(toolbar.shadowRoot, ".close-drawer");
    },
    /**
     * Keeps the drawer browsing context alive while the fast-changing toolbar
     * bar is refreshed for AJAX metrics. The iframe is created only when the
     * drawer opens and navigated only when its effective URL changes.
     */
    syncDrawer: function (view, position) {
      if (!toolbar.drawerOpen || !toolbar.activeUrl) {
        if (toolbar.drawerRoot.childNodes.length > 0) {
          toolbar.drawerRoot.innerHTML = "";
        }
        toolbar.drawerPosition = null;

        return;
      }

      var source = view.withTheme(toolbar.activeUrl);

      if (!source) {
        toolbar.drawerOpen = false;
        toolbar.activeUrl = "";
        toolbar.drawerRoot.innerHTML = "";
        toolbar.drawerPosition = null;

        return;
      }

      var frame = toolbar.drawerRoot.querySelector("iframe");

      if (!frame) {
        toolbar.drawerRoot.innerHTML = renderDrawer(view, position);
        toolbar.drawerPosition = position;

        return;
      }

      if (!sameToolbarUrl(frame.getAttribute("src"), source)) {
        frame.setAttribute("src", source);
      }

      var drawer = toolbar.drawerRoot.querySelector(drawerSelector);
      var handle = toolbar.drawerRoot.querySelector(".resize-handle");

      if (!drawer || !handle) {
        toolbar.drawerRoot.innerHTML = renderDrawer(view, position);
        toolbar.drawerPosition = position;

        return;
      }

      if (toolbar.drawerPosition === position) {
        return;
      }

      if (position === "top") {
        toolbar.drawerRoot.appendChild(drawer);
        toolbar.drawerRoot.appendChild(handle);
      } else {
        toolbar.drawerRoot.appendChild(handle);
        toolbar.drawerRoot.appendChild(drawer);
      }

      toolbar.drawerPosition = position;
    },
    /** Mirrors the menu state onto the rendered wrapper and its toggle. */
    syncExtensions: function () {
      var wrapper = toolbar.shadowRoot.querySelector(".extensions");

      if (!wrapper) {
        return;
      }

      wrapper.classList.toggle("is-open", toolbar.extensionsOpen);
      wrapper
        .querySelector(".extensions-toggle")
        ?.setAttribute(
          "aria-expanded",
          toolbar.extensionsOpen ? "true" : "false",
        );
    },
    /** Collapses or expands the bar, persisting the choice. */
    toggleExpanded: function () {
      toolbar.expanded = !toolbar.expanded;
      writeStorageItem(storageKey, toolbar.expanded ? "1" : "0");
      if (!toolbar.expanded) {
        toolbar.drawerOpen = false;
      }
      toolbar.render();
      focusToolbarElement(toolbar.shadowRoot, ".toggle-toolbar");
    },
    /**
     * Flips the menu without re-rendering the bar, so focus stays on the
     * toggle.
     */
    toggleExtensions: function () {
      toolbar.extensionsOpen = !toolbar.extensionsOpen;
      controller.syncExtensions();

      if (!toolbar.extensionsOpen) {
        return;
      }

      var menu = toolbar.shadowRoot.querySelector(".extensions-menu");

      menu?.querySelector("[data-debug-url], a, button")?.focus?.();
    },
    /** Republishes the resize handle's value range after a height change. */
    updateResizeHandleAccessibility: function () {
      var drawer = toolbar.shadowRoot.querySelector(drawerSelector);
      var handle = toolbar.shadowRoot.querySelector(".resize-handle");

      if (!drawer || !handle) {
        return;
      }

      var maximum = Math.max(minimumDrawerHeight, viewportHeight() - 48);
      var current = Math.max(
        minimumDrawerHeight,
        Math.min(maximum, Math.round(drawer.getBoundingClientRect().height)),
      );

      handle.setAttribute("aria-valuemin", String(minimumDrawerHeight));
      handle.setAttribute("aria-valuemax", String(maximum));
      handle.setAttribute("aria-valuenow", String(current));
    },
  };

  return controller;
}
