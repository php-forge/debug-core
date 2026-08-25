import assert from "node:assert/strict";
import test from "node:test";

function createStorage() {
  var items = new Map();

  return {
    getItem(key) {
      return items.has(key) ? items.get(key) : null;
    },
    setItem(key, value) {
      items.set(key, String(value));
    },
  };
}

function elementNode(selector, parentElement) {
  return {
    matches(candidate) {
      return candidate === selector;
    },
    nodeType: 1,
    parentElement: parentElement || null,
  };
}

var storageStub = createStorage();
var querySelectorCalls = [];
var fallbackToolbar = { id: "fallback-toolbar" };
var fetchStub = function fetch() {};
var xhrOpenStub = function open() {};

globalThis.window = {
  fetch: fetchStub,
  localStorage: storageStub,
  location: new URL("https://example.test/base/page"),
};
globalThis.document = {
  querySelector(selector) {
    querySelectorCalls.push(selector);

    return fallbackToolbar;
  },
};
globalThis.XMLHttpRequest = function XMLHttpRequest() {};
globalThis.XMLHttpRequest.prototype.open = xhrOpenStub;

const {
  absoluteUrl,
  closest,
  escapeHtml,
  getPrimaryToolbar,
  originalFetch,
  originalXhrOpen,
  parseJsonAttribute,
  readStorageItem,
  requestStack,
  requestStackLimit,
  sameUrl,
  storageKey,
  tagName,
  themeAttributeFilter,
  themeParam,
  themeStorageKey,
  toolbars,
  writeStorageItem,
} = await import("../src/toolbar/state.js");

test("module constants expose the shared toolbar configuration", () => {
  assert.equal(tagName, "yii-debug-toolbar");
  assert.equal(storageKey, "yii-debug-toolbar-expanded");
  assert.equal(themeParam, "yii_debug_theme");
  assert.equal(themeStorageKey, "yii-debug-toolbar-theme");
  assert.equal(requestStackLimit, 100);
  assert.deepEqual(requestStack, []);
  assert.deepEqual(toolbars, []);
  assert.deepEqual(themeAttributeFilter, [
    "class",
    "data-theme",
    "data-bs-theme",
    "data-yii-debug-theme",
    "data-color-mode",
    "data-mode",
    "data-theme-mode",
  ]);
});

test("original transport primitives are captured at module load time", () => {
  assert.equal(originalXhrOpen, xhrOpenStub);
  assert.equal(originalFetch, fetchStub);
});

test("storage helpers round-trip values through localStorage", () => {
  assert.equal(writeStorageItem("yii-debug-toolbar-expanded", "1"), true);
  assert.equal(readStorageItem("yii-debug-toolbar-expanded"), "1");
  assert.equal(readStorageItem("yii-debug-toolbar-missing"), null);
});

test("storage helpers degrade gracefully without localStorage", () => {
  try {
    window.localStorage = null;

    assert.equal(readStorageItem("yii-debug-toolbar-expanded"), null);
    assert.equal(writeStorageItem("yii-debug-toolbar-expanded", "1"), false);
  } finally {
    window.localStorage = storageStub;
  }
});

test("storage helpers tolerate denied localStorage access", () => {
  try {
    Object.defineProperty(window, "localStorage", {
      configurable: true,
      get() {
        throw new Error("Storage access denied.");
      },
    });

    assert.equal(readStorageItem("yii-debug-toolbar-expanded"), null);
    assert.equal(writeStorageItem("yii-debug-toolbar-expanded", "1"), false);
  } finally {
    Object.defineProperty(window, "localStorage", {
      configurable: true,
      value: storageStub,
      writable: true,
    });
  }
});

test("writeStorageItem reports failure when the storage write throws", () => {
  try {
    window.localStorage = {
      setItem() {
        throw new Error("Quota exceeded.");
      },
    };

    assert.equal(writeStorageItem("yii-debug-toolbar-expanded", "1"), false);
  } finally {
    window.localStorage = storageStub;
  }
});

