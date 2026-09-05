export const LIVE_FILTER_ROW_SELECTOR = "[data-yii-debug-filter-row], tbody tr";
export const LIVE_FILTER_ANCHOR_SELECTOR =
  "header, .yii-debug-section-header, .yii-debug-mini-toolbar";
const FILTER_DEFAULT_OPEN_ATTRIBUTE = "data-yii-debug-filter-default-open";
const FILTER_PREVIOUS_OPEN_ATTRIBUTE = "data-yii-debug-filter-previous-open";
const FILTER_TARGET_SELECTOR = "[data-yii-debug-filter-target]";

/**
 * Finds the target controlled by a live-filter input.
 *
 * Most filters sit in a header immediately before their target. Disclosure
 * filters instead share a body with the table they control.
 */
export function findLiveFilterTarget(input) {
  var scope = input.closest(
    "[data-yii-debug-filter-scope], .yii-debug-disclosure-body",
  );

  if (scope) {
    var scopedTarget = scope.querySelector(FILTER_TARGET_SELECTOR);

    if (scopedTarget) {
      return scopedTarget;
    }
  }

  var anchor = input.closest(LIVE_FILTER_ANCHOR_SELECTOR) || input;
  var target = anchor.nextElementSibling;

  while (target && !target.matches(FILTER_TARGET_SELECTOR)) {
    target = target.nextElementSibling;
  }

  return target;
}

/**
 * Applies one query to every searchable row in a diagnostic target.
 *
 * Group visibility and disclosure state are derived from the filtered rows so
 * a single control can search split header lanes and collapsed server groups.
 */
export function applyLiveFilter(target, value) {
  var rows = target.querySelectorAll(LIVE_FILTER_ROW_SELECTOR);
  var query = value.trim().toLowerCase();
  var visible = 0;

  for (var i = 0; i < rows.length; i++) {
    var row = rows[i];
    var matched =
      query === "" || row.textContent.toLowerCase().indexOf(query) !== -1;

    row.hidden = !matched;
    visible += matched ? 1 : 0;
  }

  var groups = target.querySelectorAll("[data-yii-debug-filter-group]");

  for (var groupIndex = 0; groupIndex < groups.length; groupIndex++) {
    var group = groups[groupIndex];
    var groupRows = group.querySelectorAll(LIVE_FILTER_ROW_SELECTOR);
    var groupHasMatch = false;

    for (var rowIndex = 0; rowIndex < groupRows.length; rowIndex++) {
      if (!groupRows[rowIndex].hidden) {
        groupHasMatch = true;
        break;
      }
    }

    group.hidden = query !== "" && !groupHasMatch;
  }

  var details = target.querySelectorAll("[data-yii-debug-filter-details]");

  for (var detailsIndex = 0; detailsIndex < details.length; detailsIndex++) {
    var disclosure = details[detailsIndex];
    var previousOpen = disclosure.getAttribute(FILTER_PREVIOUS_OPEN_ATTRIBUTE);

    if (query === "") {
      disclosure.open =
        (previousOpen ??
          disclosure.getAttribute(FILTER_DEFAULT_OPEN_ATTRIBUTE)) === "true";

      if (previousOpen !== null) {
        disclosure.removeAttribute(FILTER_PREVIOUS_OPEN_ATTRIBUTE);
      }
    } else {
      if (previousOpen === null) {
        disclosure.setAttribute(
          FILTER_PREVIOUS_OPEN_ATTRIBUTE,
          disclosure.open ? "true" : "false",
        );
      }

      if (!disclosure.hidden) {
        disclosure.open = true;
      }
    }
  }

  var empty = target.querySelector("[data-yii-debug-filter-empty]");

  if (empty) {
    empty.hidden = query === "" || visible > 0;
  }

  return {
    total: rows.length,
    unit: target.getAttribute("data-yii-debug-filter-unit") || "rows",
    visible,
  };
}
