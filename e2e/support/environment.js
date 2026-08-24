import { readFileSync } from "node:fs";
import { resolve } from "node:path";
import { fileURLToPath } from "node:url";

const repositoryRoot = fileURLToPath(new URL("../..", import.meta.url));
const contract = JSON.parse(
  readFileSync(
    new URL("../../tools/quality/fixture-contract.json", import.meta.url),
    "utf8",
  ),
);

export const panelEntries = [
  { id: "config", label: "Configuration", kind: "panel" },
  { id: "request", label: "Request", kind: "panel" },
  { id: "router", label: "Router", kind: "panel" },
  { id: "inertia", label: "Inertia", kind: "panel" },
  { id: "user", label: "User", kind: "panel" },
  { id: "log", label: "Logs", kind: "panel" },
  { id: "db", label: "Database", kind: "panel" },
  { id: "profiling", label: "Profiling", kind: "panel" },
  { id: "timeline", label: "Timeline", kind: "panel" },
  { id: "event", label: "Events", kind: "panel" },
  { id: "mail", label: "Mail", kind: "panel" },
  { id: "queue", label: "Queue", kind: "panel" },
  { id: "dump", label: "Dump", kind: "panel" },
  { id: "asset", label: "Asset Bundles", kind: "panel" },
];

export const standaloneEntries = [
  { id: "history", label: "Request history", kind: "history" },
  { id: "compare", label: "Snapshot comparison", kind: "compare" },
  { id: "phpinfo", label: "PHP Info", kind: "phpinfo" },
];

export const visualEntries = [...standaloneEntries, ...panelEntries];
export const fixtureContract = contract;
export { repositoryRoot };

function validatedBaseURL(value, index) {
  let url;

  try {
    url = new URL(value);
  } catch (_error) {
    throw new Error(`DEBUG_UI_BASE_URLS entry ${index + 1} is not a URL.`);
  }

  if (url.protocol !== "http:" && url.protocol !== "https:") {
    throw new Error(
      `DEBUG_UI_BASE_URLS entry ${index + 1} must use http or https.`,
    );
  }

  return url.href.replace(/\/$/, "");
}

export function debugApps() {
  const configuredURLs = process.env.DEBUG_UI_BASE_URLS?.split(",")
    .map((value) => value.trim())
    .filter(Boolean);
  const configuredNames = process.env.DEBUG_UI_APP_NAMES?.split(",")
    .map((value) => value.trim())
    .filter(Boolean);
  const defaults = contract.apps;

  if (configuredURLs?.length && configuredURLs.length !== defaults.length) {
    throw new Error(
      `DEBUG_UI_BASE_URLS must provide ${defaults.length} origins in fixture-contract order.`,
    );
  }

  if (configuredNames?.length && configuredNames.length !== defaults.length) {
    throw new Error(
      `DEBUG_UI_APP_NAMES must provide ${defaults.length} names in fixture-contract order.`,
    );
  }

  const urls = configuredURLs?.length
    ? configuredURLs
    : defaults.map((app) => app.baseURL);

  return urls.map((baseURL, index) => ({
    name:
      configuredNames?.[index] ?? defaults[index]?.name ?? `app-${index + 1}`,
    baseURL: validatedBaseURL(baseURL, index),
  }));
}

export function fixtureTag(state = "dense") {
  const tag = contract.states?.[state]?.tag;

  if (typeof tag !== "string" || tag === "") {
    throw new Error(`Unknown debug fixture state: ${state}`);
  }

  return tag;
}

export function debugPageURL(app, entry, options = {}) {
  const theme = options.theme ?? "light";
  const state = options.state ?? "dense";
  let path;

  if (entry.kind === "history") {
    path = "/debug/index";
  } else if (entry.kind === "compare") {
    path = "/debug/compare";
  } else if (entry.kind === "phpinfo") {
    path = "/debug/php-info";
  } else {
    path = "/debug/view";
  }

  const url = new URL(path, `${app.baseURL}/`);
  url.searchParams.set("yii_debug_theme", theme);

  if (entry.kind === "history") {
    url.searchParams.set("Debug[tag]", contract.historyFilter);
  } else if (entry.kind === "compare") {
    url.searchParams.set("baseline", fixtureTag("empty"));
    url.searchParams.set("target", fixtureTag("dense"));
  } else if (entry.kind === "panel") {
    url.searchParams.set("tag", fixtureTag(state));
    url.searchParams.set("panel", entry.id);
  }

  if (options.pageSize) {
    url.searchParams.set("per-page", options.pageSize);
  }

  return url.href;
}

export function fixtureApplicationPaths() {
  return contract.apps.map((app) => resolve(repositoryRoot, app.path));
}

export function fixtureSnapshotPath(appIndex, state = "dense") {
  const applicationPath = fixtureApplicationPaths()[appIndex];

  if (!applicationPath) {
    throw new Error(`Fixture application index is unavailable: ${appIndex}`);
  }

  return resolve(
    applicationPath,
    "runtime",
    "debug",
    `${fixtureTag(state)}.json`,
  );
}
