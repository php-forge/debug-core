import assert from "node:assert/strict";
import { test } from "vitest";

import {
  extensionsBadgeStatus,
  isInsideExtensions,
  shouldCloseExtensionsMenu,
  splitToolbarPanels,
} from "../src/toolbar/extensions.js";

test("extension panels leave the inline strip in payload order", () => {
  var request = { id: "request" };
  var inertia = { id: "inertia", extension: true };
  var db = { id: "db", extension: false };
  var profiling = { id: "profiling" };
  var queue = { id: "queue", extension: true };

  assert.deepEqual(
    splitToolbarPanels([request, inertia, db, profiling, queue], ["profiling"]),
    { inline: [request, db], extensions: [inertia, queue] },
  );
});

test("panel splitting skips empty entries and an absent payload", () => {
  var db = { id: "db" };

  assert.deepEqual(splitToolbarPanels([null, db, undefined, false], []), {
    inline: [db],
    extensions: [],
  });
  assert.deepEqual(splitToolbarPanels(null, ["profiling"]), {
    inline: [],
    extensions: [],
  });
});

test("panel splitting excludes nothing without an exclusion list", () => {
  var profiling = { id: "profiling" };
  var inertia = { id: "inertia", extension: true };

  assert.deepEqual(splitToolbarPanels([profiling, inertia]), {
    inline: [profiling],
    extensions: [inertia],
  });
});

test("the Extensions badge surfaces a failure hidden inside the menu", () => {
  var healthy = {
    id: "inertia",
    items: [{ status: "success" }, { status: "default" }],
  };
  var failing = {
    id: "queue",
    items: [{ status: "info" }, { status: "danger" }],
  };
  var metricFree = { id: "asset" };

  assert.equal(extensionsBadgeStatus([healthy, failing]), "danger");
  assert.equal(extensionsBadgeStatus([healthy, metricFree]), "default");
  assert.equal(extensionsBadgeStatus([]), "default");
});

test("Escape closes only an open Extensions menu", () => {
  assert.equal(
    shouldCloseExtensionsMenu({ key: "Escape", defaultPrevented: false }, true),
    true,
  );
  assert.equal(
    shouldCloseExtensionsMenu({ key: "Enter", defaultPrevented: false }, true),
    false,
  );
  assert.equal(
    shouldCloseExtensionsMenu({ key: "Escape", defaultPrevented: true }, true),
    false,
  );
  assert.equal(
    shouldCloseExtensionsMenu(
      { key: "Escape", defaultPrevented: false },
      false,
    ),
    false,
  );
});

test("outside pointers are told apart from the Extensions wrapper", () => {
  var wrapper = { className: "extensions" };
  var menuItem = { parentElement: wrapper };
  var control = { parentElement: null };
  var closestStub = function (target, selector) {
    if (selector !== ".extensions") {
      return null;
    }

    return target === wrapper || target.parentElement === wrapper
      ? wrapper
      : null;
  };

  assert.equal(isInsideExtensions(wrapper, closestStub), true);
  assert.equal(isInsideExtensions(menuItem, closestStub), true);
  assert.equal(isInsideExtensions(control, closestStub), false);
});
