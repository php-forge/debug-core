import assert from "node:assert/strict";
import test from "node:test";

import { bindCopyControls, copyControlText } from "../src/core/clipboard.js";

function fixture(attributes = {}) {
  var nodesById = new Map();
  var statuses = [];
  var label = { textContent: "Copy link" };
  var listeners = new Map();
  var values = new Map(Object.entries(attributes));
  var documentValue = {
    createElement(tagName) {
      var element = {
        attributes: new Map(),
        className: "",
        id: "",
        tagName,
        textContent: "",
        getAttribute(name) {
          return this.attributes.get(name) ?? null;
        },
        setAttribute(name, value) {
          this.attributes.set(name, value);
        },
      };

      statuses.push(element);

      return element;
    },
    getElementById(id) {
      if (typeof id !== "string") {
        throw new TypeError("getElementById expects a string id.");
      }

      return nodesById.get(id) || null;
    },
  };
  var control = {
    ownerDocument: documentValue,
    addEventListener(name, listener) {
      listeners.set(name, listener);
    },
    after(status) {
      nodesById.set(status.id, status);
    },
    getAttribute(name) {
      return values.get(name) ?? null;
    },
    hasAttribute(name) {
      return values.has(name);
    },
    querySelector(selector) {
      return selector === "[data-yii-debug-copy-label]" ? label : null;
    },
    setAttribute(name, value) {
      values.set(name, value);
    },
  };
  var root = {
    getElementById: documentValue.getElementById,
    querySelectorAll(selector) {
      return selector === "[data-yii-debug-copy], [data-yii-debug-copy-link]"
        ? [control]
        : [];
    },
  };

  return { control, label, listeners, nodesById, root, statuses, values };
}

test("copy controls announce successful permalink copies", async () => {
  var page = fixture({ "data-yii-debug-copy-link": "true" });
  var writes = [];

  assert.equal(
    bindCopyControls(
      page.root,
      {
        writeText(value) {
          writes.push(value);
          return Promise.resolve();
        },
      },
      "https://example.test/debug/request#request-panel-1",
    ),
    1,
  );

  page.listeners.get("click")();
  await Promise.resolve();
  await Promise.resolve();

  assert.deepEqual(writes, [
    "https://example.test/debug/request#request-panel-1",
  ]);
  assert.equal(page.label.textContent, "Copied");
  assert.equal(page.values.get("aria-label"), "Copied");
  assert.equal(page.values.get("title"), "Copied");
  assert.equal(page.statuses[0].tagName, "span");
  assert.equal(page.statuses[0].className, "yii-debug-sr-only");
  assert.match(page.statuses[0].id, /^yii-debug-copy-status-[1-9]\d*$/);
  assert.equal(page.statuses[0].getAttribute("aria-atomic"), "true");
  assert.equal(page.statuses[0].getAttribute("aria-live"), "polite");
  assert.equal(
    page.statuses[0].getAttribute("data-yii-debug-copy-status"),
    "true",
  );
  assert.equal(page.statuses[0].textContent, "Copied to clipboard.");
  assert.equal(page.nodesById.get(page.statuses[0].id), page.statuses[0]);
  assert.equal(page.values.get("aria-describedby"), page.statuses[0].id);
});

test("copy text sources resolve literals, bare targets, and location objects", () => {
  var literalPage = fixture({
    "data-yii-debug-copy": "true",
    "data-yii-debug-copy-value": "SELECT 42",
  });

  assert.equal(
    copyControlText(literalPage.control, literalPage.root, null),
    "SELECT 42",
  );

  var bareTarget = fixture({
    "data-yii-debug-copy": "true",
    "data-yii-debug-copy-target": "sql-9",
  });

  bareTarget.nodesById.set("sql-9", { textContent: "SELECT 9" });
  assert.equal(
    copyControlText(bareTarget.control, bareTarget.root, null),
    "SELECT 9",
  );
  bareTarget.nodesById.delete("sql-9");
  assert.equal(
    copyControlText(bareTarget.control, bareTarget.root, null),
    null,
  );
  assert.equal(copyControlText(bareTarget.control, {}, null), null);

  var linkPage = fixture({ "data-yii-debug-copy-link": "true" });

  assert.equal(
    copyControlText(linkPage.control, linkPage.root, "https://s.test/x"),
    "https://s.test/x",
  );
  assert.equal(
    copyControlText(linkPage.control, linkPage.root, {
      href: "https://o.test/y",
    }),
    "https://o.test/y",
  );
  assert.equal(copyControlText(linkPage.control, linkPage.root, {}), null);

  var plain = fixture({ "data-yii-debug-copy": "true" });

  assert.equal(copyControlText(plain.control, plain.root, null), null);
  assert.equal(
    copyControlText(plain.control, plain.root, "https://loc.test/z"),
    null,
  );
});

