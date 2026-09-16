// @vitest-environment jsdom
import assert from "node:assert/strict";
import { afterEach, test } from "vitest";

import {
  createToolbar,
  installLocalStorage,
  installMatchMedia,
  renderToolbar,
  teardownToolbars,
  toolbarPayload,
} from "./toolbar-element-harness.js";

var storage = installLocalStorage({ "yii-debug-toolbar-expanded": "1" });

installMatchMedia();

/**
 * Detaches the fixtures and restores the expanded flag unconditionally: a
 * thrown assertion must not hand the next test a collapsed bar.
 */
afterEach(() => {
  teardownToolbars();
  storage.set("yii-debug-toolbar-expanded", "1");
});

function request(overrides) {
  return Object.assign(
    {
      duration: 12,
      error: false,
      loading: false,
      method: "GET",
      statusCode: 200,
      url: "/api/items",
    },
    overrides || {},
  );
}

function withRequests(requests) {
  var element = renderToolbar(toolbarPayload());

  element.ajaxRequests = requests;
  element.render();

  return element;
}

function badgeClass(element) {
  return element.shadowRoot.querySelector(".ajax-panel .metric-value")
    .className;
}

function rows(element) {
  return element.shadowRoot.querySelectorAll(".ajax-popover tbody tr");
}

test("the AJAX chip counts every request but lists only the last twenty", () => {
  var stack = [];

  for (var i = 0; i < 22; i++) {
    stack.push(request({ url: "/api/items/" + i }));
  }

  var element = withRequests(stack);

  assert.equal(
    element.shadowRoot.querySelector(".ajax-panel .metric-value").textContent,
    "22",
    "Counter must reflect the whole stack.",
  );
  assert.equal(
    rows(element).length,
    20,
    "Popover must show the newest twenty.",
  );
  assert.equal(
    rows(element)[0].querySelector(".ajax-url").textContent,
    "/api/items/2",
    "Window must start after the dropped entries.",
  );
  assert.equal(
    badgeClass(element),
    "metric-value badge-success",
    "Badge: success.",
  );

  element.remove();
});

test("an in-flight request switches the chip to the loading badge", () => {
  var element = withRequests([
    request(),
    request({ loading: true, statusCode: undefined }),
  ]);

  assert.equal(
    badgeClass(element),
    "metric-value badge-loading",
    "Badge: loading.",
  );
  assert.equal(
    rows(element)[1].querySelector(".badge").className,
    "badge badge-loading",
    "In-flight row must carry the loading badge.",
  );

  element.remove();
});

test("a recent failure turns the chip red while an older one does not", () => {
  var element = withRequests([
    request({ error: true, statusCode: 500 }),
    request(),
    request(),
    request(),
    request(),
  ]);

  assert.equal(
    badgeClass(element),
    "metric-value badge-success",
    "A failure outside the recency window must not raise the badge.",
  );

  element.ajaxRequests = [
    request(),
    request({ error: true, statusCode: 500 }),
    request(),
  ];
  element.render();

  assert.equal(
    badgeClass(element),
    "metric-value badge-danger",
    "Badge: danger.",
  );

  element.remove();
});

test("an untracked stack renders the placeholder row", () => {
  var element = withRequests([]);

  assert.equal(
    rows(element)[0].querySelector(".empty").textContent,
    "No AJAX requests tracked yet.",
    "Placeholder must explain the empty popover.",
  );

  element.ajaxRequests = null;
  element.render();

  assert.equal(
    rows(element).length,
    1,
    "A missing stack must behave as empty.",
  );
  assert.equal(
    element.shadowRoot.querySelector(".ajax-panel").getAttribute("aria-label"),
    "AJAX requests: 0",
    "Label must announce an empty stack.",
  );

  element.remove();
});

test("status codes map onto the badge palette", () => {
  var element = withRequests([
    request({ statusCode: 204 }),
    request({ statusCode: 302 }),
    request({ statusCode: 404 }),
    request({ statusCode: 500 }),
    request({ statusCode: "n/a" }),
  ]);
  var badges = Array.prototype.map.call(
    element.shadowRoot.querySelectorAll(".ajax-popover .badge"),
    function (node) {
      return node.className;
    },
  );

  assert.deepEqual(
    badges,
    [
      "badge badge-status-2xx",
      "badge badge-status-3xx",
      "badge badge-status-4xx",
      "badge badge-status-5xx",
      "badge badge-danger",
    ],
    "Palette: 2xx, 3xx, 4xx, 5xx, unparsable.",
  );

  element.remove();
});

test("HTTP verbs map onto the verb palette", () => {
  var element = withRequests([
    request({ method: "GET" }),
    request({ method: "head" }),
    request({ method: "POST" }),
    request({ method: "PUT" }),
    request({ method: "patch" }),
    request({ method: "DELETE" }),
    request({ method: "OPTIONS" }),
    request({ method: undefined }),
  ]);
  var verbs = Array.prototype.map.call(
    element.shadowRoot.querySelectorAll(
      ".ajax-popover tbody tr td:first-child span",
    ),
    function (node) {
      return node.className + ":" + node.textContent;
    },
  );

  assert.deepEqual(
    verbs,
    [
      "verb-get:GET",
      "verb-get:head",
      "verb-post:POST",
      "verb-put:PUT",
      "verb-put:patch",
      "verb-delete:DELETE",
      "verb-other:OPTIONS",
      "verb-other:GET",
    ],
    "Palette: get, post, put, delete, other; a method-less row keeps the neutral class.",
  );

  element.remove();
});

