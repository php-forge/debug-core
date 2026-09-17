import "../styles/main.css";
import "../styles/timeline.css";
import "../styles/primitives.css";
import "./history-cursor.js";
import { bindCopyControls } from "./clipboard.js";
import { initSectionPermalinks } from "./deep-links.js";
import {
  dismissDropdowns,
  focusDropdownItem,
  onDisclosureClick,
  onRevealClick,
  prepareCellMoreControls,
} from "./disclosure.js";
import { clearGridFilter, shouldClearGridFilter } from "./grid-filter.js";
import { initGridNavigation } from "./grid-navigation.js";
import { loadPanelFeatures } from "./features.js";
import { LIVE_FILTER_INPUT_SELECTOR, updateLiveFilter } from "./live-filter.js";
import { initTabs } from "./tabs.js";
import {
  applyTheme,
  bindThemeToggleButton,
  preserveThemeInLinks,
} from "./theme.js";
import { requestParentToolbarDrawerClose } from "../toolbar/focus.js";

/**
 * Debugger page bootstrap.
 *
 * Every behavior lives in a controller module; this entry only resolves the
 * theme, initializes those controllers in the order their document listeners
 * must answer in, and owns the one handler no single controller can: the
 * keyboard precedence between the layers, where one Escape closes exactly one
 * of them.
 */
(function () {
  "use strict";

  preserveThemeInLinks(applyTheme());
  bindThemeToggleButton();
  prepareCellMoreControls();

  document.addEventListener("click", onDisclosureClick);
  // Click-to-reveal toggle for sensitive User-panel fields.
  document.addEventListener("click", onRevealClick);

  document.addEventListener("keydown", function (event) {
    if (
      event.key === "Escape" &&
      event.target.matches &&
      event.target.matches(LIVE_FILTER_INPUT_SELECTOR) &&
      event.target.value !== ""
    ) {
      event.preventDefault();
      event.stopPropagation();
      event.target.value = "";
      updateLiveFilter(event.target);
      event.target.focus();

      return;
    }

    if (shouldClearGridFilter(event)) {
      event.preventDefault();
      event.stopImmediatePropagation();
      clearGridFilter(event.target);

      return;
    }

    /* The open menu answers first, so the drawer behind it keeps its Escape. */
    if (focusDropdownItem(event)) {
      return;
    }

    if (event.key === "Escape") {
      var dropdownWasOpen = dismissDropdowns();

      window.setTimeout(function () {
        requestParentToolbarDrawerClose(event, window, dropdownWasOpen);
      }, 0);
    }
  });

  // Live filter for tabular and grouped diagnostic sections.
  document.addEventListener("input", function (event) {
    var input = event.target;

    if (!input || !input.matches(LIVE_FILTER_INPUT_SELECTOR)) {
      return;
    }

    updateLiveFilter(input);
  });

  initGridNavigation(document);
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
