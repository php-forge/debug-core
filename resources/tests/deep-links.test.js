import assert from "node:assert/strict";
import test from "node:test";

import {
  enhanceSectionPermalinks,
  fragmentId,
  headingLabel,
  headingSlug,
  initSectionPermalinks,
  revealDeepLink,
} from "../src/core/deep-links.js";

function makeDocument(byId) {
  return {
    createElement(tagName) {
      return {
        setAttribute(name, value) {
          this[name] = value;
        },
        tagName,
      };
    },
    getElementById(id) {
      return byId[id] || null;
    },
  };
}

function makeHeading(documentValue, options) {
  var attributes = {};
  var classes = new Set(options.classes || []);

  return {
    appendChild(node) {
      this.appended = node;
    },
    childNodes: [{ nodeType: 3, textContent: options.text || "" }],
    classList: {
      add(value) {
        classes.add(value);
      },
      contains(value) {
        return classes.has(value);
      },
    },
    closest(selector) {
      return selector === 'a, button, summary, [aria-hidden="true"]'
        ? options.nested || null
        : null;
    },
    getAttribute(name) {
      return attributes[name] || null;
    },
    id: options.id || "",
    ownerDocument: documentValue,
    setAttribute(name, value) {
      attributes[name] = value;
    },
    textContent: options.text || "",
  };
}

test("fragment ids tolerate missing, empty, and malformed hashes", () => {
  assert.equal(fragmentId(null), "");
  assert.equal(fragmentId({}), "");
  assert.equal(fragmentId({ hash: "#" }), "");
  assert.equal(fragmentId({ hash: "#plain" }), "plain");
  assert.equal(fragmentId({ hash: "#%" }), "");
  assert.equal(fragmentId({ hash: { length: 1, slice: () => "x" } }), "");
});

test("heading labels tolerate comments, empty text, and stub nodes", () => {
  var comment = { nodeType: 8 };
  var emptyText = { nodeType: 3, textContent: null };
  var plainElement = {
    childNodes: [{ nodeType: 3, textContent: "Routes" }],
    nodeType: 1,
  };
  var childlessElement = {
    matches() {
      return false;
    },
    nodeType: 1,
  };

  assert.equal(
    headingLabel({
      childNodes: [comment, emptyText, plainElement, childlessElement],
      textContent: "ignored",
    }),
    "Routes",
  );
  assert.equal(headingLabel({ textContent: "Fallback " }), "Fallback");
  assert.equal(headingLabel({}), "");
});

test("heading labels space nested text and skip non-element containers", () => {
  var hiddenContainer = {
    childNodes: [{ nodeType: 3, textContent: "hidden" }],
    nodeType: 8,
  };
  var wrapped = {
    childNodes: [
      { nodeType: 3, textContent: "perma" },
      { nodeType: 3, textContent: "links" },
    ],
    nodeType: 1,
  };

  assert.equal(
    headingLabel({
      childNodes: [
        { nodeType: 3, textContent: "Deep" },
        hiddenContainer,
        wrapped,
      ],
      textContent: "unused",
    }),
    "Deep perma links",
  );
});

test("section permalinks bind once, skip decorative headings, and dodge id collisions", () => {
  var byId = {};
  var documentValue = makeDocument(byId);
  var bound = makeHeading(documentValue, { text: "Bound" });
  bound.setAttribute("data-yii-debug-permalink-bound", "true");
  var srOnly = makeHeading(documentValue, {
    classes: ["yii-debug-sr-only"],
    text: "Hidden",
  });
  var nested = makeHeading(documentValue, { nested: {}, text: "Nested" });
  var unlabeled = makeHeading(documentValue, { text: "  " });
  var keepsId = makeHeading(documentValue, { id: "custom-id", text: "Custom" });
  var collides = makeHeading(documentValue, { text: "Logs" });
  var shifted = makeHeading(documentValue, { text: "Cache" });
  var chained = makeHeading(documentValue, { text: "Deep" });
  var fresh = makeHeading(documentValue, { text: "Request" });
  byId["yii-debug-section-logs"] = {};
  byId["yii-debug-section-logs-2"] = collides;
  byId["yii-debug-section-cache"] = {};
  byId["yii-debug-section-deep"] = {};
  byId["yii-debug-section-deep-2"] = {};
  var queried = [];
  var root = {
    querySelectorAll(selector) {
      queried.push(selector);

      return [
        bound,
        srOnly,
        nested,
        unlabeled,
        keepsId,
        collides,
        shifted,
        chained,
        fresh,
      ];
    },
  };

  enhanceSectionPermalinks(root);

  assert.deepEqual(queried, ["#yii-debug-main h2, #yii-debug-main h3"]);
  assert.equal(bound.appended, undefined);
  assert.equal(srOnly.appended, undefined);
  assert.equal(nested.appended, undefined);
  assert.equal(unlabeled.appended, undefined);
  assert.equal(keepsId.id, "custom-id");
  assert.equal(keepsId.appended.href, "#custom-id");
  assert.equal(collides.id, "yii-debug-section-logs-2");
  assert.equal(shifted.id, "yii-debug-section-cache-2");
  assert.equal(chained.id, "yii-debug-section-deep-3");
  assert.equal(fresh.id, "yii-debug-section-request");
  assert.equal(fresh.appended.tagName, "a");
  assert.equal(fresh.appended.className, "yii-debug-heading-permalink");
  assert.equal(fresh.appended.href, "#yii-debug-section-request");
  assert.equal(fresh.appended.textContent, "#");
  assert.equal(fresh.appended["aria-label"], "Permalink to Request");
  assert.equal(fresh.appended.title, "Permalink to Request");
  assert.equal(fresh.classList.contains("yii-debug-has-permalink"), true);
  assert.equal(fresh.getAttribute("data-yii-debug-permalink-bound"), "true");
});

