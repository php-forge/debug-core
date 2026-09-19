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

function profiled(tag) {
  return request({ profile: tag, profilerUrl: "/debug/view?tag=" + tag });
}

function withRequests(requests, payload) {
  var element = renderToolbar(payload || toolbarPayload());

  element.ajaxRequests = requests;
  element.render();

  return element;
}

function badgeClass(element) {
  return element.shadowRoot.querySelector(".ajax-toggle .metric-value")
    .className;
}

function rows(element) {
  return element.shadowRoot.querySelectorAll(".ajax-menu .ajax-request");
}

function toggle(element) {
  return element.shadowRoot.querySelector(".ajax-toggle");
}

function click(node) {
  node.dispatchEvent(
    new window.MouseEvent("click", { bubbles: true, cancelable: true }),
  );
}

test("the AJAX chip sits between the strip and the Extensions chip", () => {
  var element = withRequests(
    [],
    toolbarPayload({
      items: [
        { id: "request", title: "Request", url: "/debug/request" },
        {
          extension: true,
          id: "inertia",
          title: "Inertia",
          url: "/debug/inertia",
        },
      ],
    }),
  );
  var wrapper = element.shadowRoot.querySelector(".bar > .ajax");

  assert.equal(
    wrapper.previousElementSibling.className,
    "panels",
    "Chip must follow the strip.",
  );
  assert.equal(
    wrapper.nextElementSibling.className,
    "extensions menu",
    "Chip must precede Extensions.",
  );
  assert.equal(
    toggle(element).getAttribute("data-menu"),
    "ajax",
    "Chip must name its menu.",
  );
  assert.equal(
    toggle(element).getAttribute("aria-controls"),
    "ajax-menu",
    "Chip must reference its menu.",
  );
  assert.equal(
    element.shadowRoot.querySelector(".ajax-menu").getAttribute("aria-label"),
    "AJAX requests",
    "Menu must be labelled.",
  );

  element.remove();
});

