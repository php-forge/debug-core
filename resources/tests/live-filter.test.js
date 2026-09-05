import assert from "node:assert/strict";
import { test } from "vitest";

import {
  applyLiveFilter,
  findLiveFilterTarget,
  LIVE_FILTER_ANCHOR_SELECTOR,
  LIVE_FILTER_ROW_SELECTOR,
} from "../src/core/live-filter.js";

function row(text) {
  return { hidden: false, textContent: text };
}

function group(rows, defaultOpen = null) {
  var attributes = new Map();

  if (defaultOpen !== null) {
    attributes.set("data-yii-debug-filter-default-open", defaultOpen);
  }

  return {
    hidden: false,
    open: false,
    getAttribute(name) {
      return attributes.get(name) ?? null;
    },
    removeAttribute(name) {
      attributes.delete(name);
    },
    setAttribute(name, value) {
      attributes.set(name, value);
    },
    querySelectorAll(selector) {
      assert.equal(selector, LIVE_FILTER_ROW_SELECTOR);

      return rows;
    },
  };
}

test("live filter target discovery prefers a shared disclosure body", () => {
  var scopedTarget = {};
  var scope = {
    querySelector(selector) {
      assert.equal(selector, "[data-yii-debug-filter-target]");

      return scopedTarget;
    },
  };
  var input = {
    closest(selector) {
      assert.equal(
        selector,
        "[data-yii-debug-filter-scope], .yii-debug-disclosure-body",
      );

      return scope;
    },
  };

  assert.equal(findLiveFilterTarget(input), scopedTarget);
});

test("live filter target discovery falls back to following header siblings", () => {
  var target = {
    matches(selector) {
      assert.equal(selector, "[data-yii-debug-filter-target]");

      return true;
    },
  };
  var spacer = {
    nextElementSibling: target,
    matches(selector) {
      assert.equal(selector, "[data-yii-debug-filter-target]");

      return false;
    },
  };
  var anchor = { nextElementSibling: spacer };
  var scope = {
    querySelector(selector) {
      assert.equal(selector, "[data-yii-debug-filter-target]");

      return null;
    },
  };
  var input = {
    closest(selector) {
      if (
        selector === "[data-yii-debug-filter-scope], .yii-debug-disclosure-body"
      ) {
        return scope;
      }

      assert.equal(selector, LIVE_FILTER_ANCHOR_SELECTOR);

      return anchor;
    },
  };

  assert.equal(findLiveFilterTarget(input), target);
});

test("live filter target discovery tolerates an unscoped orphan input", () => {
  var input = {
    nextElementSibling: null,
    closest() {
      return null;
    },
  };

  assert.equal(findLiveFilterTarget(input), null);
});

function target({
  rows,
  groups = [],
  details = [],
  empty = null,
  unit = null,
}) {
  return {
    getAttribute(name) {
      return name === "data-yii-debug-filter-unit" ? unit : null;
    },
    querySelector(selector) {
      assert.equal(selector, "[data-yii-debug-filter-empty]");

      return empty;
    },
    querySelectorAll(selector) {
      if (selector === LIVE_FILTER_ROW_SELECTOR) {
        return rows;
      }

      if (selector === "[data-yii-debug-filter-group]") {
        return groups;
      }

      assert.equal(selector, "[data-yii-debug-filter-details]");

      return details;
    },
  };
}

test("live filter spans ledger groups and reports a custom unit", () => {
  var inbound = row("Accept text/html");
  var outbound = row("Content-Type application/json");
  var inboundGroup = group([inbound]);
  var outboundGroup = group([outbound]);
  var empty = { hidden: true };

  var result = applyLiveFilter(
    target({
      rows: [inbound, outbound],
      groups: [inboundGroup, outboundGroup],
      empty,
      unit: "fields",
    }),
    "  ACCEPT  ",
  );

  assert.deepEqual(result, { total: 2, unit: "fields", visible: 1 });
  assert.equal(inbound.hidden, false);
  assert.equal(outbound.hidden, true);
  assert.equal(inboundGroup.hidden, false);
  assert.equal(outboundGroup.hidden, true);
  assert.equal(empty.hidden, true);
});

test("live filter exposes its empty state when no diagnostic row matches", () => {
  var request = row("REQUEST_METHOD GET");
  var requestGroup = group([request]);
  var empty = { hidden: true };

  var result = applyLiveFilter(
    target({
      rows: [request],
      groups: [requestGroup],
      details: [requestGroup],
      empty,
    }),
    "missing",
  );

  assert.deepEqual(result, { total: 1, unit: "rows", visible: 0 });
  assert.equal(request.hidden, true);
  assert.equal(requestGroup.hidden, true);
  assert.equal(requestGroup.open, false);
  assert.equal(empty.hidden, false);
});

