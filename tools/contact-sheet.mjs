/**
 * Builds photographic-style contact sheets comparing the debugger UI across two running applications.
 *
 * The debugger ships one frontend consumed by several framework adapters, so the only honest regression signal is the
 * rendered page. Reading one composed sheet instead of two dozen full-page screenshots keeps that review cheap.
 *
 * Two artifact levels are produced per theme:
 * - `overview.png`: every panel as a small paired frame, to spot which panels diverge.
 * - `panel-<id>.png`: one panel per app, side by side, at a readable scale.
 *
 * Usage:
 * ```bash
 * npm run contact-sheet
 * npm run contact-sheet -- --theme=dark --only=db,request
 * npm run contact-sheet -- --apps=yii2=http://localhost:8080,yii3=http://localhost:8081
 * npm run contact-sheet -- --tags=yii2=161a8a4e,yii3=6aa86048
 * ```
 */
import { mkdir, readdir, rm, writeFile } from "node:fs/promises";
import { relative, resolve, sep } from "node:path";
import { fileURLToPath, pathToFileURL } from "node:url";

import { chromium } from "@playwright/test";

const repositoryRoot = fileURLToPath(new URL("..", import.meta.url));

const DEFAULTS = {
    apps: "yii2=http://localhost:8080,yii3=http://localhost:8081",
    maxHeight: 1100,
    only: "",
    out: "artifacts/contact-sheets",
    overviewColumns: 3,
    panelWidth: 760,
    tags: "",
    theme: "light",
    thumbHeight: 170,
    thumbWidth: 240,
    viewport: 1280,
};

/**
 * History pages differ per adapter: Yii2 routes the index action explicitly, Yii3 serves it from the module root.
 */
const HISTORY_PATHS = ["/debug/index", "/debug"];

function parseArguments(argv) {
    const options = { ...DEFAULTS };

    for (let index = 0; index < argv.length; index += 1) {
        const argument = argv[index];

        if (argument.startsWith("--") === false) {
            continue;
        }

        const body = argument.slice(2);
        const assignment = body.indexOf("=");
        const rawKey = assignment === -1 ? body : body.slice(0, assignment);
        const inlineValue =
            assignment === -1 ? undefined : body.slice(assignment + 1);
        const key = rawKey.replace(/-([a-z])/g, (_match, letter) =>
            letter.toUpperCase(),
        );

        if (key in options === false) {
            throw new Error(`Unknown option: --${rawKey}`);
        }

        const value = inlineValue ?? argv[(index += 1)];

        if (value === undefined) {
            throw new Error(`Option --${rawKey} requires a value.`);
        }

        options[key] =
            typeof DEFAULTS[key] === "number" ? Number(value) : value;
    }

    if (["light", "dark"].includes(options.theme) === false) {
        throw new Error("Option --theme must be either light or dark.");
    }

    return options;
}

function parseApps(value) {
    const apps = value
        .split(",")
        .map((entry) => entry.trim())
        .filter(Boolean)
        .map((entry) => {
            const separator = entry.indexOf("=");
            const name = separator === -1 ? "" : entry.slice(0, separator);
            const baseURL =
                separator === -1 ? entry : entry.slice(separator + 1);

            return {
                baseURL: baseURL.replace(/\/$/, ""),
                name: name || baseURL,
            };
        });

    if (apps.length < 2) {
        throw new Error("Option --apps must list at least two applications.");
    }

    const names = new Set();

    for (const app of apps) {
        assertFileSafe(app.name, "Application name");

        if (names.has(app.name)) {
            throw new Error(
                `Option --apps repeats the application name: ${app.name}. Each name keys its own screenshots.`,
            );
        }

        names.add(app.name);
    }

    return apps;
}

/**
 * Rejects a value that would leave the screenshot directory once it becomes part of a file name.
 *
 * Application names come from the command line and panel ids are scraped from a page, so neither is trusted to stay
 * inside `shotsRoot`.
 */
function assertFileSafe(value, label) {
    if (/^[A-Za-z0-9][A-Za-z0-9._-]*$/.test(value) === false) {
        throw new Error(
            `${label} must use letters, digits, dots, dashes or underscores, and start with a letter or digit: ${value}`,
        );
    }
}

/**
 * Reads the newest capture tag and the panel inventory straight from an application's history page.
 *
 * Tags are generated per request, so they cannot be hardcoded; the history page is the one place both adapters
 * publish them in the same shape.
 */
