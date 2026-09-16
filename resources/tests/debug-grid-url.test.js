// @vitest-environment jsdom
import assert from "node:assert/strict";
import { test, vi } from "vitest";

import {
  bootDebugPage,
  FILTER_FOCUS_KEY,
  fire,
  press,
} from "./debug-page-harness.js";

const LISTED = "https://debug.test/debug/index?page=3&sort=-time";
const PAGE_SIZE =
  "<select data-yii-debug-pagesize>" +
  '<option value="">Default</option>' +
  '<option value="0">All</option>' +
  '<option value="50">50</option></select>';

function change(element, value) {
  element.value = value;
  fire(element, "change");
}

test("choosing a page size rewrites per-page and returns to page one", async () => {
  const page = await bootDebugPage({ body: PAGE_SIZE, url: LISTED });

  change(page.document.querySelector("select"), "50");

  assert.deepEqual(
    page.navigations,
    ["https://debug.test/debug/index?sort=-time&per-page=50"],
    "Size must replace the page cursor, not the sort.",
  );
});

test("choosing the default page size drops per-page", async () => {
  const page = await bootDebugPage({
    body: PAGE_SIZE,
    url: "https://debug.test/debug/index?per-page=50&page=3",
  });

  change(page.document.querySelector("select"), "");

  assert.deepEqual(
    page.navigations,
    ["https://debug.test/debug/index"],
    "The default size must leave no trace in the URL.",
  );
});

test("choosing the unlimited page size drops per-page", async () => {
  const page = await bootDebugPage({
    body: PAGE_SIZE,
    url: "https://debug.test/debug/index?per-page=50",
  });

  change(page.document.querySelector("select"), "0");

  assert.deepEqual(
    page.navigations,
    ["https://debug.test/debug/index"],
    "Unlimited must be expressed by absence.",
  );
});

test("a change from an unrelated control is ignored", async () => {
  const page = await bootDebugPage({
    body: '<select id="sort"><option value="time">Time</option></select>',
    url: LISTED,
  });

  change(page.document.getElementById("sort"), "time");

  assert.equal(page.navigations.length, 0, "Only marked selects reload.");
});

test("a GridView select filter applies as soon as it changes", async () => {
  const page = await bootDebugPage({
    body:
      '<select name="Debug[method]"><option value="">Any</option>' +
      '<option value="GET">GET</option></select>',
    url: LISTED,
  });

  change(page.document.querySelector("select"), "GET");

  assert.deepEqual(
    page.navigations,
    ["https://debug.test/debug/index?sort=-time&Debug%5Bmethod%5D=GET"],
    "Filter must join the query without the page cursor.",
  );
  assert.equal(
    page.document.documentElement.getAttribute("aria-busy"),
    "true",
    "Page must announce the pending reload.",
  );
});

test("Enter applies a typed GridView filter immediately", async () => {
  const page = await bootDebugPage({
    body: '<input name="Debug[url]" value="/site/index">',
    url: LISTED,
  });
  const event = press(page.document.querySelector("input"), "Enter");

  assert.equal(event.defaultPrevented, true, "Form submission is suppressed.");
  assert.deepEqual(
    page.navigations,
    [
      "https://debug.test/debug/index?sort=-time&Debug%5Burl%5D=%2Fsite%2Findex",
    ],
    "Typed value must reach the query.",
  );
});

test("clearing a GridView filter removes its query parameter", async () => {
  const page = await bootDebugPage({
    body: '<input name="Debug[url]" value="">',
    url: "https://debug.test/debug/index?Debug%5Burl%5D=%2Fsite&page=2",
  });

  press(page.document.querySelector("input"), "Enter");

  assert.deepEqual(
    page.navigations,
    ["https://debug.test/debug/index"],
    "An empty filter must leave no parameter behind.",
  );
});

test("re-applying the value already in the URL does not reload", async () => {
  const page = await bootDebugPage({
    body: '<input name="Debug[method]" value="GET">',
    url: "https://debug.test/debug/index?Debug%5Bmethod%5D=GET",
  });

  press(page.document.querySelector("input"), "Enter");

  assert.equal(
    page.navigations.length,
    0,
    "An unchanged URL is not worth a trip.",
  );
  assert.equal(
    page.document.documentElement.getAttribute("aria-busy"),
    null,
    "No reload means no busy state.",
  );
});

test("typing applies the filter once the field goes idle", async () => {
  const page = await bootDebugPage({
    body: '<input name="Debug[url]" value="/site">',
    url: LISTED,
  });

  vi.useFakeTimers();

  try {
    fire(page.document.querySelector("input"), "input");

    assert.equal(
      page.navigations.length,
      0,
      "Every keystroke must not reload.",
    );

    vi.advanceTimersByTime(650);

    assert.deepEqual(
      page.navigations,
      ["https://debug.test/debug/index?sort=-time&Debug%5Burl%5D=%2Fsite"],
      "The idle field must reach the query.",
    );
  } finally {
    vi.useRealTimers();
  }
});

