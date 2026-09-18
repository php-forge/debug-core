/**
 * GridView filter row → URL bridge. The 22.0 shell ships without jQuery /
 * yii.gridView.js, so each filter input drives URL params by hand. The regex
 * matches any Yii form name pattern `<FormName>[<attr>]`, which means the
 * bridge works for the index page (Debug[…]) and every panel (Db[…], Log[…],
 * Profile[…], Event[…], Mail[…], User[…], …) without per-page wiring. <select>
 * filters apply on change, text inputs apply on Enter (immediate), on blur
 * (when the dev tabs out), and after a 650 ms idle while typing. Each apply
 * rebuilds the URL keeping every other query param intact and drops the page
 * param so we always land on page 1.
 *
 * The bridge owns a debounce timer, so the page bootstrap starts it explicitly
 * and `disposeGridNavigation()` drops both the timer and the listeners.
 *
 * Usage example:
 *
 * ```js
 * initGridNavigation(document);
 * disposeGridNavigation();
 * ```
 */

/** Session key the caret position is parked under across the reload. */
const FILTER_FOCUS_KEY = "yii-debug-grid-filter-focus";

/** Idle window a typed filter waits for before it rebuilds the URL. */
const IDLE_MS = 650;

/** Shape of a Yii form input name, the only one the bridge drives. */
const FORM_INPUT = /^[A-Za-z][A-Za-z0-9_]*\[[^\]]+\]$/;

var pending = null;
var root = null;

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

  var inputs = root.getElementsByName(stored.name);
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
  root.documentElement.setAttribute("aria-busy", "true");
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

/**
 * Page-size selector inside GridView footers. Picks up the change event,
 * rewrites the `per-page` query param while keeping every other filter/sort
 * intact, and reloads the panel.
 */
function onPageSize(event) {
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
}

function onChange(event) {
  if (!nameMatchesFilter(event.target)) {
    return;
  }

  if (pending && pending.input === event.target) {
    clearTimeout(pending.timeout);
    pending = null;
  }

  if (event.target.tagName === "SELECT" || event.target.tagName === "INPUT") {
    apply(event.target);
  }
}

function onInput(event) {
  if (event.target.tagName !== "INPUT" || event.target.type === "submit") {
    return;
  }
  if (!nameMatchesFilter(event.target)) {
    return;
  }

  scheduleApply(event.target);
}

function onKeyDown(event) {
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
}

function onFocusOut(event) {
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
}

/**
 * Binds the page-size selector and the filter row, then restores the caret the
 * previous navigation parked.
 */
export function initGridNavigation(documentValue) {
  root = documentValue;

  root.addEventListener("change", onPageSize);
  root.addEventListener("change", onChange);
  root.addEventListener("input", onInput);
  root.addEventListener("keydown", onKeyDown);
  root.addEventListener("focusout", onFocusOut);

  restoreFocus();
}

/** Releases the bridge and drops whatever apply was still scheduled. */
export function disposeGridNavigation() {
  if (pending) {
    clearTimeout(pending.timeout);
    pending = null;
  }

  if (!root) {
    return;
  }

  root.removeEventListener("change", onPageSize);
  root.removeEventListener("change", onChange);
  root.removeEventListener("input", onInput);
  root.removeEventListener("keydown", onKeyDown);
  root.removeEventListener("focusout", onFocusOut);
  root = null;
}