test("a profiled request links to its snapshot and an unprofiled one does not", () => {
  var element = withRequests([
    request({ profile: "tag-1", profilerUrl: "/debug/view?tag=tag-1" }),
    request({ profile: "tag-2" }),
  ]);
  var cells = element.shadowRoot.querySelectorAll(
    ".ajax-popover tbody tr td:last-child",
  );

  assert.equal(
    cells[0].querySelector(".ajax-link").getAttribute("href"),
    "http://localhost:3000/debug/view?tag=tag-1&yii_debug_theme=light",
    "Snapshot link must carry the stamped theme.",
  );
  assert.equal(
    cells[0].querySelector(".ajax-link").getAttribute("data-debug-url"),
    "/debug/view?tag=tag-1",
    "Snapshot link must double as a drawer trigger.",
  );
  assert.equal(
    cells[1].textContent,
    "n/a",
    "Unprofiled requests must read `n/a`.",
  );

  element.remove();
});

test("missing status, duration and URL fall back to placeholders", () => {
  var element = withRequests([
    request({ duration: undefined, statusCode: undefined, url: "<script>" }),
  ]);
  var cells = rows(element)[0].querySelectorAll("td");

  assert.equal(cells[1].textContent, "-", "Missing status must read `-`.");
  assert.equal(cells[3].textContent, "-", "Missing duration must read `-`.");
  assert.equal(
    cells[2].textContent,
    "<script>",
    "URL must be escaped, not parsed.",
  );

  element.remove();

  var timed = withRequests([request({ duration: 42 })]);

  assert.equal(
    timed.shadowRoot.querySelectorAll(".ajax-popover tbody td")[3].textContent,
    "42 ms",
    "A measured request must read its duration.",
  );

  timed.remove();
});

test("new metrics swap the AJAX panel without rebuilding the rest of the bar", () => {
  var element = withRequests([request()]);
  var panels = element.shadowRoot.querySelector(".panels");
  var previous = element.shadowRoot.querySelector(".ajax-panel");

  element.setAjaxRequests([request(), request()]);

  assert.equal(
    element.shadowRoot.querySelector(".panels"),
    panels,
    "Surrounding bar must be preserved.",
  );
  assert.notEqual(
    element.shadowRoot.querySelector(".ajax-panel"),
    previous,
    "AJAX chip must be replaced.",
  );
  assert.equal(
    element.shadowRoot.querySelector(".ajax-panel .metric-value").textContent,
    "2",
    "Counter must follow the new stack.",
  );

  element.remove();
});

test("metrics arriving before a first render are only recorded", () => {
  var detached = createToolbar();

  detached.data = toolbarPayload();
  detached.expanded = true;
  detached.setAjaxRequests([request()]);

  assert.equal(detached.barRoot, null, "No skeleton may be built.");
  assert.equal(
    detached.ajaxRequests.length,
    1,
    "Stack must still be recorded.",
  );

  var blank = createToolbar();

  blank.setAjaxRequests([request()]);

  assert.equal(
    blank.barRoot,
    null,
    "A payload-less toolbar must stay untouched.",
  );

  storage.set("yii-debug-toolbar-expanded", "0");

  var collapsed = renderToolbar(toolbarPayload());
  var bar = collapsed.shadowRoot.querySelector(".bar").innerHTML;

  collapsed.setAjaxRequests([request()]);

  assert.equal(
    collapsed.shadowRoot.querySelector(".bar").innerHTML,
    bar,
    "A collapsed bar must not be redrawn.",
  );

  collapsed.remove();
  storage.set("yii-debug-toolbar-expanded", "1");
});

test("a bar without an AJAX panel is redrawn from scratch", () => {
  var element = withRequests([request()]);

  element.renderError("Debug data is no longer available for this request.");
  element.setAjaxRequests([request(), request()]);

  assert.equal(
    element.shadowRoot.querySelector(".ajax-panel .metric-value").textContent,
    "2",
    "Full render must restore the chip.",
  );
  assert.equal(
    element.shadowRoot.querySelector(".error-message"),
    null,
    "Error message must be replaced.",
  );

  element.remove();
});

test("an empty AJAX fragment leaves the rendered panel untouched", () => {
  var element = withRequests([request()]);
  var previous = element.shadowRoot.querySelector(".ajax-panel");

  element.renderAjaxPanel = function () {
    return "";
  };
  element.setAjaxRequests([request(), request()]);

  assert.equal(
    element.shadowRoot.querySelector(".ajax-panel"),
    previous,
    "Panel must survive an empty fragment.",
  );

  element.remove();
});
