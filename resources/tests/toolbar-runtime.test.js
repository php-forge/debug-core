import assert from "node:assert/strict";
import test from "node:test";

import { builtinIconUrl } from "../src/toolbar/icons.js";
import { toolbarRetryDelay } from "../src/toolbar/loading.js";

test("builtinIconUrl provides self-contained toolbar control icons", () => {
  assert.match(builtinIconUrl("sun"), /^data:image\/svg\+xml,/);
  assert.match(builtinIconUrl("external-link"), /^data:image\/svg\+xml,/);
  assert.equal(builtinIconUrl("request"), "");
});

test("toolbarRetryDelay retries missing snapshots with bounded backoff", () => {
  assert.equal(toolbarRetryDelay(404, 0), 75);
  assert.equal(toolbarRetryDelay(404, 4), 900);
  assert.equal(toolbarRetryDelay(404, 5), null);
  assert.equal(toolbarRetryDelay(500, 0), null);
});
