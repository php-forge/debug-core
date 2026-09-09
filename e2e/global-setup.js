import { spawnSync } from "node:child_process";
import { fileURLToPath } from "node:url";
import { build } from "vite";

export default async function globalSetup() {
  // Build once before workers start, including runs that skip fixture seeding.
  await build({
    configFile: fileURLToPath(new URL("../vite.config.js", import.meta.url)),
  });

  if (process.env.DEBUG_UI_SEED_FIXTURES === "0") {
    return;
  }

  const seeder = fileURLToPath(
    new URL("../tools/seed-debug-fixtures.php", import.meta.url),
  );
  const command = process.env.PHP_BINARY || "php";
  const result = spawnSync(command, [seeder, "--quiet"], {
    encoding: "utf8",
    env: process.env,
  });

  if (result.status !== 0) {
    const output = [result.stdout, result.stderr].filter(Boolean).join("\n");

    throw new Error(
      `Unable to seed deterministic debug snapshots with ${command}.\n${output}`,
    );
  }
}
