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
    };

  on(
    document.getElementById("debug-userswitch__filter"),
    "click",
    function (e) {
      var el;
      if (
        e.target.tagName.toLowerCase() === "td" &&
        e.target.parentElement.parentElement.tagName.toLowerCase() === "tbody"
      ) {
        el = e.target.parentElement;
      } else if (
        e.target.tagName.toLowerCase() === "tr" &&
        e.target.parentElement.tagName.toLowerCase() === "tbody"
      ) {
        el = e.target;
      } else {
        return;
      }

      var form = document.getElementById("debug-userswitch__set-identity");
      document.getElementById("user_id").value = el.dataset.key;
      sendSetIdentity(form);
      e.stopPropagation();
    },
  );
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