test("live filter opens a collapsed group containing a match", () => {
  var mirror = row("HTTP_ACCEPT text/html");
  var mirrors = group([mirror], "false");

  var result = applyLiveFilter(
    target({ rows: [mirror], groups: [mirrors], details: [mirrors] }),
    "http_accept",
  );

  assert.equal(result.visible, 1);
  assert.equal(mirrors.hidden, false);
  assert.equal(mirrors.open, true);
});

test("clearing the filter restores disclosure defaults and every row", () => {
  var request = row("REQUEST_URI /");
  var mirror = row("HTTP_ACCEPT text/html");
  request.hidden = true;
  mirror.hidden = true;
  var requestGroup = group([request]);
  var closed = group([mirror], "false");
  var opened = group([request], "true");
  closed.hidden = true;
  closed.open = true;

  var result = applyLiveFilter(
    target({
      rows: [request, mirror],
      groups: [requestGroup, closed],
      details: [closed, opened],
    }),
    "",
  );

  assert.deepEqual(result, { total: 2, unit: "rows", visible: 2 });
  assert.equal(request.hidden, false);
  assert.equal(mirror.hidden, false);
  assert.equal(requestGroup.hidden, false);
  assert.equal(closed.hidden, false);
  assert.equal(closed.open, false);
  assert.equal(opened.open, true);
});

test("clearing the filter restores user-selected disclosure states", () => {
  var primaryRow = row("REQUEST_URI /");
  var duplicateRow = row("HTTP_ACCEPT text/html");
  var closedByUser = group([primaryRow], "true");
  var openedByUser = group([duplicateRow], "false");
  closedByUser.open = false;
  openedByUser.open = true;
  var filterTarget = target({
    rows: [primaryRow, duplicateRow],
    groups: [closedByUser, openedByUser],
    details: [closedByUser, openedByUser],
  });

  applyLiveFilter(filterTarget, "request_uri");

  assert.equal(closedByUser.open, true);
  assert.equal(openedByUser.open, true);

  applyLiveFilter(filterTarget, "http_accept");

  applyLiveFilter(filterTarget, "");

  assert.equal(closedByUser.open, false);
  assert.equal(openedByUser.open, true);
  assert.equal(
    closedByUser.getAttribute("data-yii-debug-filter-previous-open"),
    null,
  );
  assert.equal(
    openedByUser.getAttribute("data-yii-debug-filter-previous-open"),
    null,
  );
});

test("route filtering counts entries and restores independent disclosure state", () => {
  var home = row("GET / home HomeAction");
  var article = row("POST /article article/view ArticleAction Authentication");
  var homeDetails = group([], "false");
  var articleDetails = group([], "false");
  var empty = { hidden: true };
  homeDetails.open = true;
  var routes = target({
    rows: [home, article],
    details: [homeDetails, articleDetails],
    empty,
    unit: "routes",
  });

  assert.deepEqual(applyLiveFilter(routes, "authentication"), {
    total: 2,
    visible: 1,
    unit: "routes",
  });
  assert.equal(home.hidden, true);
  assert.equal(article.hidden, false);
  assert.equal(articleDetails.open, true);
  assert.equal(empty.hidden, true);

  applyLiveFilter(routes, "missing-route");
  assert.equal(empty.hidden, false);

  applyLiveFilter(routes, "");
  assert.equal(home.hidden, false);
  assert.equal(article.hidden, false);
  assert.equal(homeDetails.open, true);
  assert.equal(articleDetails.open, false);
  assert.equal(empty.hidden, true);
});

test("filters in separate disclosure bodies stay independent", () => {
  var sessionRows = [row("user 1"), row("theme dark")];
  var flashRows = [row("notice Saved"), row("warning Review")];
  var sessionTarget = target({ rows: sessionRows });
  var flashTarget = target({ rows: flashRows });
  var scopedInput = (filterTarget) => ({
    closest() {
      return { querySelector: () => filterTarget };
    },
  });
  var sessionInput = scopedInput(sessionTarget);
  var flashInput = scopedInput(flashTarget);

  applyLiveFilter(findLiveFilterTarget(sessionInput), "user");
  assert.deepEqual(
    sessionRows.map((item) => item.hidden),
    [false, true],
  );
  assert.deepEqual(
    flashRows.map((item) => item.hidden),
    [false, false],
  );

  applyLiveFilter(findLiveFilterTarget(flashInput), "warning");
  assert.deepEqual(
    sessionRows.map((item) => item.hidden),
    [false, true],
  );
  assert.deepEqual(
    flashRows.map((item) => item.hidden),
    [true, false],
  );

  applyLiveFilter(findLiveFilterTarget(sessionInput), "");
  assert.deepEqual(
    sessionRows.map((item) => item.hidden),
    [false, false],
  );
  assert.deepEqual(
    flashRows.map((item) => item.hidden),
    [true, false],
  );
});