test("permalink init reveals targets on load and history navigation", () => {
  var listeners = {};
  var reveals = 0;
  var heading = makeHeading(makeDocument({}), { text: "Overview" });
  var target = {
    classList: {
      add() {},
    },
    scrollIntoView(options) {
      this.scrollOptions = options;
    },
  };
  var windowValue = {
    addEventListener(name, handler) {
      listeners[name] = handler;
    },
    location: { hash: "#found" },
    setTimeout(callback) {
      callback();
    },
  };
  var root = {
    getElementById(id) {
      reveals += id === "found" ? 1 : 0;

      return target;
    },
    querySelectorAll(selector) {
      return selector === "#yii-debug-main h2, #yii-debug-main h3"
        ? [heading]
        : [];
    },
  };

  initSectionPermalinks(root, windowValue);
  listeners.hashchange();
  listeners.popstate();

  assert.equal(reveals, 3);
  assert.equal(heading.getAttribute("data-yii-debug-permalink-bound"), "true");
  assert.deepEqual(target.scrollOptions, { block: "start" });
});

test("deep-link reveal climbs nested disclosures and honors the scroll flag", () => {
  var outer = { open: false, parentElement: null };
  var inner = {
    open: false,
    parentElement: {
      closest(selector) {
        return selector === "details" ? outer : null;
      },
    },
  };
  var target = {
    classList: {
      add() {},
    },
    closest() {
      return inner;
    },
    scrollIntoView() {
      this.scrolled = true;
    },
  };
  var root = {
    getElementById() {
      return target;
    },
    querySelectorAll() {
      return [];
    },
  };

  var plain = {
    classList: {
      add() {},
    },
  };
  var plainRoot = {
    getElementById() {
      return plain;
    },
    querySelectorAll() {
      return [];
    },
  };

  assert.equal(revealDeepLink(root, { hash: "#deep" }, false), target);
  assert.equal(inner.open, true);
  assert.equal(outer.open, true);
  assert.equal(target.scrolled, undefined);
  assert.equal(revealDeepLink(plainRoot, { hash: "#deep" }, true), plain);
  assert.equal(revealDeepLink({}, { hash: "#deep" }, true), null);
  assert.equal(revealDeepLink(root, { hash: "" }, true), null);
});

test("heading slugs are stable, readable, and safe for fragments", () => {
  assert.equal(headingSlug("Request Headers"), "request-headers");
  assert.equal(headingSlug("Configuración / PHP"), "configuracion-php");
  assert.equal(headingSlug("***"), "section");
  assert.equal(headingSlug(""), "section");
});

test("heading labels ignore dynamic counters when deriving permalinks", () => {
  var skipSelector =
    ".yii-debug-section-count, .yii-debug-badge, .yii-debug-panel-heading-kind, " +
    '.yii-debug-heading-permalink, [data-yii-debug-count], [aria-hidden="true"]';
  var text = { nodeType: 3, textContent: "Logs " };
  var count = {
    childNodes: [{ nodeType: 3, textContent: "80" }],
    matches(selector) {
      return selector === skipSelector;
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
  var removed = [];
  var oldTarget = {
    classList: {
      remove(value) {
        removed.push(value);
      },
    },
  };
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
    scrollIntoView(options) {
      this.scrollOptions = options;
    },
  };
  var root = {
    getElementById(id) {
      return id === "request%20headers" ? target : null;
    },
    querySelectorAll(selector) {
      return selector === ".yii-debug-deep-link-target" ? [oldTarget] : [];
    },
  };

  assert.equal(
    revealDeepLink(root, { hash: "#request%2520headers" }, true),
    target,
  );
  assert.equal(details.open, true);
  assert.deepEqual(removed, ["yii-debug-deep-link-target"]);
  assert.equal(classes.has("yii-debug-deep-link-target"), true);
  assert.deepEqual(target.scrollOptions, { block: "start" });
  assert.equal(revealDeepLink(root, { hash: "#missing" }, true), null);
});
