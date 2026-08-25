import assert from "node:assert/strict";
import test from "node:test";

var prevented = 0;
var scrolls = [];
var clickHandler = null;

function classList() {
  var classes = new Set();

  return {
    add(name) {
      classes.add(name);
    },
    contains(name) {
      return classes.has(name);
    },
    remove(name) {
      classes.delete(name);
    },
    toggle(name, force) {
      if (force) {
        classes.add(name);
      } else {
        classes.delete(name);
      }
    },
  };
}

function row(attributes) {
  return {
    attributes,
    classList: classList(),
    rect: { top: 10, bottom: 20, height: 10 },
    getAttribute(name) {
      return this.attributes[name] ?? null;
    },
    getBoundingClientRect() {
      return this.rect;
    },
  };
}

var rows = [
  row({
    "data-yii-debug-tag": "tag-1",
    "data-yii-debug-method": "GET",
    "data-yii-debug-url": "https://example.test/site/index?q=1#frag",
    "data-yii-debug-status": "200",
    "data-yii-debug-time": "12:00:01",
    "data-yii-debug-ajax": "0",
  }),
  row({
    "data-yii-debug-tag": "tag-2",
    "data-yii-debug-method": "HEAD",
    "data-yii-debug-url": "php yii migrate/up",
    "data-yii-debug-status": "301",
    "data-yii-debug-time": "12:00:02",
    "data-yii-debug-ajax": "0",
  }),
  row({
    "data-yii-debug-tag": "tag-3",
    "data-yii-debug-method": "PATCH",
    "data-yii-debug-url": "::not-a-url::",
    "data-yii-debug-status": "404",
    "data-yii-debug-time": "",
    "data-yii-debug-ajax": "0",
  }),
  row({
    "data-yii-debug-tag": "tag-4",
    "data-yii-debug-method": "DELETE",
    "data-yii-debug-url": "wtf:",
    "data-yii-debug-status": "500",
    "data-yii-debug-time": "12:00:04",
    "data-yii-debug-ajax": "0",
  }),
  row({}),
  row({
    "data-yii-debug-tag": "tag-6",
    "data-yii-debug-method": "POST",
    "data-yii-debug-url": "https://example.test/post",
    "data-yii-debug-status": "201",
    "data-yii-debug-time": "3 ms",
    "data-yii-debug-ajax": "0",
  }),
  row({
    "data-yii-debug-tag": "tag-7",
    "data-yii-debug-method": "PUT",
    "data-yii-debug-url": "https://example.test/put",
    "data-yii-debug-status": "204",
    "data-yii-debug-time": "4 ms",
    "data-yii-debug-ajax": "1",
  }),
];

/* The init-tagged row starts above the viewport so the import-time scroll runs. */
rows[2].rect = { top: -50, bottom: 0, height: 50 };

function fieldElement(field) {
  return {
    attributes: { "data-snapshot-field": field },
    classList: classList(),
    hidden: false,
    textContent: "",
    getAttribute(name) {
      return this.attributes[name] ?? null;
    },
    setAttribute(name, value) {
      this.attributes[name] = value;
    },
  };
}

var methodField = fieldElement("method");
var urlField = fieldElement("url");
var statusField = fieldElement("status");
var timeField = fieldElement("time");
var ajaxField = fieldElement("ajax");
var extraField = fieldElement("unknown");

function button(direction) {
  return {
    attributes: { "data-yii-debug-cursor": direction },
    disabled: false,
    getAttribute(name) {
      return this.attributes[name] ?? null;
    },
  };
}

var newestButton = button("newest");
var newerButton = button("newer");
var olderButton = button("older");
var oldestButton = button("oldest");

function phantomButton(direction) {
  return {
    disabled: false,
    getAttribute(name) {
      return name === "data-yii-debug-cursor" ? direction : null;
    },
  };
}

var card = {
  attributes: {},
  setAttribute(name, value) {
    this.attributes[name] = value;
  },
};

