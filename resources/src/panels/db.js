import { ajax, on } from "../core/dom.js";

const EXPLAIN_ALL_SELECTOR =
  ".yii-debug-db-explain-all-toggle, .yii-debug-db-explain-all a";
const EXPLAIN_ERROR_MESSAGE = "Unable to load the EXPLAIN output. Try again.";

export function updateExplainAllControl(control, expanded) {
  control.textContent = expanded ? "Collapse all" : "Explain all";
  control.setAttribute("aria-expanded", expanded ? "true" : "false");
}

(function () {
  "use strict";

  function syncExplainAllControls(expanded) {
    var controls = document.querySelectorAll(EXPLAIN_ALL_SELECTOR);
    var isExpanded =
      typeof expanded === "boolean"
        ? expanded
        : document.querySelectorAll(
            ".yii-debug-db-explain.is-open, .yii-debug-db-explain.is-loading",
          ).length > 0;

    for (var i = 0; i < controls.length; i++) {
      updateExplainAllControl(controls[i], isExpanded);
    }
  }

  on(
    document.querySelectorAll(".yii-debug-db-explain-toggle"),
    "click",
    function (e) {
      e.preventDefault();

      var container = this.closest(".yii-debug-db-explain"),
        target = container.querySelector(".yii-debug-db-explain-text"),
        isOpen = container.classList.contains("is-open"),
        self = this;

      if (container.classList.contains("is-loading")) {
        return;
      }

      if (isOpen) {
        container.classList.remove("is-open");
        self.setAttribute("aria-expanded", "false");
        syncExplainAllControls();
        return;
      }

      // Lazy-load on first open; cached afterwards.
      if (target.dataset.loaded === "1") {
        container.classList.add("is-open");
        self.setAttribute("aria-expanded", "true");
        syncExplainAllControls();
        return;
      }

      container.classList.add("is-loading");
      target.classList.remove("is-error");
      target.removeAttribute("role");
      target.setAttribute("aria-busy", "true");
      target.textContent = "";
      syncExplainAllControls();

      ajax(this.href, {
        success: function (xhr) {
          target.innerHTML = xhr.responseText;
          target.dataset.loaded = "1";
          target.removeAttribute("aria-busy");
          container.classList.remove("is-loading");

          if (container.dataset.collapseAfterLoad === "1") {
            delete container.dataset.collapseAfterLoad;
            self.setAttribute("aria-expanded", "false");
          } else {
            container.classList.add("is-open");
            self.setAttribute("aria-expanded", "true");
          }

          syncExplainAllControls();
        },
        error: function () {
          container.classList.remove("is-loading");
          delete container.dataset.collapseAfterLoad;
          target.removeAttribute("aria-busy");
          target.classList.add("is-error");
          target.setAttribute("role", "alert");
          target.textContent = EXPLAIN_ERROR_MESSAGE;
          self.setAttribute("aria-expanded", "false");
          syncExplainAllControls();
        },
      });
    },
  );

  on(document.querySelectorAll(EXPLAIN_ALL_SELECTOR), "click", function (e) {
    e.preventDefault();

    var event = new MouseEvent("click", {
      cancelable: true,
      bubbles: true,
    });
    var toggles = document.querySelectorAll(".yii-debug-db-explain-toggle");
    var anyOpen =
      document.querySelectorAll(
        ".yii-debug-db-explain.is-open, .yii-debug-db-explain.is-loading",
      ).length > 0;

    for (var i = 0, len = toggles.length; i < len; i++) {
      var container = toggles[i].closest(".yii-debug-db-explain");
      var isOpen = container.classList.contains("is-open");
      var isLoading = container.classList.contains("is-loading");

      if (anyOpen && isLoading) {
        container.dataset.collapseAfterLoad = "1";
      } else if ((anyOpen && isOpen) || (!anyOpen && !isOpen)) {
        toggles[i].dispatchEvent(event);
      }
    }

    syncExplainAllControls(!anyOpen);
  });

  syncExplainAllControls();
})();
