import assert from "node:assert/strict";
import { utimes } from "node:fs/promises";
import { fileURLToPath } from "node:url";
import { beforeEach, test, vi } from "vitest";

import config from "../../vite.config.js";

vi.mock("node:fs/promises", async (importOriginal) => ({
  ...(await importOriginal()),
  utimes: vi.fn(),
}));

const publicationPlugin = config.plugins.find(
  (plugin) => plugin.name === "refresh-directory-publication-key",
);
const assetRoot = fileURLToPath(new URL("../assets", import.meta.url));

beforeEach(() => {
  utimes.mockReset();
});

test("publication-key refresh is limited to builds", () => {
  assert.equal(publicationPlugin.apply, "build");
});

test("failed builds preserve the asset publication key", async () => {
  await publicationPlugin.closeBundle(new Error("Build failed."));

  assert.equal(
    utimes.mock.calls.length,
    0,
    "A build failure must not make incomplete assets newly publishable.",
  );
});

test("successful builds refresh the asset root after an earlier failure", async () => {
  await publicationPlugin.closeBundle(new Error("Earlier build failed."));
  await publicationPlugin.closeBundle();

  assert.equal(
    utimes.mock.calls.length,
    1,
    "Only the successful build must refresh the publication key.",
  );
  const [path, accessTime, modificationTime] = utimes.mock.calls[0];

  assert.equal(
    path,
    assetRoot,
    "The directory publisher must observe the asset root, not its nested dist directory.",
  );
  assert.ok(
    accessTime instanceof Date,
    "Publication timestamps must be Date values.",
  );
  assert.ok(
    Number.isFinite(accessTime.getTime()),
    "Publication timestamps must be valid.",
  );
  assert.equal(
    modificationTime,
    accessTime,
    "Access and modification times must use the same build timestamp.",
  );
});

test("publication-key filesystem failures remain visible", async () => {
  const error = new Error("Asset root is not writable.");
  utimes.mockRejectedValueOnce(error);

  await assert.rejects(
    publicationPlugin.closeBundle(),
    error,
    "A publication-key failure must not be silently ignored.",
  );
});