var section = {
  attributes: { "data-yii-debug-cursor-init": "tag-3" },
  getAttribute(name) {
    return this.attributes[name] ?? null;
  },
  querySelector(selector) {
    if (selector === ".yii-debug-history-card") {
      return card;
    }
    if (selector === '[data-yii-debug-cursor="newest"]') {
      return newestButton;
    }
    if (selector === '[data-yii-debug-cursor="newer"]') {
      return newerButton;
    }
    if (selector === '[data-yii-debug-cursor="older"]') {
      return olderButton;
    }
    if (selector === '[data-yii-debug-cursor="oldest"]') {
      return oldestButton;
    }

    return null;
  },
  querySelectorAll(selector) {
    if (selector === "[data-snapshot-field]") {
      return [
        methodField,
        urlField,
        statusField,
        timeField,
        ajaxField,
        extraField,
      ];
    }

    return [];
  },
  addEventListener(type, listener) {
    if (type === "click") {
      clickHandler = listener;
    }
  },
  contains(node) {
    return node.outside !== true;
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
  scrollY: 100,
  scrollTo(options) {
    scrolls.push(options);
  },
};

await import("../src/core/history-cursor.js");

function click(button) {
  clickHandler({
    preventDefault() {
      prevented += 1;
    },
    target: {
      closest(selector) {
        return selector === "[data-yii-debug-cursor]" ? button : null;
      },
    },
  });
}

function assertCursorAt(index) {
  rows.forEach(function (r, i) {
    assert.equal(r.classList.contains("is-cursor"), i === index);
  });
}

test("importing the module lands the cursor on the init-tagged row and clamps the initial scroll", () => {
  assert.equal(typeof clickHandler, "function");
  assertCursorAt(2);

  assert.equal(methodField.textContent, "PATCH");
  assert.equal(methodField.classList.contains("yii-debug-verb-put"), true);
  assert.equal(urlField.textContent, "::not-a-url::");
  assert.equal(urlField.attributes.title, "::not-a-url::");
  assert.equal(statusField.textContent, "404");
  assert.equal(statusField.classList.contains("yii-debug-status-4xx"), true);
  assert.equal(timeField.textContent, "");
  assert.equal(timeField.hidden, true);
  assert.equal(ajaxField.hidden, true);
  assert.equal(extraField.textContent, "");
  assert.equal(card.attributes.title, "PATCH ::not-a-url::");

  assert.equal(newestButton.disabled, false);
  assert.equal(newerButton.disabled, false);
  assert.equal(olderButton.disabled, false);
  assert.equal(oldestButton.disabled, false);

  assert.deepEqual(scrolls, [{ top: 0, behavior: "smooth" }]);
});

test("clicks without a cursor control or from outside the section are ignored", () => {
  clickHandler({
    preventDefault() {
      prevented += 1;
    },
    target: {
      closest() {
        return null;
      },
    },
  });

  var outsideButton = phantomButton("older");
  outsideButton.outside = true;
  click(outsideButton);

  assertCursorAt(2);
  assert.equal(prevented, 0);
  assert.equal(scrolls.length, 1);
});

test("older clicks walk toward the oldest row and refresh every snapshot facet", () => {
  click(olderButton);

  assertCursorAt(3);
  assert.equal(methodField.textContent, "DELETE");
  assert.equal(methodField.classList.contains("yii-debug-verb-delete"), true);
  assert.equal(methodField.classList.contains("yii-debug-verb-put"), false);
  assert.equal(urlField.textContent, "/");
  assert.equal(urlField.attributes.title, "wtf:");
  assert.equal(statusField.textContent, "500");
  assert.equal(statusField.classList.contains("yii-debug-status-5xx"), true);
  assert.equal(statusField.classList.contains("yii-debug-status-4xx"), false);
  assert.equal(timeField.textContent, "12:00:04");
  assert.equal(timeField.hidden, false);
  assert.equal(card.attributes.title, "DELETE wtf:");
  assert.equal(scrolls.length, 1);

  globalThis.window.innerHeight = 0;
  globalThis.window.scrollY = 1000;
  rows[4].rect = { top: 50, bottom: 650, height: 600 };
  click(olderButton);

  assertCursorAt(4);
  assert.equal(methodField.textContent, "");
  assert.equal(methodField.classList.contains("yii-debug-verb-other"), true);
  assert.equal(urlField.textContent, "");
  assert.equal(urlField.attributes.title, "wtf:");
  assert.equal(statusField.textContent, "–");
  assert.equal(statusField.classList.contains("yii-debug-status-none"), true);
  assert.equal(timeField.hidden, true);
  assert.equal(ajaxField.hidden, true);
  assert.equal(card.attributes.title, "");
  assert.deepEqual(scrolls[1], { top: 1050, behavior: "smooth" });

  globalThis.window.innerHeight = 800;
  click(olderButton);

  assertCursorAt(5);
  assert.equal(methodField.textContent, "POST");
  assert.equal(methodField.classList.contains("yii-debug-verb-post"), true);
  assert.equal(urlField.textContent, "/post");
  assert.equal(urlField.attributes.title, "https://example.test/post");
  assert.equal(statusField.textContent, "201");
  assert.equal(statusField.classList.contains("yii-debug-status-2xx"), true);
  assert.equal(timeField.textContent, "3 ms");
  assert.equal(timeField.hidden, false);

  click(olderButton);

  assertCursorAt(6);
  assert.equal(methodField.textContent, "PUT");
  assert.equal(methodField.classList.contains("yii-debug-verb-put"), true);
  assert.equal(statusField.textContent, "204");
  assert.equal(ajaxField.hidden, false);
  assert.equal(newestButton.disabled, false);
  assert.equal(newerButton.disabled, false);
  assert.equal(olderButton.disabled, true);
  assert.equal(oldestButton.disabled, true);
  assert.equal(scrolls.length, 2);
});

test("the oldest row rejects both disabled and out-of-range older movement", () => {
  var preventedBefore = prevented;
  click(olderButton);

  assertCursorAt(6);
  assert.equal(prevented, preventedBefore + 1);

  click(phantomButton("older"));

  assertCursorAt(6);
  assert.equal(scrolls.length, 2);
});

test("newest jumps to the top row and guards newer movement at the boundary", () => {
  click(newestButton);

  assertCursorAt(0);
  assert.equal(methodField.textContent, "GET");
  assert.equal(methodField.classList.contains("yii-debug-verb-get"), true);
  assert.equal(urlField.textContent, "/site/index?q=1#frag");
  assert.equal(
    urlField.attributes.title,
    "https://example.test/site/index?q=1#frag",
  );
  assert.equal(statusField.textContent, "200");
  assert.equal(statusField.classList.contains("yii-debug-status-2xx"), true);
  assert.equal(timeField.textContent, "12:00:01");
  assert.equal(timeField.hidden, false);
  assert.equal(ajaxField.hidden, true);
  assert.equal(
    card.attributes.title,
    "GET https://example.test/site/index?q=1#frag",
  );
  assert.equal(newestButton.disabled, true);
  assert.equal(newerButton.disabled, true);
  assert.equal(olderButton.disabled, false);
  assert.equal(oldestButton.disabled, false);

  click(phantomButton("newer"));
  assertCursorAt(0);

  click(phantomButton("newest"));
  assertCursorAt(0);
});

test("oldest jumps to the bottom row and newer steps back toward the top", () => {
  rows[2].rect = { top: 10, bottom: 20, height: 10 };
  click(oldestButton);

  assertCursorAt(6);

  click(newerButton);

  assertCursorAt(5);
  assert.equal(newestButton.disabled, false);
  assert.equal(newerButton.disabled, false);
  assert.equal(olderButton.disabled, false);
  assert.equal(oldestButton.disabled, false);

  click(newerButton);
  click(newerButton);
  click(newerButton);
  click(newerButton);

  assertCursorAt(1);
  assert.equal(methodField.textContent, "HEAD");
  assert.equal(methodField.classList.contains("yii-debug-verb-get"), true);
  assert.equal(urlField.textContent, "php yii migrate/up");
  assert.equal(urlField.attributes.title, "php yii migrate/up");
  assert.equal(statusField.textContent, "301");
  assert.equal(statusField.classList.contains("yii-debug-status-3xx"), true);
  assert.equal(card.attributes.title, "HEAD php yii migrate/up");
});

test("unknown cursor directions consume the click without moving the cursor", () => {
  var preventedBefore = prevented;
  var scrollsBefore = scrolls.length;
  click(phantomButton("sideways"));

  assertCursorAt(1);
  assert.equal(prevented, preventedBefore + 1);
  assert.equal(scrolls.length, scrollsBefore);
});
