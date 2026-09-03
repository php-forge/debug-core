import assert from "node:assert/strict";
import { test } from "vitest";

var scrolls = [];
var clickHandler = null;

function row(tag) {
  var classes = new Set();

  return {
    classList: {
      contains(name) {
        return classes.has(name);
      },
      toggle(name, force) {
        if (force) {
          classes.add(name);
        } else {
          classes.delete(name);
        }
      },
    },
    getAttribute(name) {
      return name === "data-yii-debug-tag" ? tag : null;
    },
    getBoundingClientRect() {
      return { top: 10, bottom: 20, height: 10 };
    },
  };
}

var rows = [row("tag-1"), row("tag-2")];

var section = {
  getAttribute() {
    return null;
  },
  querySelector() {
    return null;
  },
  querySelectorAll() {
    return [];
  },
  addEventListener(type, listener) {
    if (type === "click") {
      clickHandler = listener;
    }
  },
  contains() {
    return true;
  },
};

globalThis.document = {
  documentElement: { clientHeight: 600 },
  querySelector(selector) {
    return selector === "[data-yii-debug-history-cursor]" ? section : null;
  },
  querySelectorAll(selector) {
    return selector === "tr[data-yii-debug-tag]" ? rows : [];
  },
};
globalThis.window = {
  innerHeight: 800,
  scrollY: 0,
  scrollTo(options) {
    scrolls.push(options);
  },
};

await import("../src/core/history-cursor.js");

test("a missing init attribute leaves the cursor on the newest row without scrolling", () => {
  assert.equal(typeof clickHandler, "function");
  assert.equal(rows[0].classList.contains("is-cursor"), true);
  assert.equal(rows[1].classList.contains("is-cursor"), false);
  assert.equal(scrolls.length, 0);
});
