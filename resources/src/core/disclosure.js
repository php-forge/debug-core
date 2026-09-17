import { closest } from "./shared.js";
import { dropdownNavigationIndex } from "./dropdown.js";

/**
 * Click-driven disclosure layers of the debugger page: dropdown menus,
 * collapsible sections, the "Show more" cell boxes and the click-to-reveal
 * controls guarding sensitive values.
 *
 * Keyboard handling stays with the page bootstrap, which owns the precedence
 * between the layers; this module exposes the two steps that handler needs
 * ({@link focusDropdownItem} and {@link dismissDropdowns}).
 *
 * Usage example:
 *
 * ```js
 * prepareCellMoreControls();
 * document.addEventListener("click", onDisclosureClick);
 * ```
 */

/** Selector of the trigger opening a dropdown menu. */
const DROPDOWN_TRIGGER = '[data-yii-debug-toggle="dropdown"]';

/** Keys the dropdown menu answers with a roving focus move. */
const DROPDOWN_NAVIGATION_KEYS = ["ArrowDown", "ArrowUp", "Home", "End"];

function findToggle(node, kind) {
  return closest(node, '[data-yii-debug-toggle="' + kind + '"]');
}

function dropdownItems(menu) {
  return Array.from(
    menu.querySelectorAll(
      'a[href], button:not([disabled]), [role="menuitem"][tabindex]',
    ),
  ).filter(function (item) {
    return !item.hidden && item.getAttribute("aria-hidden") !== "true";
  });
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

/** Closes every open dropdown but the one owning `except`. */
function hideDropdowns(except) {
  var wrappers = document.querySelectorAll(".yii-debug-dropdown.is-open");
  for (var i = 0; i < wrappers.length; i++) {
    var menu = wrappers[i].querySelector(".yii-debug-dropdown-menu");
    if (except && menu === except) {
      continue;
    }
    wrappers[i].classList.remove("is-open");
    var trigger = wrappers[i].querySelector(DROPDOWN_TRIGGER);
    if (trigger) {
      trigger.setAttribute("aria-expanded", "false");
    }
  }
}

/**
 * Moves focus inside the dropdown the event started in.
 *
 * @returns {boolean} `true` when the key belonged to an open-able dropdown, so
 * the caller stops before the layers behind it answer the same key.
 */
export function focusDropdownItem(event) {
  var dropdownWrapper = closest(event.target, ".yii-debug-dropdown");
  var dropdownTrigger = dropdownWrapper
    ? dropdownWrapper.querySelector(DROPDOWN_TRIGGER)
    : null;
  var dropdownMenu = dropdownWrapper
    ? dropdownWrapper.querySelector(".yii-debug-dropdown-menu")
    : null;

  if (
    dropdownWrapper &&
    dropdownTrigger &&
    dropdownMenu &&
    DROPDOWN_NAVIGATION_KEYS.indexOf(event.key) !== -1
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
      return true;
    }

    event.preventDefault();
    hideDropdowns(dropdownMenu);
    dropdownWrapper.classList.add("is-open");
    dropdownTrigger.setAttribute("aria-expanded", "true");

    items[nextItem].focus();

    return true;
  }

  return false;
}

/**
 * Closes every dropdown and returns focus to the trigger of the one that was
 * open.
 *
 * @returns {boolean} `true` when a dropdown absorbed the dismissal, so the
 * layer behind it keeps its own Escape.
 */
export function dismissDropdowns() {
  var openDropdown = document.querySelector(".yii-debug-dropdown.is-open");
  var dropdownWasOpen = Boolean(openDropdown);
  var openDropdownTrigger = openDropdown
    ? openDropdown.querySelector(DROPDOWN_TRIGGER)
    : null;

  hideDropdowns(null);

  if (openDropdownTrigger) {
    openDropdownTrigger.focus();
  }

  return dropdownWasOpen;
}

/** Toggles the disclosure layer a click landed on, closing the menus behind it. */
export function onDisclosureClick(event) {
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
    var target = targetSelector ? document.querySelector(targetSelector) : null;
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
