/**
 * Compares the toolbar's inline glyph inventory with the SVG files on disk.
 *
 * The toolbar ships its glyphs inline (`resources/src/toolbar/icons.js`) so the
 * shadow DOM stays self-contained, while the panel pages render the files under
 * `resources/assets/svg/` through PHP `Helper\Icon`. Both inventories must draw
 * the same artwork; this script reports, per key, whether they do.
 *
 * Markup is normalized before comparing — attribute order and insignificant
 * whitespace carry no meaning in SVG — but no artwork is ever rewritten.
 *
 * The two inventories are not aligned yet, so a difference is a warning and the
 * run stays green. Pass `--strict` to fail on one, which is what the repository
 * must switch to once the authoritative artwork has been picked.
 */
import { readFileSync, existsSync } from "node:fs";
import { resolve } from "node:path";
import { fileURLToPath } from "node:url";

import { builtinIcons } from "../resources/src/toolbar/icons.js";

const repositoryRoot = fileURLToPath(new URL("..", import.meta.url));
const svgRoot = resolve(repositoryRoot, "resources/assets/svg");
const strict = process.argv.includes("--strict");

/** Attributes that only size the rendered glyph, never its geometry. */
const PRESENTATION_ATTRIBUTES = ["width", "height"];

/**
 * Rewrites one tag with its attributes sorted by name, dropping the ones named
 * in `ignored`.
 */
function normalizeTag(tag, ignored) {
    const match = /^<\s*([a-zA-Z0-9:-]+)([\s\S]*?)(\/?)>$/.exec(tag);

    if (match === null) {
        return tag;
    }

    const [, name, rest, selfClosing] = match;
    const attributes = [...rest.matchAll(/([a-zA-Z0-9:_.-]+)\s*=\s*"([^"]*)"/g)]
        .filter(([, attribute]) => !ignored.includes(attribute))
        .map(([, attribute, value]) => ` ${attribute}="${value}"`)
        .sort();

    return `<${name}${attributes.join("")}${selfClosing}>`;
}

/**
 * Reduces SVG markup to a comparable form: no XML prolog, no comments, no
 * insignificant whitespace and attributes in a stable order.
 */
function normalize(markup, options = {}) {
    const ignored = options.ignoreAttributes ?? [];
    let normalized = markup
        .replace(/<\?xml[\s\S]*?\?>/g, "")
        .replace(/<!--[\s\S]*?-->/g, "")
        .replace(/>\s+</g, "><")
        .trim();

    if (options.dropTitles === true) {
        normalized = normalized.replace(/<title\b[\s\S]*?<\/title>/g, "");
    }

    return normalized.replace(/<[^>]+>/g, (tag) => normalizeTag(tag, ignored));
}

/** Reports how two normalized glyphs differ, or `null` when they do not. */
function compare(inline, file) {
    if (normalize(inline) === normalize(file)) {
        return null;
    }

    const options = {
        dropTitles: true,
        ignoreAttributes: PRESENTATION_ATTRIBUTES,
    };

    return normalize(inline, options) === normalize(file, options)
        ? "presentation only (width/height/title)"
        : "artwork";
}

const rows = [];
const differing = [];
const missing = [];

for (const key of Object.keys(builtinIcons).sort()) {
    const path = resolve(svgRoot, `${key}.svg`);

    if (!existsSync(path)) {
        missing.push(key);
        rows.push({ icon: key, result: "no file", difference: "" });

        continue;
    }

    const difference = compare(builtinIcons[key], readFileSync(path, "utf8"));

    if (difference === null) {
        rows.push({ icon: key, result: "identical", difference: "" });

        continue;
    }

    differing.push({ key, difference });
    rows.push({ icon: key, result: "differs", difference });
}

console.log(
    `Toolbar glyph inventory (${Object.keys(builtinIcons).length} keys) against resources/assets/svg`,
);
console.table(rows);
console.log(
    `Identical: ${rows.length - differing.length - missing.length}, ` +
        `differs: ${differing.length}, no file: ${missing.length}.`,
);

if (missing.length > 0) {
    console.log(
        `\nInline only (no file on disk, so the panels cannot render them): ${missing.join(", ")}.`,
    );
}

if (differing.length === 0) {
    console.log(
        "\nEvery key backed by a file is identical: `npm run build:icons` can " +
            "regenerate icons.js from the files.",
    );

    process.exit(0);
}

const report = differing
    .map(({ key, difference }) => `- ${key}: ${difference}`)
    .join("\n");

if (strict) {
    console.error(`\nInline glyphs differ from their file:\n${report}`);
    process.exitCode = 1;

    process.exit();
}

console.warn(
    `\nWarning: inline glyphs differ from their file:\n${report}\n` +
        "The inline set stays authoritative until the artwork is picked, so " +
        "icons.js is NOT generated from the files. Run with `--strict` to fail " +
        "on a difference.",
);
