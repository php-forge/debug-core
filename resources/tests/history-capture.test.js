import assert from "node:assert/strict";
import { test } from "vitest";
import { updateHistoryCapture } from "../src/core/history-capture.js";

function link(href) {
  return {
    href,
    getAttribute() {
      return this.href;
    },
    setAttribute(name, value) {
      assert.equal(name, "href");
      this.href = value;
    },
  };
}

function shell() {
  var header = {
    chip: null,
    querySelector(selector) {
      return selector === ".yii-debug-brand-chip-mem" ? this.chip : null;
    },
    insertBefore(chip) {
      this.chip = chip;
    },
  };
  return {
    header,
    links: [
      link("/debug/view?tag=latest&panel=request"),
      link("/debug/view?tag=latest&panel=event&yii_debug_theme=dark"),
      link("/debug/view?tag=latest&panel=log"),
      link("/debug/view?tag=latest&panel=config"),
      link("/debug"),
      link("https://external.test/?tag=latest"),
    ],
    querySelector: () => header,
    querySelectorAll() {
      return this.links;
    },
    createElement() {
      return {
        children: [],
        append(...nodes) {
          this.children.push(...nodes);
        },
        querySelector() {
          return this.children[1];
        },
        remove() {
          header.chip = null;
        },
      };
    },
  };
}

test("cursor updates every capture link and memory without changing History or external links", () => {
  var root = shell();
  updateHistoryCapture(
    root,
    { tag: "older", memory: "6.00 MB" },
    "https://example.test/debug",
  );

  for (var item of root.links.slice(0, 4)) {
    assert.equal(
      new URL(item.href, "https://example.test").searchParams.get("tag"),
      "older",
    );
  }
  assert.ok(root.links[1].href.includes("panel=event&yii_debug_theme=dark"));
  assert.equal(root.links[4].href, "/debug");
  assert.equal(root.links[5].href, "https://external.test/?tag=latest");
  assert.equal(root.header.chip.children[1].textContent, "6.00 MB");
  assert.equal(root.header.chip.children[0].textContent, "Memory");

  var existingChip = root.header.chip;
  updateHistoryCapture(
    root,
    { tag: "other", memory: "2.00 MB" },
    "https://example.test/debug",
  );
  assert.equal(root.header.chip, existingChip);
  assert.equal(root.header.chip.children[1].textContent, "2.00 MB");
});

test("missing memory removes stale metrics and a later captured value recreates the chip", () => {
  var root = shell();
  var base = "https://example.test/debug";
  updateHistoryCapture(root, { tag: "old", memory: "" }, base);
  assert.equal(root.header.chip, null);
  updateHistoryCapture(root, { tag: "old", memory: "7.00 MB" }, base);
  updateHistoryCapture(root, { tag: "missing", memory: "" }, base);
  assert.equal(root.header.chip, null);
  updateHistoryCapture(root, { tag: "later", memory: "3.00 MB" }, base);
  assert.equal(root.header.chip.children[1].textContent, "3.00 MB");
  updateHistoryCapture(root, { tag: "", memory: "" }, base);
  assert.equal(root.header.chip.children[1].textContent, "3.00 MB");
});
