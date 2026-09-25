import { closest } from "./shared.js";

/**
 * Click-driven disclosure layers of the debugger page: the "Show more" cell
 * boxes and the click-to-reveal controls guarding sensitive values.
 *
 * Usage example:
 *
 * ```js
 * prepareCellMoreControls();
 * document.addEventListener("click", onDisclosureClick);
 * ```
 */

function findToggle(node, kind) {
  return closest(node, '[data-yii-debug-toggle="' + kind + '"]');
}

/**
 * Pairs every "Show more" control with the body it controls and labels it after
 * the state that body is rendered in.
 */
export function prepareCellMoreControls() {
  var boxes = document.querySelectorAll(".yii-debug-cell-more");
  var sequence = 0;

  for (var i = 0; i < boxes.length; i++) {
    var body = boxes[i].querySelector(".yii-debug-cell-more-body");
    var control = boxes[i].querySelector('[data-yii-debug-toggle="cell-more"]');

    if (!body || !control) {
      continue;
    }

    if (!body.id) {
      do {
        sequence++;
        body.id = "yii-debug-cell-more-" + sequence;
      } while (document.querySelectorAll('[id="' + body.id + '"]').length > 1);
    }

    control.setAttribute("aria-controls", body.id);
    control.textContent = boxes[i].classList.contains("is-open")
      ? "Show less"
      : "Show more";
  }
}

/** Toggles the "Show more" box a click landed on. */
export function onDisclosureClick(event) {
  var cellMore = findToggle(event.target, "cell-more");

  if (!cellMore) {
    return;
  }

  var moreBox = closest(cellMore, ".yii-debug-cell-more");
  event.preventDefault();

  if (!moreBox) {
    return;
  }

  var moreOpen = moreBox.classList.toggle("is-open");
  cellMore.setAttribute("aria-expanded", moreOpen ? "true" : "false");
  cellMore.textContent = moreOpen ? "Show less" : "Show more";
}

/** Click-to-reveal toggle for sensitive User-panel fields. */
export function onRevealClick(event) {
  var btn = event.target.closest("[data-yii-debug-reveal]");

  if (!btn) {
    return;
  }

  var revealed = btn.classList.toggle("is-revealed");
  var revealLabel = btn.getAttribute("data-yii-debug-reveal-label") || "value";

  btn.setAttribute("aria-pressed", revealed ? "true" : "false");
  btn.setAttribute(
    "aria-label",
    (revealed ? "Hide " : "Reveal ") + revealLabel,
  );
}
