/**
 * Verifies that every `yii-debug-*` name the PHP renderers emit is defined by the frontend that ships with them.
 *
 * The stylesheet lives in this package while framework adapters render into it, so a name spelled at a PHP call site
 * is a cross-repository contract no compiler checks. This tool closes that gap in both directions: names emitted but
 * never defined, and names defined but never emitted.
 *
 * Usage:
 * ```bash
 * npm run check:css-vocabulary
 * npm run check:css-vocabulary -- --source=../../yii2-extensions/debug/src
 * npm run check:css-vocabulary -- --unused
 * ```
 */
import { readFileSync, readdirSync } from "node:fs";
import { relative, resolve } from "node:path";
import { fileURLToPath } from "node:url";

const repositoryRoot = fileURLToPath(new URL("..", import.meta.url));
const allowlist = JSON.parse(
    readFileSync(
        new URL("./quality/css-vocabulary-allowlist.json", import.meta.url),
        "utf8",
    ),
);

/**
 * Sources emitting names: PHP renderers plus the view templates they include.
 */
const DEFAULT_EMITTERS = ["src", "resources/views"];

/**
 * Sources defining names: the stylesheets, and the scripts that add classes or read data attributes at runtime.
 */
const DEFINERS = [
    "resources/src/styles",
    "resources/src/toolbar",
    "resources/src",
];

const TOKEN = /yii-debug-[a-z0-9-]*/g;
const CSS_CLASS = /\.(yii-debug-[a-z0-9-]*)/g;

function listFiles(directory, extensions) {
    let entries;

    try {
        entries = readdirSync(directory, { withFileTypes: true });
    } catch {
        return [];
    }

    return entries
        .flatMap((entry) => {
            const path = resolve(directory, entry.name);

            if (entry.isDirectory()) {
                return listFiles(path, extensions);
            }

            return extensions.some((extension) =>
                entry.name.endsWith(extension),
            )
                ? [path]
                : [];
        })
        .sort();
}

/**
 * Strips comments so prose mentioning a name never counts as defining it.
 *
 * A stale comment must not keep a deleted rule alive: without this, removing `.yii-debug-x` while leaving a comment
 * that names it would still pass the check. Line comments are only stripped when they own the line, so a `//` inside
 * a URL or a string survives.
 */
function withoutComments(content) {
    return content
        .replace(/\/\*[\s\S]*?\*\//g, "")
        .replace(/^[ \t]*\/\/.*$/gm, "");
}

/**
 * Collects every name a set of files mentions, keeping the origin of each one for the report.
 */
function collect(files, pattern, group = 0, stripComments = false) {
    const found = new Map();

    for (const file of files) {
        const raw = readFileSync(file, "utf8");
        const content = stripComments ? withoutComments(raw) : raw;
        const origin = relative(repositoryRoot, file);

        for (const match of content.matchAll(pattern)) {
            const name = match[group];
            const origins = found.get(name) ?? new Set();

            origins.add(origin);
            found.set(name, origins);
        }
    }

    return found;
}

function parseSources(argv) {
    const sources = [];
    let unused = false;

    for (const argument of argv) {
        if (argument === "--unused") {
            unused = true;

            continue;
        }

        if (argument.startsWith("--source=")) {
            sources.push(argument.slice("--source=".length));

            continue;
        }

        throw new Error(`Unknown option: ${argument}`);
    }

    return { sources: sources.length > 0 ? sources : DEFAULT_EMITTERS, unused };
}

/**
 * Whether the frontend defines a name.
 *
 * A literal ending in `-` is a prefix the renderer completes at runtime (`'yii-debug-verb-' . $verb`), so it holds as
 * long as the frontend defines at least one name built on it.
 */
function isDefined(name, definitions) {
    if (name.endsWith("-") === false) {
        return definitions.has(name);
    }

    for (const defined of definitions.keys()) {
        if (defined.startsWith(name) && defined !== name) {
            return true;
        }
    }

    return false;
}

const options = parseSources(process.argv.slice(2));
const emitterFiles = options.sources.flatMap((source) =>
    listFiles(resolve(repositoryRoot, source), [".php"]),
);

if (emitterFiles.length === 0) {
    console.error(`No PHP source found in: ${options.sources.join(", ")}`);
    process.exit(1);
}

const definerFiles = DEFINERS.flatMap((source) =>
    listFiles(resolve(repositoryRoot, source), [".css", ".js"]),
);
const emitted = collect(emitterFiles, TOKEN);
const defined = collect(definerFiles, TOKEN, 0, true);
const cssClasses = collect(
    definerFiles.filter((file) => file.endsWith(".css")),
    CSS_CLASS,
    1,
    true,
);
const known = { ...allowlist.deliberate, ...allowlist.pendingReview };
const undefinedNames = [...emitted.keys()]
    .filter((name) => isDefined(name, defined) === false)
    .filter((name) => name in known === false)
    .sort();
const staleAllowlist = Object.keys(known)
    .filter((name) => emitted.has(name) === false || isDefined(name, defined))
    .sort();
const pending = Object.keys(allowlist.pendingReview)
    .filter((name) => staleAllowlist.includes(name) === false)
    .sort();

console.log(
    `Scanned ${emitterFiles.length} PHP files (${emitted.size} names emitted) against ${definerFiles.length} frontend files (${defined.size} names defined).`,
);

if (options.unused) {
    const unused = [...cssClasses.keys()]
        .filter((name) => emitted.has(name) === false)
        .sort();

    console.log(`\n${unused.length} CSS classes no PHP source emits:`);

    for (const name of unused) {
        console.log(`  ${name}`);
    }
}

if (pending.length > 0) {
    console.log(`\n${pending.length} name(s) awaiting a styling decision:`);

    for (const name of pending) {
        console.log(`  ${name} - ${allowlist.pendingReview[name]}`);
    }
}

for (const name of staleAllowlist) {
    console.error(
        `Stale allowlist entry: ${name} is now defined, or no longer emitted. Remove it from tools/quality/css-vocabulary-allowlist.json.`,
    );
}

for (const name of undefinedNames) {
    const origins = [...(emitted.get(name) ?? [])].sort().join(", ");

    console.error(
        `Undefined: ${name} is emitted by ${origins} but the frontend defines no such name.`,
    );
}

const failures = undefinedNames.length + staleAllowlist.length;

if (failures > 0) {
    console.error(`\n${failures} vocabulary problem(s) found.`);
    process.exit(1);
}

console.log(
    "\nEvery emitted name is defined by the frontend or listed as a known exception.",
);
