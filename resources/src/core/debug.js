import "../styles/main.css";
import "../styles/timeline.css";
import "../styles/primitives.css";
import "./history-cursor.js";
import { bindCopyControls } from "./clipboard.js";
import { initSectionPermalinks } from "./deep-links.js";
import { bindDensityToggle } from "./density.js";
import { dropdownNavigationIndex } from "./dropdown.js";
import { loadPanelFeatures } from "./features.js";
import { initTabs } from "./tabs.js";
import {
  bindThemeToggle,
  normalizeTheme,
  preserveThemeInLinks,
  readStoredTheme,
  readThemeCookie,
  THEME_PARAM,
  writeTheme,
} from "./theme.js";
import { requestParentToolbarDrawerClose } from "../toolbar/focus.js";

(function () {
  "use strict";

  function getParentToolbarTheme() {
    var root;
    var host;

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

  function getUrlTheme() {
    try {
      return normalizeTheme(
        new URL(window.location.href).searchParams.get(THEME_PARAM),
      );
    } catch {
      return null;
    }
  }

  function applyTheme() {
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
    var theme =
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

  function bindThemeToggleButton() {
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

  function bindDensityToggleButton() {
    var storage = null;

    try {
      storage = window.localStorage;
    } catch {
      // Sandboxed drawer frames can expose the property but reject access.
    }

    bindDensityToggle(
      document.querySelector("[data-yii-debug-density-toggle]"),
      document.documentElement,
      storage,
    );
  }

  function closest(element, selector) {
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

  function findToggle(node, kind) {
    return closest(node, '[data-yii-debug-toggle="' + kind + '"]');
  }

  function dropdownItems(menu) {
    return menu
      ? Array.from(
          menu.querySelectorAll(
            'a[href], button:not([disabled]), [role="menuitem"][tabindex]',
          ),
        ).filter(function (item) {
          return !item.hidden && item.getAttribute("aria-hidden") !== "true";
        })
      : [];
  }

  function prepareCellMoreControls() {
    var boxes = document.querySelectorAll(".yii-debug-cell-more");
    var sequence = 0;

    for (var i = 0; i < boxes.length; i++) {
      var body = boxes[i].querySelector(".yii-debug-cell-more-body");
      var control = boxes[i].querySelector(
        '[data-yii-debug-toggle="cell-more"]',
      );

      if (!body || !control) {
        continue;
      }

      if (!body.id) {
        do {
          sequence++;
          body.id = "yii-debug-cell-more-" + sequence;
        } while (
          document.querySelectorAll('[id="' + body.id + '"]').length > 1
        );
      }

      control.setAttribute("aria-controls", body.id);
      control.textContent = boxes[i].classList.contains("is-open")
        ? "Show less"
        : "Show more";
    }
  }

  function updateLiveFilter(input) {
    var anchor = input.closest("header, .yii-debug-section-header") || input;
    var target = anchor.nextElementSibling;

    while (target && !target.matches("[data-yii-debug-filter-target]")) {
      target = target.nextElementSibling;
    }

    if (!target) {
      return;
    }

    var rows = target.querySelectorAll("tbody tr");
    var query = input.value.trim().toLowerCase();
    var visible = 0;

    for (var i = 0; i < rows.length; i++) {
      var row = rows[i];
      var matched =
        query === "" || row.textContent.toLowerCase().indexOf(query) !== -1;

      row.hidden = !matched;
      visible += matched ? 1 : 0;
    }

    var status = anchor.querySelector("[data-yii-debug-filter-status]");

    if (!status) {
      status = document.createElement("span");
      status.id = input.id
        ? input.id + "-status"
        : "yii-debug-filter-status-" +
          (document.querySelectorAll("[data-yii-debug-filter-status]").length +
            1);
      status.className = "yii-debug-sr-only";
      status.setAttribute("aria-live", "polite");
      status.setAttribute("aria-atomic", "true");
      status.setAttribute("data-yii-debug-filter-status", "true");
      anchor.appendChild(status);

      var descriptions = (input.getAttribute("aria-describedby") || "")
        .split(/\s+/)
        .filter(Boolean);

      if (descriptions.indexOf(status.id) === -1) {
        descriptions.push(status.id);
        input.setAttribute("aria-describedby", descriptions.join(" "));
      }
    }

    status.textContent = visible + " of " + rows.length + " rows shown.";
  }

  function hideDropdowns(except) {
    var wrappers = document.querySelectorAll(".yii-debug-dropdown.is-open");
    for (var i = 0; i < wrappers.length; i++) {
      var menu = wrappers[i].querySelector(".yii-debug-dropdown-menu");
      if (except && menu === except) {
        continue;
      }
      wrappers[i].classList.remove("is-open");
      var trigger = wrappers[i].querySelector(
        '[data-yii-debug-toggle="dropdown"]',
      );
      if (trigger) {
        trigger.setAttribute("aria-expanded", "false");
      }
    }
  }

  preserveThemeInLinks(applyTheme());
  bindThemeToggleButton();
  bindDensityToggleButton();
  prepareCellMoreControls();

  document.addEventListener("click", function (event) {
    var dropdown = findToggle(event.target, "dropdown");
    var collapse = findToggle(event.target, "collapse");
    var cellMore = findToggle(event.target, "cell-more");

    if (cellMore) {
      var moreBox = closest(cellMore, ".yii-debug-cell-more");
      event.preventDefault();

      if (!moreBox) {
        return;
      }

      var moreOpen = moreBox.classList.toggle("is-open");
      cellMore.setAttribute("aria-expanded", moreOpen ? "true" : "false");
      cellMore.textContent = moreOpen ? "Show less" : "Show more";
      return;
    }

    if (collapse) {
      var targetSelector =
        collapse.getAttribute("data-target") || collapse.getAttribute("href");
      var target = targetSelector
        ? document.querySelector(targetSelector)
        : null;
      event.preventDefault();

      if (!target) {
        return;
      }

      var isShown = target.classList.contains("is-open");
      target.classList.toggle("is-open", !isShown);
      collapse.setAttribute("aria-expanded", isShown ? "false" : "true");
      return;
    }

    if (dropdown) {
      var wrapper = closest(dropdown, ".yii-debug-dropdown");
      var menu = wrapper
        ? wrapper.querySelector(".yii-debug-dropdown-menu")
        : null;
      event.preventDefault();
      event.stopPropagation();

      if (!wrapper || !menu) {
        return;
      }

      var isOpen = wrapper.classList.contains("is-open");
      hideDropdowns(menu);
      wrapper.classList.toggle("is-open", !isOpen);
      dropdown.setAttribute("aria-expanded", isOpen ? "false" : "true");
      return;
    }

    hideDropdowns(null);
  });

  document.addEventListener("keydown", function (event) {
    if (
      event.key === "Escape" &&
      event.target.matches &&
      event.target.matches("[data-yii-debug-filter]") &&
      event.target.value !== ""
    ) {
      event.preventDefault();
      event.stopPropagation();
      event.target.value = "";
      updateLiveFilter(event.target);
      event.target.focus();
      return;
    }

    if (
      event.key === "Escape" &&
      event.target.matches &&
      event.target.matches(
        ".yii-debug-grid .filters input, .yii-debug-grid td.yii-debug-filter-cell input",
      ) &&
      event.target.value !== ""
    ) {
      event.preventDefault();
      event.stopImmediatePropagation();
      event.target.value = "";
      event.target.dispatchEvent(new Event("change", { bubbles: true }));
      return;
    }

    var dropdownWrapper = closest(event.target, ".yii-debug-dropdown");
    var dropdownTrigger = dropdownWrapper
      ? dropdownWrapper.querySelector('[data-yii-debug-toggle="dropdown"]')
      : null;
    var dropdownMenu = dropdownWrapper
      ? dropdownWrapper.querySelector(".yii-debug-dropdown-menu")
      : null;

    if (
      dropdownWrapper &&
      dropdownTrigger &&
      dropdownMenu &&
      ["ArrowDown", "ArrowUp", "Home", "End"].indexOf(event.key) !== -1
    ) {
      var items = dropdownItems(dropdownMenu);
      var currentItem = items.indexOf(event.target);
      var nextItem = dropdownNavigationIndex(
        items.length,
        currentItem,
        event.key,
        event.target === dropdownTrigger,
      );

      if (items.length === 0) {
        return;
      }

      event.preventDefault();
      hideDropdowns(dropdownMenu);
      dropdownWrapper.classList.add("is-open");
      dropdownTrigger.setAttribute("aria-expanded", "true");

      items[nextItem].focus();
      return;
    }

    if (event.key === "Escape") {
      var openDropdown = document.querySelector(".yii-debug-dropdown.is-open");
      var dropdownWasOpen = Boolean(openDropdown);
      var openDropdownTrigger = openDropdown
        ? openDropdown.querySelector('[data-yii-debug-toggle="dropdown"]')
        : null;

      hideDropdowns(null);

      if (openDropdownTrigger) {
        openDropdownTrigger.focus();
      }

      window.setTimeout(function () {
        requestParentToolbarDrawerClose(event, window, dropdownWasOpen);
      }, 0);
    }
  });

  // Click-to-reveal toggle for sensitive User-panel fields.
  document.addEventListener("click", function (event) {
    var btn = event.target.closest("[data-yii-debug-reveal]");

    if (!btn) {
      return;
    }

    var revealed = btn.classList.toggle("is-revealed");
    var revealLabel =
      btn.getAttribute("data-yii-debug-reveal-label") || "value";

    btn.setAttribute("aria-pressed", revealed ? "true" : "false");
    btn.setAttribute(
      "aria-label",
      (revealed ? "Hide " : "Reveal ") + revealLabel,
    );
  });

  // Page-size selector inside GridView footers. Picks up the change event,
  // rewrites the `per-page` query param while keeping every other filter/sort
  // intact, and reloads the panel.
  document.addEventListener("change", function (event) {
    var select = event.target;

    if (!select || !select.matches("[data-yii-debug-pagesize]")) {
      return;
    }

    var url = new URL(window.location.href);

    if (select.value === "" || select.value === "0") {
      url.searchParams.delete("per-page");
    } else {
      url.searchParams.set("per-page", select.value);
    }

    // Drop the page param so we land on page 1 with the new size.
    url.searchParams.delete("page");
    window.location.href = url.toString();
  });

  // Live filter for tabular sections marked with [data-yii-debug-filter].
  // The input filters its sibling [data-yii-debug-filter-target] table rows by
  // case-insensitive substring against the row's text content. Hiding rows
  // client-side is cheap and avoids round-trips for >100-header request panels.
  document.addEventListener("input", function (event) {
    var input = event.target;

    if (!input || !input.matches("[data-yii-debug-filter]")) {
      return;
    }

    updateLiveFilter(input);
  });

  // GridView filter row → URL bridge. The 22.0 shell ships without jQuery / yii.gridView.js,
  // so each filter input drives URL params by hand. The regex matches any Yii form name
  // pattern `<FormName>[<attr>]`, which means the bridge works for the index page (Debug[…])
  // and every panel (Db[…], Log[…], Profile[…], Event[…], Mail[…], User[…], …) without
  // per-page wiring. <select> filters apply on change, text inputs apply on Enter
  // (immediate), on blur (when the dev tabs out), and after a 650 ms idle while typing.
  // Each apply rebuilds the URL keeping every other query param intact and drops the page
  // param so we always land on page 1.
  (function () {
    var FILTER_FOCUS_KEY = "yii-debug-grid-filter-focus";
    var IDLE_MS = 650;
    var FORM_INPUT = /^[A-Za-z][A-Za-z0-9_]*\[[^\]]+\]$/;
    var pending = null;

    function nameMatchesFilter(input) {
      return !!input && !!input.name && FORM_INPUT.test(input.name);
    }

    function sessionStorageBackend() {
      try {
        return window.sessionStorage;
      } catch {
        return null;
      }
    }

    function rememberFocus(input) {
      var storage = sessionStorageBackend();

      if (!storage) {
        return;
      }

      try {
        storage.setItem(
          FILTER_FOCUS_KEY,
          JSON.stringify({
            end: input.selectionEnd,
            name: input.name,
            start: input.selectionStart,
          }),
        );
      } catch {
        // Storage quotas or privacy settings must not block navigation.
      }
    }

    function restoreFocus() {
      var storage = sessionStorageBackend();
      var stored;

      if (!storage) {
        return;
      }

      try {
        stored = JSON.parse(storage.getItem(FILTER_FOCUS_KEY) || "null");
        storage.removeItem(FILTER_FOCUS_KEY);
      } catch {
        return;
      }

      if (!stored || !nameMatchesFilter(stored)) {
        return;
      }

      var inputs = document.getElementsByName(stored.name);
      var input = inputs.length > 0 ? inputs[0] : null;

      if (!input || input.tagName !== "INPUT") {
        return;
      }

      input.focus({ preventScroll: true });

      if (typeof input.setSelectionRange === "function") {
        input.setSelectionRange(stored.start, stored.end);
      }
    }

    function apply(input) {
      if (!nameMatchesFilter(input)) {
        return;
      }

      var url = new URL(window.location.href);

      if (input.value === "" || input.value === null) {
        url.searchParams.delete(input.name);
      } else {
        url.searchParams.set(input.name, input.value);
      }

      url.searchParams.delete("page");

      if (url.toString() === window.location.href) {
        return;
      }

      rememberFocus(input);
      document.documentElement.setAttribute("aria-busy", "true");
      window.location.href = url.toString();
    }

    function scheduleApply(input) {
      if (pending) {
        clearTimeout(pending.timeout);
      }
      pending = {
        input: input,
        timeout: setTimeout(function () {
          var current = pending;
          pending = null;
          apply(current.input);
        }, IDLE_MS),
      };
    }

    function flushPending() {
      if (!pending) {
        return false;
      }
      clearTimeout(pending.timeout);
      var input = pending.input;
      pending = null;
      apply(input);
      return true;
    }

    document.addEventListener("change", function (event) {
      if (!nameMatchesFilter(event.target)) {
        return;
      }

      if (pending && pending.input === event.target) {
        clearTimeout(pending.timeout);
        pending = null;
      }

      if (
        event.target.tagName === "SELECT" ||
        event.target.tagName === "INPUT"
      ) {
        apply(event.target);
      }
    });

    document.addEventListener("input", function (event) {
      if (event.target.tagName !== "INPUT" || event.target.type === "submit") {
        return;
      }
      if (!nameMatchesFilter(event.target)) {
        return;
      }
      scheduleApply(event.target);
    });

    document.addEventListener("keydown", function (event) {
      if (event.key !== "Enter") {
        return;
      }
      if (event.target.tagName !== "INPUT" || event.target.type === "submit") {
        return;
      }
      if (!nameMatchesFilter(event.target)) {
        return;
      }
      event.preventDefault();
      if (!flushPending()) {
        apply(event.target);
      }
    });

    document.addEventListener("focusout", function (event) {
      if (event.target.tagName !== "INPUT" || event.target.type === "submit") {
        return;
      }
      if (!nameMatchesFilter(event.target)) {
        return;
      }
      // If the dev tabs out before the debounce fires, flush immediately so the
      // URL reflects whatever they typed.
      if (pending && pending.input === event.target) {
        flushPending();
      }
    });

    restoreFocus();
  })();

  initSectionPermalinks(document, window);
  initTabs(document, window);
  bindCopyControls(
    document,
    window.navigator ? window.navigator.clipboard : null,
    window.location,
  );
  loadPanelFeatures(document).catch(function () {
    document.documentElement.setAttribute(
      "data-yii-debug-feature-load-error",
      "true",
    );

    var alert = document.createElement("div");
    alert.className = "yii-debug-callout yii-debug-callout-danger";
    alert.setAttribute("role", "alert");
    alert.textContent =
      "An interactive debugger feature failed to load. Reload the page and try again.";

    var main = document.getElementById("yii-debug-main");
    if (main) {
      main.prepend(alert);
    }
  });
})();