test("every keystroke restarts the idle wait", async () => {
  const page = await bootDebugPage({
    body: '<input name="Debug[url]" value="/site">',
    url: LISTED,
  });
  const input = page.document.querySelector("input");

  vi.useFakeTimers();

  try {
    fire(input, "input");
    vi.advanceTimersByTime(400);

    input.value = "/site/index";
    fire(input, "input");
    vi.advanceTimersByTime(400);

    assert.equal(page.navigations.length, 0, "The first wait must be dropped.");

    vi.advanceTimersByTime(250);

    assert.deepEqual(
      page.navigations,
      [
        "https://debug.test/debug/index?sort=-time&Debug%5Burl%5D=%2Fsite%2Findex",
      ],
      "Only the last value may reach the query.",
    );
  } finally {
    vi.useRealTimers();
  }
});

test("a filter renamed while the wait runs is dropped", async () => {
  const page = await bootDebugPage({
    body: '<input name="Debug[url]" value="/site">',
    url: LISTED,
  });
  const input = page.document.querySelector("input");

  vi.useFakeTimers();

  try {
    fire(input, "input");
    input.removeAttribute("name");
    vi.advanceTimersByTime(650);

    assert.equal(
      page.navigations.length,
      0,
      "A field that stopped being a filter must not reload.",
    );
  } finally {
    vi.useRealTimers();
  }
});

test("leaving the field flushes what was typed", async () => {
  const page = await bootDebugPage({
    body: '<input name="Debug[url]" value="/site">',
    url: LISTED,
  });
  const input = page.document.querySelector("input");

  vi.useFakeTimers();

  try {
    fire(input, "input");
    fire(input, "focusout");

    assert.equal(page.navigations.length, 1, "Tabbing out must not lose text.");

    vi.advanceTimersByTime(650);

    assert.equal(page.navigations.length, 1, "The flush must cancel the wait.");
  } finally {
    vi.useRealTimers();
  }
});

test("Enter flushes the pending edit instead of applying it twice", async () => {
  const page = await bootDebugPage({
    body: '<input name="Debug[url]" value="/site">',
    url: LISTED,
  });
  const input = page.document.querySelector("input");

  vi.useFakeTimers();

  try {
    fire(input, "input");
    press(input, "Enter");

    vi.advanceTimersByTime(650);

    assert.equal(page.navigations.length, 1, "Only one reload may happen.");
  } finally {
    vi.useRealTimers();
  }
});

test("a change cancels the wait started by the same field", async () => {
  const page = await bootDebugPage({
    body: '<input name="Debug[url]" value="/site">',
    url: LISTED,
  });
  const input = page.document.querySelector("input");

  vi.useFakeTimers();

  try {
    fire(input, "input");
    fire(input, "change");

    assert.equal(page.navigations.length, 1, "The change must apply at once.");

    vi.advanceTimersByTime(650);

    assert.equal(page.navigations.length, 1, "The wait must be cancelled.");
  } finally {
    vi.useRealTimers();
  }
});

test("a change on another field leaves the first one waiting", async () => {
  const page = await bootDebugPage({
    body:
      '<input name="Debug[url]" value="/site">' +
      '<input name="Debug[method]" value="GET">',
    url: LISTED,
  });
  const inputs = page.document.querySelectorAll("input");

  vi.useFakeTimers();

  try {
    fire(inputs[0], "input");
    fire(inputs[1], "change");

    assert.equal(page.navigations.length, 1, "The change must apply at once.");

    vi.advanceTimersByTime(650);

    assert.equal(page.navigations.length, 2, "The waiting field must follow.");
  } finally {
    vi.useRealTimers();
  }
});

test("a field whose name is not a Yii form input is ignored", async () => {
  const page = await bootDebugPage({
    body: '<input name="q" value="debug">',
    url: LISTED,
  });
  const input = page.document.querySelector("input");

  press(input, "Enter");
  fire(input, "input");
  fire(input, "change");
  fire(input, "focusout");

  assert.equal(
    page.navigations.length,
    0,
    "Foreign fields must be left alone.",
  );
});

test("a field without a name is ignored", async () => {
  const page = await bootDebugPage({
    body: '<input value="debug">',
    url: LISTED,
  });

  press(page.document.querySelector("input"), "Enter");

  assert.equal(page.navigations.length, 0, "A nameless field drives nothing.");
});

test("a submit button is never treated as a filter", async () => {
  const page = await bootDebugPage({
    body: '<input type="submit" name="Debug[go]" value="Go">',
    url: LISTED,
  });
  const button = page.document.querySelector("input");

  press(button, "Enter");
  fire(button, "input");
  fire(button, "focusout");

  assert.equal(page.navigations.length, 0, "Submitting is not filtering.");
});

test("a named control that is not an input or a select is ignored", async () => {
  const page = await bootDebugPage({
    body: '<textarea name="Debug[note]">hello</textarea>',
    url: LISTED,
  });
  const field = page.document.querySelector("textarea");

  press(field, "Enter");
  fire(field, "input");
  fire(field, "focusout");
  fire(field, "change");

  assert.equal(page.navigations.length, 0, "Only fields drive the query.");
});

