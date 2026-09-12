import assert from "node:assert/strict";
import { test } from "vitest";

import {
  clearGridFilter,
  GRID_FILTER_INPUT_SELECTOR,
  shouldClearGridFilter,
} from "../src/core/grid-filter.js";

function input(value, selectors = [GRID_FILTER_INPUT_SELECTOR]) {
  return {
    value,
    matches(selector) {
      return selectors.indexOf(selector) !== -1;
    },
  };
}

function keydown(key, target) {
  return { key, target };
}

test("Escape clears a filled input of a standard grid filter row", () => {
  assert.equal(shouldClearGridFilter(keydown("Escape", input("SELECT"))), true);
});

test("Escape leaves an already empty filter input untouched", () => {
  assert.equal(shouldClearGridFilter(keydown("Escape", input(""))), false);
});

test("only Escape clears a filter input", () => {
  assert.equal(shouldClearGridFilter(keydown("Enter", input("SELECT"))), false);
});

test("inputs outside a grid filter row keep their value", () => {
  assert.equal(
    shouldClearGridFilter(keydown("Escape", input("SELECT", []))),
    false,
  );
});

test("targets without selector matching are ignored", () => {
  assert.equal(
    shouldClearGridFilter(keydown("Escape", { value: "SELECT" })),
    false,
  );
  assert.equal(shouldClearGridFilter(keydown("Escape", null)), false);
  assert.equal(shouldClearGridFilter(null), false);
});

test("clearing a filter input empties it and announces the change", () => {
  var dispatched = [];
  var target = {
    value: "SELECT",
    dispatchEvent(event) {
      dispatched.push(event);
    },
  };

  clearGridFilter(target);

  assert.equal(target.value, "");
  assert.equal(dispatched.length, 1);
  assert.equal(dispatched[0].type, "change");
  assert.equal(dispatched[0].bubbles, true);
});