test("the AJAX chip counts every request but lists only the last twenty", () => {
  var stack = [];

  for (var i = 0; i < 22; i++) {
    stack.push(request({ url: "/api/items/" + i }));
  }

  var element = withRequests(stack);

  assert.equal(
    toggle(element).querySelector(".metric-value").textContent,
    "22",
    "Counter must reflect the whole stack.",
  );
  assert.equal(
    toggle(element).getAttribute("aria-label"),
    "AJAX requests: 22",
    "Label must announce the whole stack.",
  );
  assert.equal(rows(element).length, 20, "Menu must list the newest twenty.");
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

test("an untracked stack renders the placeholder entry", () => {
  var element = withRequests([]);

  assert.equal(
    element.shadowRoot.querySelector(".ajax-menu .empty").textContent,
    "No AJAX requests tracked yet.",
    "Placeholder must explain the empty menu.",
  );
  assert.equal(rows(element).length, 0, "No row may be listed.");

  element.ajaxRequests = null;
  element.render();

  assert.ok(
    element.shadowRoot.querySelector(".ajax-menu .empty"),
    "A missing stack must behave as empty.",
  );
  assert.equal(
    toggle(element).getAttribute("aria-label"),
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
    element.shadowRoot.querySelectorAll(".ajax-menu .badge"),
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
    element.shadowRoot.querySelectorAll(".ajax-menu .ajax-verb"),
    function (node) {
      return node.className + ":" + node.textContent;
    },
  );

  assert.deepEqual(
    verbs,
    [
      "ajax-verb verb-get:GET",
      "ajax-verb verb-get:head",
      "ajax-verb verb-post:POST",
      "ajax-verb verb-put:PUT",
      "ajax-verb verb-put:patch",
      "ajax-verb verb-delete:DELETE",
      "ajax-verb verb-other:OPTIONS",
      "ajax-verb verb-other:GET",
    ],
    "Palette: get, post, put, delete, other; a method-less row keeps the neutral class.",
  );

  element.remove();
});

test("a profiled request links to its capture and an unprofiled one stays inert", () => {
  var element = withRequests([
    profiled("tag-1"),
    request({ profile: "tag-2" }),
  ]);
  var entries = rows(element);

  assert.equal(entries[0].tagName, "A", "Profiled row must be a link.");
  assert.equal(
    entries[0].getAttribute("href"),
    "http://localhost:3000/debug/view?tag=tag-1&yii_debug_theme=light",
    "Capture link must carry the stamped theme.",
  );
  assert.equal(
    entries[0].getAttribute("data-debug-url"),
    "/debug/view?tag=tag-1",
    "Capture link must double as a drawer trigger.",
  );
  assert.equal(entries[1].tagName, "SPAN", "Unprofiled row must be inert.");
  assert.equal(
    entries[1].hasAttribute("data-debug-url"),
    false,
    "Unprofiled row must not trigger the drawer.",
  );

  element.remove();
});

test("the chip opens the menu, moves focus to the first row, and closes it again", () => {
  var element = withRequests([profiled("tag-1")]);
  var root = element.shadowRoot;

  click(toggle(element));

  assert.equal(element.openMenu, "ajax", "Menu must be open.");
  assert.ok(
    root.querySelector(".ajax").classList.contains("is-open"),
    "Wrapper must carry the open modifier.",
  );
  assert.equal(
    toggle(element).getAttribute("aria-expanded"),
    "true",
    "Chip must announce the open menu.",
  );
  assert.equal(
    root.activeElement,
    rows(element)[0],
    "Focus must land on the first row.",
  );

  click(toggle(element));

  assert.equal(element.openMenu, null, "Menu must be closed.");
  assert.equal(
    toggle(element).getAttribute("aria-expanded"),
    "false",
    "Chip must announce the closed menu.",
  );

  element.remove();
});

test("opening a row shows its capture in the drawer and closes the menu", () => {
  var element = withRequests(
    [profiled("tag-1"), profiled("tag-2")],
    toolbarPayload({
      items: [{ id: "request", title: "Request", url: "/debug/request" }],
    }),
  );
  var root = element.shadowRoot;

  click(toggle(element));
  click(rows(element)[1]);

  assert.equal(
    element.openMenu,
    null,
    "Menu must close with the drawer opening.",
  );
  assert.equal(element.drawerOpen, true, "Drawer must open.");
  assert.equal(
    element.activeUrl,
    "/debug/view?tag=tag-2",
    "Capture must reach the drawer.",
  );
  assert.ok(
    toggle(element).classList.contains("panel-active"),
    "Chip must mark the open capture.",
  );
  assert.deepEqual(
    Array.prototype.map.call(rows(element), function (row) {
      return row.classList.contains("is-active");
    }),
    [false, true],
    "Only the open capture may be marked.",
  );

  click(root.querySelector('[title="Request"]'));

  assert.equal(
    toggle(element).classList.contains("panel-active"),
    false,
    "Another panel in the drawer must clear the chip.",
  );
  assert.equal(
    root.querySelector(".ajax-request.is-active"),
    null,
    "Another panel in the drawer must clear the rows.",
  );

  element.remove();
});

test("new metrics swap the AJAX menu without rebuilding the rest of the bar", () => {
  var element = withRequests([request()]);
  var panels = element.shadowRoot.querySelector(".panels");
  var previous = element.shadowRoot.querySelector(".ajax");

  element.setAjaxRequests([request(), request()]);

  assert.equal(
    element.shadowRoot.querySelector(".panels"),
    panels,
    "Surrounding bar must be preserved.",
  );
  assert.notEqual(
    element.shadowRoot.querySelector(".ajax"),
    previous,
    "AJAX menu must be replaced.",
  );
  assert.equal(
    toggle(element).querySelector(".metric-value").textContent,
    "2",
    "Counter must follow the new stack.",
  );

  element.remove();
});

test("a refresh keeps an open menu open and focus on its chip", () => {
  var element = withRequests([request()]);
  var root = element.shadowRoot;

  toggle(element).focus();
  click(toggle(element));
  element.setAjaxRequests([request(), request()]);

  assert.equal(element.openMenu, "ajax", "Menu must stay open.");
  assert.ok(
    root.querySelector(".ajax").classList.contains("is-open"),
    "Replacement must be rendered open.",
  );
  assert.equal(
    toggle(element).getAttribute("aria-expanded"),
    "true",
    "Replacement chip must be rendered as expanded.",
  );
  assert.equal(
    root.activeElement,
    toggle(element),
    "Focus must move to the replacement chip.",
  );

  element.remove();
});

test("a refresh keeps focus on the focused row while it stays listed", () => {
  var element = withRequests([profiled("tag-1")]);
  var root = element.shadowRoot;
  var stack = [profiled("tag-1")];

  click(toggle(element));

  var previous = rows(element)[0];

  stack.push(request());
  element.setAjaxRequests(stack);

  assert.notEqual(rows(element)[0], previous, "Row must be replaced.");
  assert.equal(
    root.activeElement,
    rows(element)[0],
    "Focus must move to the replacement row.",
  );

  for (var i = 0; i < 20; i++) {
    stack.push(request({ url: "/api/items/" + i }));
  }

  element.setAjaxRequests(stack);

  assert.equal(
    root.activeElement,
    toggle(element),
    "A row pushed out of the list must hand focus to the chip.",
  );

  element.remove();
});

test("a refresh leaves focus alone when it is outside the menu", () => {
  var element = withRequests([request()]);
  var root = element.shadowRoot;
  var collapse = root.querySelector(".toggle-toolbar");

  collapse.focus();
  element.setAjaxRequests([request(), request()]);

  assert.equal(root.activeElement, collapse, "Focus must not move.");

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

test("a bar without an AJAX menu is redrawn from scratch", () => {
  var element = withRequests([request()]);

  element.renderError("Debug data is no longer available for this request.");
  element.setAjaxRequests([request(), request()]);

  assert.equal(
    toggle(element).querySelector(".metric-value").textContent,
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

test("an empty AJAX fragment leaves the rendered menu untouched", () => {
  var element = withRequests([request()]);
  var previous = element.shadowRoot.querySelector(".ajax");

  element.renderAjaxMenu = function () {
    return "";
  };
  element.setAjaxRequests([request(), request()]);

  assert.equal(
    element.shadowRoot.querySelector(".ajax"),
    previous,
    "Menu must survive an empty fragment.",
  );

  element.remove();
});

test("missing status, duration and URL fall back to placeholders", () => {
  var element = withRequests([
    request({ duration: undefined, statusCode: undefined, url: "<script>" }),
  ]);
  var row = rows(element)[0];

  assert.equal(
    row.querySelector(".badge").textContent,
    "-",
    "Missing status must read `-`.",
  );
  assert.equal(
    row.querySelector(".ajax-time").textContent,
    "-",
    "Missing duration must read `-`.",
  );
  assert.equal(
    row.querySelector(".ajax-url").textContent,
    "<script>",
    "URL must be escaped, not parsed.",
  );
  assert.equal(
    row.getAttribute("title"),
    "<script>",
    "Row title must repeat the full URL.",
  );

  element.remove();

  var timed = withRequests([request({ duration: 42 })]);

  assert.equal(
    rows(timed)[0].querySelector(".ajax-time").textContent,
    "42 ms",
    "A measured request must read its duration.",
  );

  timed.remove();
});
