import assert from "node:assert/strict";
import test from "node:test";

import { dropdownNavigationIndex } from "../src/core/dropdown.js";

test("dropdown trigger follows platform arrow-key expectations", () => {
  assert.equal(dropdownNavigationIndex(4, -1, "ArrowDown", true), 0);
  assert.equal(dropdownNavigationIndex(4, -1, "ArrowUp", true), 3);
  assert.equal(dropdownNavigationIndex(4, -1, "Home", true), 0);
  assert.equal(dropdownNavigationIndex(4, -1, "End", true), 3);
  assert.equal(dropdownNavigationIndex(4, 2, "ArrowDown", true), 0);
  assert.equal(dropdownNavigationIndex(4, 1, "ArrowUp", true), 3);
});

test("dropdown items wrap while Home and End remain absolute", () => {
  assert.equal(dropdownNavigationIndex(4, 0, "ArrowUp", false), 3);
  assert.equal(dropdownNavigationIndex(4, 3, "ArrowDown", false), 0);
  assert.equal(dropdownNavigationIndex(4, 2, "Home", false), 0);
  assert.equal(dropdownNavigationIndex(4, 1, "End", false), 3);
  assert.equal(dropdownNavigationIndex(0, -1, "ArrowDown", true), -1);
  assert.equal(dropdownNavigationIndex(2.5, 0, "ArrowDown", false), -1);
  assert.equal(dropdownNavigationIndex(4, -1, "ArrowUp", false), 3);
  assert.equal(dropdownNavigationIndex(4, -1, "ArrowDown", false), 0);
  assert.equal(dropdownNavigationIndex(4, 2, "ArrowUp", false), 1);
  assert.equal(dropdownNavigationIndex(4, 1, "ArrowDown", false), 2);
  assert.equal(dropdownNavigationIndex(4, -2, "ArrowUp", false), 3);
  assert.equal(dropdownNavigationIndex(4, -2, "ArrowDown", false), 0);
});

test("arrow-up wrap from the first item derives from the current index", () => {
  var calls = 0;
  // A numeric spy proves index `0` takes the modular-arithmetic branch, which
  // reads the current index twice, instead of the negative-index shortcut.
  var zero = {
    valueOf() {
      calls += 1;

      return 0;
    },
  };

  assert.equal(dropdownNavigationIndex(4, zero, "ArrowUp", false), 3);
  assert.equal(calls, 2);
});
