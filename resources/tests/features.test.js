import assert from "node:assert/strict";
import { test } from "vitest";

import {
  loadPanelFeatures,
  PANEL_FEATURE_MARKERS,
} from "../src/core/features.js";

test("panel feature markers pin the server-rendered selector contract", () => {
  assert.equal(
    PANEL_FEATURE_MARKERS.db,
    ".yii-debug-db-explain-toggle, [data-yii-debug-n1-filter]",
  );
  assert.equal(
    PANEL_FEATURE_MARKERS.phpinfo,
    "[data-yii-debug-phpinfo-search]",
  );
  assert.equal(
    PANEL_FEATURE_MARKERS.userswitch,
    "#debug-userswitch__filter, #debug-userswitch__reset-identity-button",
  );
  assert.deepEqual(Object.keys(PANEL_FEATURE_MARKERS), [
    "db",
    "phpinfo",
    "userswitch",
  ]);
  assert.equal(Object.isFrozen(PANEL_FEATURE_MARKERS), true);
});

test("panel features import only when their DOM marker exists", async () => {
  var loaded = [];
  var root = {
    querySelector(selector) {
      return selector === "[data-yii-debug-phpinfo-search]" ? {} : null;
    },
  };
  var loaders = {
    db() {
      loaded.push("db");
      return "db-feature";
    },
    phpinfo() {
      loaded.push("phpinfo");
      return "phpinfo-feature";
    },
    userswitch() {
      loaded.push("userswitch");
      return "userswitch-feature";
    },
  };

  var results = await loadPanelFeatures(root, loaders);

  assert.deepEqual(loaded, ["phpinfo"]);
  assert.deepEqual(results, ["phpinfo-feature"]);
});

test("default loaders import panel modules for present markers", async () => {
  globalThis.document = {
    getElementById() {
      return null;
    },
    querySelector() {
      return null;
    },
    querySelectorAll() {
      return [];
    },
  };
  globalThis.window = { location: { href: "https://example.test/" } };

  var results = await loadPanelFeatures({
    querySelector() {
      return {};
    },
  });

  assert.equal(results.length, 3);

  var [db, phpinfo, userswitch] = results;

  assert.equal(db.EXPLAIN_CONCURRENCY, 3);
  assert.equal(typeof phpinfo.initPhpInfoSearch, "function");
  assert.equal(typeof userswitch, "object");
  assert.notEqual(userswitch, null);
});

test("panel feature import failures remain observable", async () => {
  var root = {
    querySelector(selector) {
      return selector ===
        ".yii-debug-db-explain-toggle, [data-yii-debug-n1-filter]"
        ? {}
        : null;
    },
  };

  await assert.rejects(
    loadPanelFeatures(root, {
      db() {
        return Promise.reject(new Error("chunk missing"));
      },
      phpinfo() {},
      userswitch() {},
    }),
    /chunk missing/,
  );
});
