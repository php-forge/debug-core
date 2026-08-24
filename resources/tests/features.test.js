import assert from "node:assert/strict";
import test from "node:test";

import {
  loadPanelFeatures,
  PANEL_FEATURE_MARKERS,
} from "../src/core/features.js";

test("panel features import only when their DOM marker exists", async () => {
  var loaded = [];
  var root = {
    querySelector(selector) {
      return selector === PANEL_FEATURE_MARKERS.phpinfo ? {} : null;
    },
  };
  var loaders = {
    db() {
      loaded.push("db");
    },
    phpinfo() {
      loaded.push("phpinfo");
    },
    userswitch() {
      loaded.push("userswitch");
    },
  };

  await loadPanelFeatures(root, loaders);

  assert.deepEqual(loaded, ["phpinfo"]);
});

test("panel feature import failures remain observable", async () => {
  var root = {
    querySelector(selector) {
      return selector === PANEL_FEATURE_MARKERS.db ? {} : null;
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
