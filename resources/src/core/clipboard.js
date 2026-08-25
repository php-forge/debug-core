const COPY_FEEDBACK = Object.freeze({
  error: {
    button: "Copy failed",
    label: "Retry copy",
    status: "Copy failed. Try again.",
  },
  success: {
    button: "Copied",
    label: "Copied",
    status: "Copied to clipboard.",
  },
  unavailable: {
    button: "Copy unavailable",
    label: "Unavailable",
    status: "Clipboard access is unavailable.",
  },
});

var copyStatusSequence = 0;

function describedByIds(control) {
  return (control.getAttribute("aria-describedby") || "")
    .split(/\s+/)
    .filter(Boolean);
}

function appendDescription(control, statusId) {
  var descriptions = describedByIds(control);

  if (descriptions.indexOf(statusId) === -1) {
    descriptions.push(statusId);
    control.setAttribute("aria-describedby", descriptions.join(" "));
  }
}

function createStatus(control) {
  var status = control.ownerDocument.createElement("span");

  copyStatusSequence += 1;
  status.id = "yii-debug-copy-status-" + copyStatusSequence;
  status.className = "yii-debug-sr-only";
  status.setAttribute("aria-atomic", "true");
  status.setAttribute("aria-live", "polite");
  status.setAttribute("data-yii-debug-copy-status", "true");
  control.after(status);
  appendDescription(control, status.id);

  return status;
}

function statusFor(control) {
  var describedBy = describedByIds(control);
  var documentValue = control.ownerDocument;

  for (var i = 0; i < describedBy.length; i++) {
    var candidate = documentValue.getElementById(describedBy[i]);

    if (
      candidate &&
      candidate.getAttribute("data-yii-debug-copy-status") === "true"
    ) {
      return candidate;
    }
  }

  return createStatus(control);
}

function setFeedback(control, status, state) {
  var visibleLabel = control.querySelector("[data-yii-debug-copy-label]");
  var feedback = COPY_FEEDBACK[state];

  control.setAttribute("aria-label", feedback.button);
  control.setAttribute("title", feedback.button);
  status.textContent = feedback.status;

  if (visibleLabel) {
    visibleLabel.textContent = feedback.label;
  }
}

/**
 * Resolves the text source for a reusable copy control.
 */
export function copyControlText(control, root, locationValue) {
  var literal = control.getAttribute("data-yii-debug-copy-value");

  if (literal !== null) {
    return literal;
  }

  var targetId = control.getAttribute("data-yii-debug-copy-target");

  if (targetId) {
    var id = targetId.charAt(0) === "#" ? targetId.slice(1) : targetId;
    var target = root.getElementById ? root.getElementById(id) : null;

    return target ? target.textContent : null;
  }

  if (control.hasAttribute("data-yii-debug-copy-link")) {
    if (typeof locationValue === "string") {
      return locationValue;
    }

    return locationValue && locationValue.href ? locationValue.href : null;
  }

  return null;
}

/**
 * Binds accessible clipboard feedback to declarative copy controls.
 *
 * The generic contract is `[data-yii-debug-copy]` plus either a literal
 * `data-yii-debug-copy-value` or a safe target id in
 * `data-yii-debug-copy-target`. The historical shell
 * `[data-yii-debug-copy-link]` marker remains supported.
 */
export function bindCopyControls(root, clipboard, locationValue) {
  var controls = root.querySelectorAll(
    "[data-yii-debug-copy], [data-yii-debug-copy-link]",
  );
  var bound = 0;

  for (var i = 0; i < controls.length; i++) {
    var control = controls[i];

    if (control.getAttribute("data-yii-debug-copy-bound") === "true") {
      continue;
    }

    control.setAttribute("data-yii-debug-copy-bound", "true");
    var status = statusFor(control);

    control.addEventListener(
      "click",
      (function (button, liveStatus) {
        return function () {
          var text = copyControlText(button, root, locationValue);

          if (
            text === null ||
            !clipboard ||
            typeof clipboard.writeText !== "function"
          ) {
            setFeedback(button, liveStatus, "unavailable");

            return;
          }

          try {
            Promise.resolve(clipboard.writeText(text)).then(
              function () {
                setFeedback(button, liveStatus, "success");
              },
              function () {
                setFeedback(button, liveStatus, "error");
              },
            );
          } catch {
            setFeedback(button, liveStatus, "error");
          }
        };
      })(control, status),
    );
    bound += 1;
  }

  return bound;
}