test("escapeHtml encodes markup-significant characters", () => {
  assert.equal(escapeHtml("&<>\"'"), "&amp;&lt;&gt;&quot;&#039;");
  assert.equal(
    escapeHtml('<a href="x">&\'</a>'),
    "&lt;a href=&quot;x&quot;&gt;&amp;&#039;&lt;/a&gt;",
  );
  assert.equal(escapeHtml(200), "200");
});

test("escapeHtml renders null and undefined as empty strings", () => {
  assert.equal(escapeHtml(null), "");
  assert.equal(escapeHtml(undefined), "");
  assert.equal(escapeHtml(), "");
});

test("parseJsonAttribute parses valid JSON attribute payloads", () => {
  var element = {
    getAttribute(name) {
      return name === "data-panels" ? '{"db":{"count":3}}' : null;
    },
  };

  assert.deepEqual(parseJsonAttribute(element, "data-panels", {}), {
    db: { count: 3 },
  });
});

test("parseJsonAttribute falls back for missing or malformed payloads", () => {
  var attributes = { "data-empty": "", "data-invalid": "{invalid" };
  var element = {
    getAttribute(name) {
      return name in attributes ? attributes[name] : null;
    },
  };
  var fallback = { fallback: true };

  assert.equal(parseJsonAttribute(element, "data-missing", fallback), fallback);
  assert.equal(parseJsonAttribute(element, "data-empty", fallback), fallback);
  assert.equal(parseJsonAttribute(element, "data-invalid", fallback), fallback);
});

test("absoluteUrl resolves input against the window location", () => {
  assert.equal(absoluteUrl("status").href, "https://example.test/base/status");
  assert.equal(
    absoluteUrl("/debug/view").href,
    "https://example.test/debug/view",
  );
  assert.equal(
    absoluteUrl("https://other.test/debug").href,
    "https://other.test/debug",
  );
});

test("absoluteUrl returns null for unparsable input", () => {
  assert.equal(absoluteUrl("https://[invalid"), null);
});

test("sameUrl compares fully resolved URLs", () => {
  assert.equal(
    sameUrl("/debug/view?tag=1", "https://example.test/debug/view?tag=1"),
    true,
  );
  assert.equal(sameUrl("/debug/view?tag=1", "/debug/view?tag=2"), false);
});

test("sameUrl rejects comparisons with unparsable URLs", () => {
  assert.equal(sameUrl("https://[invalid", "/debug/view"), false);
  assert.equal(sameUrl("/debug/view", "https://[invalid"), false);
});

test("getPrimaryToolbar falls back to a document query when unregistered", () => {
  assert.equal(getPrimaryToolbar(), fallbackToolbar);
  assert.deepEqual(querySelectorCalls, ["yii-debug-toolbar"]);
});

test("getPrimaryToolbar prefers the first registered toolbar", () => {
  var registered = { id: "registered-toolbar" };

  try {
    toolbars.push(registered, { id: "secondary-toolbar" });
    querySelectorCalls.length = 0;

    assert.equal(getPrimaryToolbar(), registered);
    assert.deepEqual(querySelectorCalls, []);
  } finally {
    toolbars.splice(0, toolbars.length);
  }
});

test("closest matches the element itself and walks up to ancestors", () => {
  var grandparent = elementNode(".panel");
  var parent = elementNode(".item", grandparent);
  var child = elementNode(".label", parent);

  assert.equal(closest(child, ".label"), child);
  assert.equal(closest(child, ".panel"), grandparent);
  assert.equal(closest(child, ".missing"), null);
});

test("closest starts from the parent element for non-element nodes", () => {
  var parent = elementNode(".item");
  var textNode = { nodeType: 3, parentElement: parent };
  var detachedTextNode = { nodeType: 3, parentElement: null };

  assert.equal(closest(textNode, ".item"), parent);
  assert.equal(closest(detachedTextNode, ".item"), null);
});

test("closest returns null for missing nodes and non-element ancestors", () => {
  var documentNode = { nodeType: 9, parentElement: null };
  var rootElement = elementNode(".root", documentNode);

  assert.equal(closest(null, ".item"), null);
  assert.equal(closest(rootElement, ".missing"), null);
});