async function discoverCapture(page, app) {
    for (const path of HISTORY_PATHS) {
        const response = await page
            .goto(`${app.baseURL}${path}`, { waitUntil: "domcontentloaded" })
            .catch(() => null);

        if (response?.ok() !== true) {
            continue;
        }

        const hrefs = await page.$$eval('a[href*="/debug/view"]', (nodes) =>
            nodes.map((node) => node.getAttribute("href") ?? ""),
        );

        let tag = "";
        const panels = [];

        for (const href of hrefs) {
            const url = new URL(href, `${app.baseURL}/`);
            const panel = url.searchParams.get("panel");

            tag ||= url.searchParams.get("tag") ?? "";

            if (panel !== null && panels.includes(panel) === false) {
                panels.push(panel);
            }
        }

        if (tag !== "" && panels.length > 0) {
            return { historyURL: `${app.baseURL}${path}`, panels, tag };
        }
    }

    throw new Error(
        `${app.name}: no capture found at ${app.baseURL}. Browse the application once so the debugger records a request.`,
    );
}

/**
 * Parses the `name=tag` pairs that pin a capture per application.
 *
 * Each request the debugger records rotates the newest tag, so comparing the same panel across a code change needs the
 * capture held still.
 */
function parseTags(value) {
    const tags = new Map();

    for (const entry of value
        .split(",")
        .map((part) => part.trim())
        .filter(Boolean)) {
        const separator = entry.indexOf("=");

        if (separator === -1) {
            throw new Error(
                `Option --tags expects name=tag pairs, got: ${entry}`,
            );
        }

        tags.set(entry.slice(0, separator), entry.slice(separator + 1));
    }

    return tags;
}

async function freezeMotion(page) {
    await page.addStyleTag({
        content: `
            *, *::before, *::after {
                animation-delay: 0s !important;
                animation-duration: 0s !important;
                caret-color: transparent !important;
                scroll-behavior: auto !important;
                transition-delay: 0s !important;
                transition-duration: 0s !important;
            }
        `,
    });
}

async function waitForStableUI(page) {
    await page.evaluate(async () => {
        if (document.fonts?.ready) {
            await document.fonts.ready;
        }

        await new Promise((done) => {
            requestAnimationFrame(() => requestAnimationFrame(done));
        });
    });
}

/**
 * Resolves the screenshot path and refuses one that would land outside the screenshot directory.
 *
 * Both halves of the name are validated beforehand; this is the belt that proves the result stayed put.
 */
function shotPath(shotsRoot, name, panel) {
    const file = resolve(shotsRoot, `${name}-${panel}.png`);

    if (file.startsWith(shotsRoot + sep) === false) {
        throw new Error(
            `Refusing to write a screenshot outside ${shotsRoot}: ${file}`,
        );
    }

    return file;
}

async function capturePanel(page, app, panel, options, file) {
    const url = new URL("/debug/view", `${app.baseURL}/`);

    url.searchParams.set("panel", panel);
    url.searchParams.set("tag", app.tag);
    url.searchParams.set("yii_debug_theme", options.theme);

    const response = await page
        .goto(url.href, { waitUntil: "domcontentloaded" })
        .catch(() => null);
    const status = response?.status() ?? 0;

    await freezeMotion(page);
    await waitForStableUI(page);
    await page.screenshot({ fullPage: true, path: file, scale: "css" });

    return { ok: status >= 200 && status < 400, status, url: url.href };
}