test("copy controls reuse existing status nodes and bind only once", () => {
  var reused = fixture({
    "aria-describedby": "missing plain-node existing-status",
    "data-yii-debug-copy-link": "true",
  });
  var existingStatus = {
    getAttribute(name) {
      return name === "data-yii-debug-copy-status" ? "true" : null;
    },
    textContent: "",
  };

  reused.nodesById.set("plain-node", {
    getAttribute() {
      return null;
    },
  });
  reused.nodesById.set("existing-status", existingStatus);

  assert.equal(bindCopyControls(reused.root, null, null), 1);
  assert.equal(bindCopyControls(reused.root, null, null), 0);

  reused.listeners.get("click")();

  assert.equal(reused.statuses.length, 0);
  assert.equal(existingStatus.textContent, "Clipboard access is unavailable.");
});

test("copy failures surface retry feedback for rejections and sync throws", async () => {
  var rejecting = fixture({ "data-yii-debug-copy-link": "true" });

  bindCopyControls(
    rejecting.root,
    {
      writeText() {
        return Promise.reject(new Error("denied"));
      },
    },
    "https://example.test/a",
  );
  rejecting.listeners.get("click")();
  await Promise.resolve();
  await Promise.resolve();

  assert.equal(rejecting.label.textContent, "Retry copy");
  assert.equal(rejecting.values.get("aria-label"), "Copy failed");
  assert.equal(rejecting.values.get("title"), "Copy failed");
  assert.equal(rejecting.statuses[0].textContent, "Copy failed. Try again.");

  var throwing = fixture({ "data-yii-debug-copy-link": "true" });

  throwing.control.querySelector = () => null;
  bindCopyControls(
    throwing.root,
    {
      writeText() {
        throw new Error("denied");
      },
    },
    "https://example.test/b",
  );
  throwing.listeners.get("click")();

  assert.equal(throwing.values.get("aria-label"), "Copy failed");
  assert.equal(throwing.label.textContent, "Copy link");

  var noWrite = fixture({ "data-yii-debug-copy-link": "true" });

  bindCopyControls(noWrite.root, {}, "https://example.test/c");
  noWrite.listeners.get("click")();

  assert.equal(noWrite.values.get("aria-label"), "Copy unavailable");
  assert.equal(noWrite.values.get("title"), "Copy unavailable");
});

test("copy descriptions extend existing tokens without duplicates", () => {
  var extended = fixture({
    "aria-describedby": "missing plain-node",
    "data-yii-debug-copy-link": "true",
  });

  assert.equal(bindCopyControls(extended.root, null, null), 1);
  assert.equal(extended.statuses.length, 1);
  assert.equal(
    extended.values.get("aria-describedby"),
    "missing plain-node " + extended.statuses[0].id,
  );

  var prefix = "yii-debug-copy-status-";
  var nextId =
    prefix + (Number(extended.statuses[0].id.slice(prefix.length)) + 1);
  var preseeded = fixture({
    "aria-describedby": nextId,
    "data-yii-debug-copy-link": "true",
  });

  assert.equal(bindCopyControls(preseeded.root, null, null), 1);
  assert.equal(preseeded.statuses[0].id, nextId);
  assert.equal(preseeded.values.get("aria-describedby"), nextId);
});

test("copy descriptions split on whitespace runs", () => {
  var page = fixture({ "data-yii-debug-copy-link": "true" });
  var separators = [];
  var plainGetAttribute = page.control.getAttribute.bind(page.control);

  // A string-like described-by value records the exact separator pattern used
  // to tokenize it while delegating to the real `String#split` behavior.
  page.control.getAttribute = function (name) {
    var value = plainGetAttribute(name);

    if (name !== "aria-describedby") {
      return value;
    }

    return {
      split(separator) {
        separators.push(separator.source + "/" + separator.flags);

        return String(value ?? "").split(separator);
      },
    };
  };

  assert.equal(bindCopyControls(page.root, null, null), 1);
  assert.deepEqual(separators, ["\\s+/", "\\s+/"]);
});

test("unresolved copy targets keep a working clipboard untouched", async () => {
  var page = fixture({
    "data-yii-debug-copy": "true",
    "data-yii-debug-copy-target": "#gone",
  });
  var writes = [];

  bindCopyControls(page.root, {
    writeText(value) {
      writes.push(value);
      return Promise.resolve();
    },
  });
  page.listeners.get("click")();
  await Promise.resolve();
  await Promise.resolve();

  assert.deepEqual(writes, []);
  assert.equal(page.values.get("aria-label"), "Copy unavailable");
});

test("generic copy controls resolve target ids and report unavailable APIs", () => {
  var page = fixture({
    "data-yii-debug-copy": "true",
    "data-yii-debug-copy-target": "#sql-7",
  });

  page.nodesById.set("sql-7", { textContent: "SELECT 1" });

  assert.equal(copyControlText(page.control, page.root, null), "SELECT 1");
  bindCopyControls(page.root, null, null);
  page.listeners.get("click")();

  assert.equal(page.label.textContent, "Unavailable");
  assert.equal(page.values.get("aria-label"), "Copy unavailable");
  assert.equal(
    page.statuses[0].textContent,
    "Clipboard access is unavailable.",
  );
});
