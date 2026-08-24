import assert from "node:assert/strict";
import test from "node:test";

import {
  headingLabel,
  headingSlug,
  revealDeepLink,
} from "../src/core/deep-links.js";

test("heading slugs are stable, readable, and safe for fragments", () => {
  assert.equal(headingSlug("Request Headers"), "request-headers");
  assert.equal(headingSlug("Configuración / PHP"), "configuracion-php");
  assert.equal(headingSlug("***"), "section");
});

test("heading labels ignore dynamic counters when deriving permalinks", () => {
  var text = { nodeType: 3, textContent: "Logs " };
  var count = {
    childNodes: [{ nodeType: 3, textContent: "80" }],
    matches(selector) {
      return selector.includes(".yii-debug-section-count");
    },
    nodeType: 1,
  };

  assert.equal(
    headingLabel({ childNodes: [text, count], textContent: "Logs 80" }),
    "Logs",
  );
  assert.equal(
    headingSlug(headingLabel({ childNodes: [text, count] })),
    "logs",
  );
});

test("deep-link reveal opens disclosures and marks only the active target", () => {
  var oldTarget = { classList: { remove() {} } };
  var details = { open: false, parentElement: null };
  var classes = new Set();
  var target = {
    classList: {
      add(value) {
        classes.add(value);
      },
    },
    closest(selector) {
      return selector === "details" ? details : null;
    },
    scrollIntoView() {
      this.scrolled = true;
    },
  };
  var root = {
    getElementById(id) {
      return id === "request%20headers" ? target : null;
    },
    querySelectorAll() {
      return [oldTarget];
    },
  };

  assert.equal(
    revealDeepLink(root, { hash: "#request%2520headers" }, true),
    target,
  );
  assert.equal(details.open, true);
  assert.equal(classes.has("yii-debug-deep-link-target"), true);
  assert.equal(target.scrolled, true);
  assert.equal(revealDeepLink(root, { hash: "#missing" }, true), null);
});
