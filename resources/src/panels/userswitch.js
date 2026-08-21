import { ajax, on } from "../core/dom.js";

(function () {
  "use strict";

  var serialize = function (form) {
      if (!form || form.nodeName !== "FORM") {
        return "";
      }

      var params = new URLSearchParams();

      new FormData(form).forEach(function (value, name) {
        if (typeof value === "string") {
          params.append(name, value);
        }
      });

      return params.toString();
    },
    renderError = function (form, xhr) {
      var message = xhr.responseText || "Unable to switch user.";
      var error = form.querySelector(".debug-userswitch__error");

      if (!error) {
        error = document.createElement("div");
        error.className =
          "debug-userswitch__error yii-debug-callout yii-debug-callout-danger";
        error.setAttribute("role", "alert");
        form.insertBefore(error, form.firstChild);
      }

      error.textContent = message;
    },
    switching = false,
    sendSetIdentity = function (form) {
      /*
       * Collapse concurrent submits (double-click, or set + reset in the same
       * tick) into a single request: each identity switch regenerates the
       * session id server-side, so two in-flight POSTs race and can leave the
       * session tracking the wrong main user.
       */
      if (switching) {
        return;
      }

      switching = true;

      ajax({
        url: form.action,
        method: "post",
        data: serialize(form),
        success: function () {
          window.top.location.reload();
        },
        error: function (xhr) {
          switching = false;
          renderError(form, xhr);
        },
      });
    },
    closestElement = function (target, selector) {
      if (target && target.nodeType !== 1) {
        target = target.parentElement;
      }

      return target && typeof target.closest === "function"
        ? target.closest(selector)
        : null;
    },
    findSwitchRow = function (filter, target) {
      var row = closestElement(target, "tbody tr[data-key]");

      return row && filter.contains(row) ? row : null;
    },
    isInteractiveTarget = function (row, target) {
      var interactive = closestElement(
        target,
        'a, button, input, select, textarea, label, summary, [role="button"], [role="link"], [contenteditable="true"]',
      );

      return interactive !== null && row.contains(interactive);
    },
    switchToRow = function (filter, target) {
      var row = findSwitchRow(filter, target);
      var form;
      var userId;

      if (!row || isInteractiveTarget(row, target)) {
        return false;
      }

      form = document.getElementById("debug-userswitch__set-identity");
      userId = document.getElementById("user_id");

      if (!form || !userId) {
        return false;
      }

      userId.value = row.dataset.key;
      sendSetIdentity(form);

      return true;
    };

  var filter = document.getElementById("debug-userswitch__filter");

  on(filter, "click", function (e) {
    if (!switchToRow(filter, e.target)) {
      return;
    }

    e.stopPropagation();
  });
  on(filter, "keydown", function (e) {
    if (e.key !== "Enter" && e.key !== " ") {
      return;
    }

    var row = findSwitchRow(filter, e.target);

    if (!row || e.target !== row || isInteractiveTarget(row, e.target)) {
      return;
    }

    e.preventDefault();

    if (!switchToRow(filter, row)) {
      return;
    }

    e.stopPropagation();
  });
  on(
    document.getElementById("debug-userswitch__reset-identity-button"),
    "click",
    function (e) {
      var form = document.getElementById("debug-userswitch__reset-identity");

      e.preventDefault();
      e.stopPropagation();

      sendSetIdentity(form);
    },
  );
})();
