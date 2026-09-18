import { HOST_THEME_PROPAGATION_KEYS } from "../core/shared.js";
import {
  readStorageItem,
  themeAttributeFilter,
  themeStorageKey,
  writeStorageItem,
} from "./state.js";
import {
  addThemeToUrl,
  delegateThemeToHost,
  getComputedTheme,
  getElementTheme,
  getStorageTheme,
  hostHasThemeControl,
  normalizeTheme,
  resetHostThemeControlCache,
  writeThemeCookie,
} from "./theme.js";
import {
  focusToolbarElement,
  isToolbarDrawerCloseMessage,
  isToolbarDrawerThemeMessage,
} from "./focus.js";

/**
 * Owns the toolbar's theme lifecycle: the resolution order, the observers that
 * follow the host page, and the listeners that must be released when the
 * element leaves the document.
 *
 * `start()` resolves the first theme and subscribes to every signal the host
 * can flip it through; `dispose()` releases all of them. The controller writes
 * the resolved value back to `toolbar.theme`, which the renderer reads.
 *
 * Usage example:
 *
 * ```js
 * var theme = createToolbarThemeController(element);
 *
 * theme.start();
 * theme.dispose();
 * ```
 */
export function createToolbarThemeController(toolbar) {
  var controller;

  /**
   * Re-resolves the active theme. Every listener dispatches through the
   * controller, so the lifecycle checkpoints share one replaceable entry point.
   */
  function onThemeSignal() {
    controller.refreshTheme();
  }

  /** Invalidates the cached host switcher before re-resolving the theme. */
  function onHostSettled() {
    controller.refreshHostThemeControl();
  }

  /**
   * Applies an explicit choice: the attribute pin keeps `detectTheme()` on it,
   * the cookie carries it to the next panel navigation and the host signals fan
   * it out to the surrounding page.
   */
  function pinTheme(theme) {
    toolbar.theme = theme;
    toolbar.setAttribute("data-theme", theme);
    /* Pin the explicit choice so `detectTheme()` keeps honoring it. */
    toolbar.setAttribute("data-yii-debug-theme", theme);

    writeStorageItem(themeStorageKey, theme);
    writeThemeCookie(theme);
    controller.propagateThemeToHost(theme);
  }

  /**
   * Applies a theme flip posted by the chip inside the panel iframe, and closes
   * the drawer when the panel asks for it.
   */
  function onThemeMessage(event) {
    if (!event || event.origin !== window.location.origin) {
      return;
    }

    var data = event.data;
    var drawerFrame = toolbar.shadowRoot.querySelector(".drawer iframe");

    if (
      isToolbarDrawerCloseMessage(
        event,
        window.location.origin,
        drawerFrame ? drawerFrame.contentWindow : null,
      )
    ) {
      toolbar.drawer.closeDrawer();

      return;
    }

    if (
      !isToolbarDrawerThemeMessage(
        event,
        window.location.origin,
        drawerFrame ? drawerFrame.contentWindow : null,
      )
    ) {
      return;
    }

    var nextTheme = normalizeTheme(data.theme);

    if (!nextTheme || nextTheme === toolbar.theme) {
      return;
    }

    if (delegateThemeToHost(nextTheme, controller.detectTheme())) {
      controller.refreshTheme();

      return;
    }

    /**
     * The flip originated inside the panel iframe; pinning carries it to the
     * cookie so a fresh panel navigation (or a hard reload) lands on the same
     * theme.
     */
    pinTheme(nextTheme);
    toolbar.render();
  }

  controller = {
    /** Live `MutationObserver` following the host theme attributes. */
    observer: null,
    /** Whether the toolbar owns the theme, or delegates to a host switcher. */
    ownsTheme: false,
    /** Timer for the checkpoint that re-evaluates a late host switcher. */
    refreshTimer: null,
    /** Live `prefers-color-scheme` query. */
    systemQuery: null,
    /** Live `message` listener, or `null` while the element is detached. */
    themeMessage: null,
    /**
     * Resolves the theme the toolbar must render, most authoritative signal
     * first.
     */
    detectTheme: function () {
      /**
       * The current DOM state (`<html>`/`<body>` class or `data-theme` attr) is
       * the most authoritative signal — if the page IS rendering with a Tailwind
       * `dark` class then any stale `localStorage[yii-debug-toolbar-theme]` from
       * a previous session must lose.
       */
      var domTheme =
        getElementTheme(document.documentElement) ||
        getElementTheme(document.body);

      if (domTheme) {
        return domTheme;
      }

      /**
       * The host manages its own theme and the document carries no marker: every
       * mainstream dark-mode convention (Tailwind class, `data-bs-theme`, Pico)
       * marks DARK explicitly, so an unmarked document is the host's light
       * state. Stale storage must not outvote what the page actually renders.
       */
      if (!controller.ownsTheme) {
        return getComputedTheme() || "light";
      }

      return (
        normalizeTheme(toolbar.getAttribute("data-yii-debug-theme")) ||
        normalizeTheme(readStorageItem(themeStorageKey)) ||
        getStorageTheme() ||
        getComputedTheme() ||
        (window.matchMedia &&
        window.matchMedia("(prefers-color-scheme: dark)").matches
          ? "dark"
          : "light")
      );
    },
    /**
     * Ends the lifecycle: the observer, the system query and every window
     * listener the controller installed are released.
     */
    dispose: function () {
      if (controller.observer) {
        controller.observer.disconnect();
        controller.observer = null;
      }

      if (controller.systemQuery) {
        if (controller.systemQuery.removeEventListener) {
          controller.systemQuery.removeEventListener("change", onThemeSignal);
        } else if (controller.systemQuery.removeListener) {
          controller.systemQuery.removeListener(onThemeSignal);
        }
        controller.systemQuery = null;
      }

      window.removeEventListener("storage", onThemeSignal, false);
      window.removeEventListener("load", onHostSettled, false);

      if (controller.refreshTimer !== null) {
        window.clearTimeout(controller.refreshTimer);
        controller.refreshTimer = null;
      }

      if (controller.themeMessage) {
        window.removeEventListener("message", controller.themeMessage, false);
        controller.themeMessage = null;
      }
    },
    /**
     * When the dev flips the theme via our own toggle (i.e. the host app does
     * NOT ship a switcher of its own) we best-effort fan the change out to the
     * signals most front-end stacks read so the surrounding page also flips.
     * None of these writes is destructive: if a token isn't recognized by the
     * host, it's simply ignored.
     */
    propagateThemeToHost: function (theme) {
      var html = document.documentElement;
      var opposite = theme === "dark" ? "light" : "dark";
      var storageKeys = HOST_THEME_PROPAGATION_KEYS;
      var i;

      if (html) {
        /**
         * Tailwind-style modifier class (`<html class="dark">`) is the most
         * common convention; we keep `light`/`dark` mutually exclusive.
         */
        if (html.classList) {
          html.classList.add(theme);
          html.classList.remove(opposite);
        }
        /* Bootstrap 5 / Pico / generic CSS-token convention. */
        html.setAttribute("data-theme", theme);
        html.setAttribute("data-bs-theme", theme);
        html.style.colorScheme = theme;
      }

      for (i = 0; i < storageKeys.length; i++) {
        writeStorageItem(storageKeys[i], theme);
      }
    },
    /** Re-evaluates the host switcher, then the theme it implies. */
    refreshHostThemeControl: function () {
      resetHostThemeControlCache();
      controller.refreshTheme();
    },
    /** Applies the resolved theme, re-rendering only when it moved. */
    refreshTheme: function () {
      /* Reuse the host-control result between explicit lifecycle checkpoints. */
      controller.ownsTheme = !hostHasThemeControl();

      var theme = controller.detectTheme();
      var previousTheme = toolbar.theme;

      if (previousTheme === theme) {
        return;
      }

      toolbar.theme = theme;
      toolbar.setAttribute("data-theme", theme);

      writeStorageItem(themeStorageKey, theme);

      /**
       * Cookie is what the backend reads on the next debug request, so the panel
       * page renders with the correct theme even when the toolbar followed a
       * host change via the MutationObserver and the URL didn't carry
       * `yii_debug_theme`.
       */
      writeThemeCookie(theme);

      if (previousTheme && toolbar.data) {
        toolbar.render();
      }
    },
    /**
     * Starts the lifecycle: resolves the first theme, subscribes to every host
     * signal and schedules the checkpoints that catch a late host switcher.
     */
    start: function () {
      resetHostThemeControlCache();
      controller.ownsTheme = !hostHasThemeControl();
      controller.refreshTheme();
      controller.watchTheme();

      /**
       * SPA hosts (Vue/Inertia/React) mount their theme switcher AFTER this
       * callback runs, so the initial `hostHasThemeControl()` sweep can miss it.
       * Re-evaluate once the page settles. These discrete checkpoints invalidate
       * the host-control cache before refreshing the active theme.
       */
      window.addEventListener("load", onHostSettled, false);
      controller.refreshTimer = window.setTimeout(onHostSettled, 1500);
    },
    /** Flips between the two themes, or lets the host switcher do it. */
    toggleTheme: function () {
      var next = toolbar.theme === "dark" ? "light" : "dark";

      if (delegateThemeToHost(next, controller.detectTheme())) {
        controller.refreshTheme();
        focusToolbarElement(toolbar.shadowRoot, ".toggle-theme");

        return;
      }

      pinTheme(next);
      toolbar.render();
      focusToolbarElement(toolbar.shadowRoot, ".toggle-theme");
    },
    /** Subscribes to every host signal that can flip the theme. Idempotent. */
    watchTheme: function () {
      if (window.MutationObserver && !controller.observer) {
        controller.observer = new MutationObserver(onThemeSignal);

        controller.observer.observe(document.documentElement, {
          attributes: true,
          attributeFilter: themeAttributeFilter,
        });
        if (document.body) {
          controller.observer.observe(document.body, {
            attributes: true,
            attributeFilter: themeAttributeFilter,
          });
        }
      }

      if (window.matchMedia && !controller.systemQuery) {
        controller.systemQuery = window.matchMedia(
          "(prefers-color-scheme: dark)",
        );
        if (controller.systemQuery.addEventListener) {
          controller.systemQuery.addEventListener("change", onThemeSignal);
        } else if (controller.systemQuery.addListener) {
          controller.systemQuery.addListener(onThemeSignal);
        }
      }

      window.addEventListener("storage", onThemeSignal, false);

      /**
       * Receive theme flips from inside the panel iframe (the chip in the panel
       * header postMessages us) and apply them on the host instantly, without
       * waiting for the storage event.
       */
      if (!controller.themeMessage) {
        controller.themeMessage = onThemeMessage;
        window.addEventListener("message", controller.themeMessage, false);
      }
    },
    /** Stamps the active theme on a URL and mirrors it to the cookie. */
    withTheme: function (url) {
      var theme = toolbar.theme || controller.detectTheme();

      /**
       * Keep the cookie in lockstep with what the drawer is about to show, so
       * bare in-panel navigation (which resolves via the cookie) stays on the
       * same theme as the stamped entry URL.
       */
      writeThemeCookie(theme);

      return addThemeToUrl(url, theme);
    },
  };

  return controller;
}
