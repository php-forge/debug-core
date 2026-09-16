// @vitest-environment jsdom
import assert from "node:assert/strict";
import { afterEach, test, vi } from "vitest";

import {
  connectToolbar,
  createToolbar,
  installLocalStorage,
  installMatchMedia,
  installXmlHttpRequest,
  teardownToolbars,
} from "./toolbar-element-harness.js";

installLocalStorage({ "yii-debug-toolbar-expanded": "1" });
installMatchMedia();

var transport = installXmlHttpRequest();

/** Detaches the fixtures and drops a fake clock a thrown assertion left set. */
afterEach(() => {
  teardownToolbars();
  vi.useRealTimers();
});

var snapshot = JSON.stringify({
  items: [{ id: "db", title: "Database", url: "/debug/db" }],
  tag: "tag-1",
  title: "Yii Debugger",
});

function mount(attributes) {
  return connectToolbar(attributes);
}

function errorMessage(element) {
  return element.shadowRoot.querySelector(".error-message").textContent;
}

test("a missing data URL is reported instead of fetched", () => {
  var before = transport.requests.length;
  var element = mount();

  assert.equal(
    errorMessage(element),
    "Debug toolbar data URL is missing or unsafe.",
    "Missing URL must be reported on the bar.",
  );
  assert.equal(transport.requests.length, before, "No request may be issued.");

  element.remove();
});

test("an unsafe data URL is reported instead of fetched", () => {
  var element = mount({ "data-url": "//evil.test/debug/toolbar" });

  assert.equal(
    errorMessage(element),
    "Debug toolbar data URL is missing or unsafe.",
    "Unsafe URL must be reported on the bar.",
  );

  element.remove();
});

test("a loaded snapshot is rendered and announced", () => {
  var element = createToolbar({ "data-url": "/debug/toolbar" });
  var attached = 0;

  element.addEventListener("yii.debug.toolbar_attached", function () {
    attached += 1;
  });
  document.body.appendChild(element);

  var request = transport.last();

  assert.equal(request.method, "GET", "Snapshot must be fetched with GET.");
  assert.equal(
    request.headers["X-Requested-With"],
    "XMLHttpRequest",
    "Request must be marked as an AJAX call.",
  );
  assert.equal(
    request.headers.Accept,
    "application/json",
    "Request must ask for JSON.",
  );

  request.progress(3);

  assert.equal(
    element.data,
    null,
    "An intermediate ready state must be ignored.",
  );

  request.respond(200, snapshot);

  assert.equal(element.currentTag, "tag-1", "Snapshot tag must be recorded.");
  assert.equal(
    element.lastLoadedTag,
    "tag-1",
    "Last good tag must be recorded.",
  );
  assert.equal(
    element.lastLoadedUrl,
    "/debug/toolbar",
    "Last good URL must be recorded.",
  );
  assert.equal(attached, 1, "Attachment must be announced exactly once.");
  assert.ok(
    element.shadowRoot.querySelector('[title="Database"]'),
    "Snapshot must be rendered.",
  );

  element.remove();
});

test("a snapshot without a tag clears the tracked tag", () => {
  var element = mount({ "data-url": "/debug/toolbar" });

  transport.last().respond(200, JSON.stringify({ items: [] }));

  assert.equal(element.currentTag, null, "Tag must be cleared.");
  assert.equal(element.lastLoadedTag, null, "Last good tag must be cleared.");

  element.remove();
});

test("the attachment event falls back to the legacy constructor", () => {
  var constructor = globalThis.Event;
  var element = createToolbar({ "data-url": "/debug/toolbar" });
  var attached = 0;

  element.addEventListener("yii.debug.toolbar_attached", function () {
    attached += 1;
  });
  document.body.appendChild(element);

  try {
    globalThis.Event = undefined;
    transport.last().respond(200, snapshot);
  } finally {
    globalThis.Event = constructor;
  }

  assert.equal(attached, 1, "Legacy path must still announce the attachment.");

  element.remove();
});

test("an unparsable snapshot is reported", () => {
  var element = mount({ "data-url": "/debug/toolbar" });

  transport.last().respond(200, "<html>not json</html>");

  assert.equal(
    errorMessage(element),
    "Invalid debug toolbar data response.",
    "A malformed body must be reported.",
  );
  assert.equal(element.data, null, "No payload may be kept.");

  element.remove();
});

test("a structured server error surfaces its own message", () => {
  var element = mount({ "data-url": "/debug/toolbar" });

  transport
    .last()
    .respond(500, JSON.stringify({ error: "Storage is unreachable." }));

  assert.equal(
    errorMessage(element),
    "Storage is unreachable.",
    "Structured message must be shown.",
  );

  element.remove();
});

test("an opaque server error falls back to a generic message", () => {
  var element = mount({ "data-url": "/debug/toolbar" });

  transport.last().respond(503, "<html>Service unavailable</html>");

  assert.equal(
    errorMessage(element),
    "Unable to load debug toolbar data.",
    "An unparsable body must not be leaked.",
  );

  element.remove();

  var typed = mount({ "data-url": "/debug/toolbar" });

  transport.last().respond(500, JSON.stringify({ status: 500 }));

  assert.equal(
    errorMessage(typed),
    "Unable to load debug toolbar data.",
    "A payload without a message must not be leaked.",
  );

  typed.remove();
});