test("a key other than Enter leaves the field alone", async () => {
  const page = await bootDebugPage({
    body: '<input name="Debug[url]" value="/site">',
    url: LISTED,
  });

  press(page.document.querySelector("input"), "a");

  assert.equal(page.navigations.length, 0, "Typing must not reload.");
});

test("leaving an untouched field does not reload", async () => {
  const page = await bootDebugPage({
    body: '<input name="Debug[url]" value="/site">',
    url: LISTED,
  });

  fire(page.document.querySelector("input"), "focusout");

  assert.equal(page.navigations.length, 0, "Nothing was typed, nothing to do.");
});

test("the caret position is recorded before the reload", async () => {
  const page = await bootDebugPage({
    body: '<input name="Debug[url]" value="/site/index">',
    url: LISTED,
  });
  const input = page.document.querySelector("input");

  input.setSelectionRange(2, 5);
  press(input, "Enter");

  assert.deepEqual(
    JSON.parse(page.session.items.get(FILTER_FOCUS_KEY)),
    { end: 5, name: "Debug[url]", start: 2 },
    "Caret must survive the round trip.",
  );
});

test("the recorded caret is restored on the next page", async () => {
  const page = await bootDebugPage({
    body: '<input name="Debug[url]" value="/site/index">',
    sessionData: {
      [FILTER_FOCUS_KEY]: JSON.stringify({
        end: 5,
        name: "Debug[url]",
        start: 2,
      }),
    },
  });
  const input = page.document.querySelector("input");

  assert.equal(page.document.activeElement, input, "Field must regain focus.");
  assert.equal(input.selectionStart, 2, "Caret must start where it was.");
  assert.equal(input.selectionEnd, 5, "Caret must end where it was.");
  assert.equal(
    page.session.items.has(FILTER_FOCUS_KEY),
    false,
    "A restored position must be consumed.",
  );
});

test("a field that cannot place a caret is only refocused", async () => {
  const page = await bootDebugPage({
    body: '<input name="Debug[url]" value="/site/index">',
    prepare(documentValue) {
      /**
       * Older engines expose no selection API on some input types; the source
       * must still hand the field back its focus.
       */
      documentValue.querySelector("input").setSelectionRange = undefined;
    },
    sessionData: {
      [FILTER_FOCUS_KEY]: JSON.stringify({
        end: 5,
        name: "Debug[url]",
        start: 2,
      }),
    },
  });

  assert.equal(
    page.document.activeElement,
    page.document.querySelector("input"),
    "Field must regain focus.",
  );
});

test("a recorded position for a field that is gone is dropped", async () => {
  const page = await bootDebugPage({
    body: '<input name="Debug[method]" value="GET">',
    sessionData: {
      [FILTER_FOCUS_KEY]: JSON.stringify({ end: 5, name: "Db[url]", start: 2 }),
    },
  });

  assert.equal(
    page.document.activeElement,
    page.document.body,
    "Nothing to focus.",
  );
  assert.equal(
    page.session.items.has(FILTER_FOCUS_KEY),
    false,
    "A stale position must be consumed.",
  );
});

test("a recorded position naming a control that is not an input is dropped", async () => {
  const page = await bootDebugPage({
    body: '<button name="Debug[url]">Go</button>',
    sessionData: {
      [FILTER_FOCUS_KEY]: JSON.stringify({
        end: 5,
        name: "Debug[url]",
        start: 2,
      }),
    },
  });

  assert.equal(
    page.document.activeElement,
    page.document.body,
    "Only a field may take the caret back.",
  );
});

test("a recorded position that is not a filter name is dropped", async () => {
  const page = await bootDebugPage({
    body: '<input name="q" value="debug">',
    sessionData: { [FILTER_FOCUS_KEY]: JSON.stringify({ name: "q" }) },
  });

  assert.equal(
    page.document.activeElement,
    page.document.body,
    "Foreign names must be refused.",
  );
});

test("an unreadable recorded position is ignored", async () => {
  const page = await bootDebugPage({
    body: '<input name="Debug[url]" value="/site/index">',
    sessionData: { [FILTER_FOCUS_KEY]: "{not json" },
  });

  assert.equal(
    page.document.activeElement,
    page.document.body,
    "A corrupt entry must not throw.",
  );
});

test("a page without session storage still filters", async () => {
  const page = await bootDebugPage({
    body: '<input name="Debug[url]" value="/site">',
    session: "throws",
    url: LISTED,
  });

  press(page.document.querySelector("input"), "Enter");

  assert.deepEqual(
    page.navigations,
    ["https://debug.test/debug/index?sort=-time&Debug%5Burl%5D=%2Fsite"],
    "Blocked storage must not block the reload.",
  );
});

test("a session storage that refuses writes still filters", async () => {
  const page = await bootDebugPage({
    body: '<input name="Debug[url]" value="/site">',
    session: {
      getItem() {
        return null;
      },
      removeItem() {},
      setItem() {
        throw new Error("Quota exceeded.");
      },
    },
    url: LISTED,
  });

  press(page.document.querySelector("input"), "Enter");

  assert.deepEqual(
    page.navigations,
    ["https://debug.test/debug/index?sort=-time&Debug%5Burl%5D=%2Fsite"],
    "A full quota must not block the reload.",
  );
});
