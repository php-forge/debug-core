import assert from "node:assert/strict";
import { spawnSync } from "node:child_process";
import { fileURLToPath } from "node:url";
import { build } from "vite";
import { afterEach, beforeEach, test, vi } from "vitest";

import globalSetup from "../../e2e/global-setup.js";

vi.mock("node:child_process", () => ({ spawnSync: vi.fn() }));
vi.mock("vite", () => ({ build: vi.fn() }));

beforeEach(() => {
  build.mockReset().mockResolvedValue(undefined);
  spawnSync.mockReset().mockReturnValue({ status: 0 });
  vi.stubEnv("DEBUG_UI_SEED_FIXTURES", undefined);
  vi.stubEnv("PHP_BINARY", undefined);
});

afterEach(() => {
  vi.unstubAllEnvs();
});

test("fixture-free browser runs still build assets once with the repository config", async () => {
  vi.stubEnv("DEBUG_UI_SEED_FIXTURES", "0");

  await globalSetup();

  assert.deepEqual(build.mock.calls, [
    [
      {
        configFile: fileURLToPath(
          new URL("../../vite.config.js", import.meta.url),
        ),
      },
    ],
  ]);
  assert.equal(spawnSync.mock.calls.length, 0);
});

test("fixture seeding waits for the asset build to finish", async () => {
  let finishBuild;
  build.mockReturnValueOnce(
    new Promise((resolve) => {
      finishBuild = resolve;
    }),
  );

  const setup = globalSetup();
  assert.equal(build.mock.calls.length, 1);
  assert.equal(spawnSync.mock.calls.length, 0);

  finishBuild();
  await setup;

  assert.equal(build.mock.calls.length, 1);
  assert.deepEqual(spawnSync.mock.calls, [
    [
      "php",
      [
        fileURLToPath(
          new URL("../../tools/seed-debug-fixtures.php", import.meta.url),
        ),
        "--quiet",
      ],
      { encoding: "utf8", env: process.env },
    ],
  ]);
});

for (const seedFixtures of ["0", "1"]) {
  test(`build failures abort setup with DEBUG_UI_SEED_FIXTURES=${seedFixtures}`, async () => {
    vi.stubEnv("DEBUG_UI_SEED_FIXTURES", seedFixtures);
    const error = new Error("Unable to compile the current stylesheet.");
    build.mockRejectedValueOnce(error);

    await assert.rejects(globalSetup(), (failure) => failure === error);

    assert.equal(build.mock.calls.length, 1);
    assert.equal(spawnSync.mock.calls.length, 0);
  });
}

test("fixture seeding preserves the configured PHP binary", async () => {
  vi.stubEnv("PHP_BINARY", "/custom/php");

  await globalSetup();

  assert.equal(spawnSync.mock.calls.length, 1);
  assert.equal(spawnSync.mock.calls[0][0], "/custom/php");
});

test("fixture failures remain visible after a successful build", async () => {
  spawnSync.mockReturnValueOnce({
    status: 1,
    stdout: "Preparing fixtures.",
    stderr: "Fixture application is unavailable.",
  });

  await assert.rejects(
    globalSetup(),
    /Unable to seed deterministic debug snapshots with php\.\nPreparing fixtures\.\nFixture application is unavailable\./,
  );
  assert.equal(build.mock.calls.length, 1);
});