test("a snapshot that is not persisted yet is retried before it is reported", () => {
  vi.useFakeTimers();

  var issued = transport.requests.length;
  var element = mount({ "data-url": "/debug/toolbar" });
  var delays = [75, 150, 300, 600, 900];

  try {
    assert.equal(
      transport.requests.length,
      issued + 1,
      "The first fetch must be issued on connect.",
    );

    for (var i = 0; i < delays.length; i++) {
      transport.last().respond(404, "");

      assert.equal(
        transport.requests.length,
        issued + 1 + i,
        "A retry must wait for its backoff.",
      );

      vi.advanceTimersByTime(delays[i]);

      assert.equal(
        transport.requests.length,
        issued + 2 + i,
        "The elapsed backoff must issue a retry.",
      );
    }

    transport.last().respond(404, "");

    assert.equal(
      errorMessage(element),
      "Debug data is no longer available for this request.",
      "An exhausted retry budget must be reported plainly.",
    );

    vi.advanceTimersByTime(5000);

    assert.equal(
      transport.requests.length,
      issued + 6,
      "No further retry may be issued.",
    );
  } finally {
    vi.useRealTimers();
    element.remove();
  }
});

test("a retry belonging to a superseded load is dropped", () => {
  vi.useFakeTimers();

  var element = mount({ "data-url": "/debug/toolbar" });

  try {
    transport.last().respond(404, "");
    element.load();

    var current = transport.requests.length;

    vi.advanceTimersByTime(75);

    assert.equal(
      transport.requests.length,
      current,
      "A superseded retry must not reach the network.",
    );

    transport.last().respond(200, snapshot);

    assert.equal(
      element.currentTag,
      "tag-1",
      "The current load must still apply.",
    );
  } finally {
    vi.useRealTimers();
    element.remove();
  }
});

test("a response belonging to a superseded load is dropped", () => {
  var element = mount({ "data-url": "/debug/toolbar" });
  var stale = transport.last();

  element.load();
  stale.respond(200, snapshot);

  assert.equal(element.data, null, "A stale response must not be applied.");

  transport.last().respond(200, snapshot);

  assert.equal(
    element.currentTag,
    "tag-1",
    "The current response must be applied.",
  );

  element.remove();
});

test("following a new tag reloads the toolbar for that request", () => {
  var element = mount({ "data-url": "/debug/toolbar?tag=tag-1" });

  transport.last().respond(200, snapshot);
  element.followTag("tag-2");

  assert.equal(
    transport.last().url,
    "http://localhost:3000/debug/toolbar?tag=tag-2",
    "Follow-up request must carry the new tag.",
  );

  transport.last().respond(200, JSON.stringify({ items: [], tag: "tag-2" }));

  assert.equal(element.currentTag, "tag-2", "Tracked tag must move on.");

  element.remove();
});

test("following the tag already on screen is a no-op", () => {
  var element = mount({ "data-url": "/debug/toolbar?tag=tag-1" });

  transport.last().respond(200, snapshot);

  var issued = transport.requests.length;

  element.followTag("tag-1");
  element.followTag(null);

  assert.equal(transport.requests.length, issued, "No request may be issued.");

  element.currentTag = null;
  element.followTag("tag-1");

  assert.equal(
    transport.requests.length,
    issued,
    "A tag that resolves to the same URL must be skipped.",
  );

  element.remove();
});

test("following a tag without a data URL is a no-op", () => {
  var element = mount();
  var issued = transport.requests.length;

  element.followTag("tag-2");

  assert.equal(transport.requests.length, issued, "No request may be issued.");
  assert.equal(element.currentTag, null, "No tag may be tracked.");

  element.remove();
});

test("a rejected tag rolls back to the last good snapshot", () => {
  var element = mount({ "data-url": "/debug/toolbar?tag=tag-1" });

  transport.last().respond(200, snapshot);
  element.followTag("tag-2");
  transport.last().respond(500, "");

  assert.equal(element.currentTag, "tag-1", "Tracked tag must roll back.");
  assert.equal(
    element.getAttribute("data-url"),
    "/debug/toolbar?tag=tag-1",
    "Data URL must roll back.",
  );
  assert.ok(
    element.shadowRoot.querySelector('[title="Database"]'),
    "Last good snapshot must stay on screen.",
  );
  assert.equal(
    element.shadowRoot.querySelector(".error-message"),
    null,
    "A recoverable failure must not paint an error.",
  );

  element.remove();
});

test("a rejected tag without a snapshot reloads the previous data URL", () => {
  var element = createToolbar({ "data-url": "/debug/toolbar?tag=tag-1" });

  element.followTag("tag-2");

  assert.equal(
    transport.last().url,
    "http://localhost:3000/debug/toolbar?tag=tag-2",
    "Follow-up request must carry the new tag.",
  );

  transport.last().respond(500, "");

  assert.equal(element.currentTag, null, "Tracked tag must be forgotten.");
  assert.equal(
    element.getAttribute("data-url"),
    "/debug/toolbar?tag=tag-1",
    "Data URL must roll back.",
  );
  assert.equal(
    transport.last().url,
    "/debug/toolbar?tag=tag-1",
    "Rollback must refetch the previous snapshot.",
  );
});