function escapeHtml(value) {
    return String(value).replace(
        /[&<>"]/g,
        (character) =>
            ({ "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;" })[
                character
            ],
    );
}

function sheetStyles(options) {
    const ink = options.theme === "dark" ? "#e6efe9" : "#12201a";
    const ground = options.theme === "dark" ? "#0c120e" : "#f2f5f1";
    const frame = options.theme === "dark" ? "#243028" : "#ffffff";
    const rule = options.theme === "dark" ? "#33453a" : "#ccd8d0";

    return `
        * { box-sizing: border-box; }
        body {
            background: ${ground};
            color: ${ink};
            font: 13px/1.4 ui-sans-serif, system-ui, sans-serif;
            margin: 0;
            padding: 16px;
        }
        h1 { font-size: 15px; margin: 0 0 4px; }
        .meta { font-size: 11px; margin: 0 0 14px; opacity: 0.75; }
        .grid { display: grid; gap: 12px; justify-content: start; }
        .frame {
            background: ${frame};
            border: 1px solid ${rule};
            border-radius: 6px;
            margin: 0;
            overflow: hidden;
        }
        .frame > figcaption {
            border-bottom: 1px solid ${rule};
            font-weight: 600;
            padding: 5px 8px;
        }
        .pair { display: flex; gap: 1px; background: ${rule}; }
        .shot { background: ${frame}; flex: 1 1 0; min-width: 0; }
        .shot > span {
            display: block;
            font-size: 10px;
            letter-spacing: 0.04em;
            opacity: 0.7;
            padding: 3px 6px;
            text-transform: uppercase;
        }
        .shot img { display: block; object-position: top; width: 100%; }
        .failed { color: #c0392b; font-weight: 600; }
    `;
}

function overviewHtml(rows, apps, options) {
    const frames = rows
        .map((row) => {
            const shots = apps
                .map((app) => {
                    const result = row.results[app.name];
                    const body = result.captured
                        ? `<img alt="${escapeHtml(`${app.name} ${row.panel}`)}" height="${options.thumbHeight}" src="${escapeHtml(result.relativePath)}">`
                        : `<div class="failed" style="height:${options.thumbHeight}px;padding:6px">HTTP ${result.status}</div>`;

                    return `<div class="shot"><span>${escapeHtml(app.name)}</span>${body}</div>`;
                })
                .join("");

            return `<figure class="frame"><figcaption>${escapeHtml(row.panel)}</figcaption><div class="pair">${shots}</div></figure>`;
        })
        .join("");

    return `<!doctype html><meta charset="utf-8"><style>${sheetStyles(options)}
        .grid { grid-template-columns: repeat(${options.overviewColumns}, ${options.thumbWidth * apps.length + 2}px); }
        .shot img { height: ${options.thumbHeight}px; object-fit: cover; }
    </style>
    <h1>Debug UI contact sheet — ${escapeHtml(options.theme)}</h1>
    <p class="meta">${apps.map((app) => `${escapeHtml(app.name)} ${escapeHtml(app.baseURL)} tag ${escapeHtml(app.tag)}`).join(" · ")}</p>
    <div class="grid">${frames}</div>`;
}

function panelHtml(row, apps, options) {
    const shots = apps
        .map((app) => {
            const result = row.results[app.name];
            const body = result.captured
                ? `<img alt="${escapeHtml(`${app.name} ${row.panel}`)}" src="${escapeHtml(result.relativePath)}">`
                : `<div class="failed" style="padding:10px">HTTP ${result.status}</div>`;

            return `<div class="shot"><span>${escapeHtml(app.name)}</span>${body}</div>`;
        })
        .join("");

    return `<!doctype html><meta charset="utf-8"><style>${sheetStyles(options)}
        .pair { max-height: ${options.maxHeight}px; overflow: hidden; width: max-content; }
        .shot { flex: 0 0 auto; width: ${options.panelWidth}px; }
    </style>
    <h1>${escapeHtml(row.panel)} — ${escapeHtml(options.theme)}</h1>
    <p class="meta">${apps.map((app) => `${escapeHtml(app.name)} tag ${escapeHtml(app.tag)}`).join(" · ")}</p>
    <div class="pair">${shots}</div>`;
}

/**
 * Renders one sheet and trims the canvas to its content.
 *
 * Playwright's full-page screenshot never shrinks below the viewport and never widens past it, so the sheet is
 * measured first and the viewport resized to match. Without it every sheet carries dead pixels, and a sheet wider
 * than the viewport loses its right-hand column.
 */
/**
 * Deletes the sheets and screenshots a previous run left behind.
 *
 * Only files this tool names are removed, never the directory itself: `--out` may point at a directory holding
 * unrelated work. A narrower run would otherwise leave sheets for panels it no longer captures, and comparing those
 * against the fresh ones reads as a change that never happened.
 */
async function removeStaleArtifacts(outputRoot, shotsRoot) {
    const targets = [
        [outputRoot, /^(?:overview|panel-[A-Za-z0-9._-]+)\.png$/],
        [shotsRoot, /^[A-Za-z0-9._-]+-[A-Za-z0-9._-]+\.png$/],
    ];

    for (const [directory, pattern] of targets) {
        const entries = await readdir(directory, { withFileTypes: true }).catch(
            () => [],
        );

        for (const entry of entries) {
            if (entry.isFile() && pattern.test(entry.name)) {
                await rm(resolve(directory, entry.name), { force: true });
            }
        }
    }
}

async function composeSheet(page, directory, name, html) {
    const htmlFile = resolve(directory, `${name}.html`);
    const pngFile = resolve(directory, `${name}.png`);

    await writeFile(htmlFile, html, "utf8");
    await page.setViewportSize({ height: 900, width: 800 });
    await page.goto(pathToFileURL(htmlFile).href, { waitUntil: "networkidle" });

    const size = await page.evaluate(() => {
        const style = getComputedStyle(document.body);
        const padding = Number.parseFloat(style.paddingRight);

        return {
            height: Math.ceil(document.body.scrollHeight + padding),
            width: Math.ceil(document.body.scrollWidth + padding),
        };
    });

    await page.setViewportSize(size);
    await page.screenshot({ fullPage: true, path: pngFile, scale: "css" });
    await rm(htmlFile, { force: true });

    return pngFile;
}

async function main() {
    const options = parseArguments(process.argv.slice(2));
    const apps = parseApps(options.apps);
    const outputRoot = resolve(repositoryRoot, options.out, options.theme);
    const shotsRoot = resolve(outputRoot, "shots");

    await mkdir(shotsRoot, { recursive: true });
    await removeStaleArtifacts(outputRoot, shotsRoot);

    const browser = await chromium.launch();
    const context = await browser.newContext({
        colorScheme: options.theme,
        locale: "en-US",
        reducedMotion: "reduce",
        timezoneId: "UTC",
        viewport: { height: 900, width: options.viewport },
    });
    // The client-side toggle restores its remembered theme on load, so seed it before the first navigation.
    await context.addInitScript((selected) => {
        try {
            localStorage.setItem("theme", selected);
            localStorage.setItem("yii-debug-toolbar-theme", selected);
        } catch {
            // A storage-less context still renders the server-selected theme.
        }
    }, options.theme);

    const page = await context.newPage();

    try {
        const pinned = parseTags(options.tags);

        for (const app of apps) {
            const capture = await discoverCapture(page, app);

            app.panels = capture.panels;
            app.tag = pinned.get(app.name) ?? capture.tag;
            console.log(
                `${app.name}: tag ${app.tag}${pinned.has(app.name) ? " (pinned)" : ""}, ${capture.panels.length} panels from ${capture.historyURL}`,
            );
        }

        const [first, ...rest] = apps;
        const shared = first.panels.filter((panel) =>
            rest.every((app) => app.panels.includes(panel)),
        );

        for (const app of apps) {
            const exclusive = app.panels.filter(
                (panel) => shared.includes(panel) === false,
            );

            if (exclusive.length > 0) {
                console.log(
                    `${app.name}: panels without a counterpart, skipped — ${exclusive.join(", ")}`,
                );
            }
        }

        const requested = options.only
            .split(",")
            .map((panel) => panel.trim())
            .filter(Boolean);
        const panels =
            requested.length > 0
                ? shared.filter((panel) => requested.includes(panel))
                : shared;

        if (panels.length === 0) {
            throw new Error("No panel is available in every application.");
        }

        const rows = [];

        for (const panel of panels) {
            const results = {};

            assertFileSafe(panel, "Panel id");

            for (const app of apps) {
                const file = shotPath(shotsRoot, app.name, panel);
                const outcome = await capturePanel(
                    page,
                    app,
                    panel,
                    options,
                    file,
                );

                results[app.name] = {
                    captured: outcome.ok,
                    relativePath: relative(outputRoot, file),
                    status: outcome.status,
                };

                if (outcome.ok === false) {
                    console.log(
                        `${app.name}: ${panel} responded HTTP ${outcome.status} (${outcome.url})`,
                    );
                }
            }

            rows.push({ panel, results });
        }

        const sheets = [
            await composeSheet(
                page,
                outputRoot,
                "overview",
                overviewHtml(rows, apps, options),
            ),
        ];

        for (const row of rows) {
            sheets.push(
                await composeSheet(
                    page,
                    outputRoot,
                    `panel-${row.panel}`,
                    panelHtml(row, apps, options),
                ),
            );
        }

        console.log("\nContact sheets:");

        for (const sheet of sheets) {
            console.log(`  ${relative(repositoryRoot, sheet)}`);
        }
    } finally {
        await context.close();
        await browser.close();
    }
}

await main();
