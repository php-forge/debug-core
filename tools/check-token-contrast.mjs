import { readFileSync } from "node:fs";
import { resolve } from "node:path";
import { fileURLToPath } from "node:url";

const repositoryRoot = fileURLToPath(new URL("..", import.meta.url));
const budget = JSON.parse(
    readFileSync(
        new URL("./quality/token-contrast-budget.json", import.meta.url),
        "utf8",
    ),
);
const source = readFileSync(resolve(repositoryRoot, budget.source), "utf8");
const strict = process.argv.includes("--strict");

function parseTokens(css) {
    const tokens = new Map();
    const colorPattern =
        /(--[\w-]+)\s*:\s*light-dark\(\s*(#[0-9a-f]{3}(?:[0-9a-f]{3})?)\s*,\s*(#[0-9a-f]{3}(?:[0-9a-f]{3})?)\s*\)\s*;/gi;
    const aliasPattern = /(--[\w-]+)\s*:\s*var\(\s*(--[\w-]+)\s*\)\s*;/gi;

    for (const match of css.matchAll(colorPattern)) {
        tokens.set(match[1], { light: match[2], dark: match[3] });
    }

    for (const match of css.matchAll(aliasPattern)) {
        tokens.set(match[1], { alias: match[2] });
    }

    return tokens;
}

function resolveToken(tokens, name, visited = new Set()) {
    if (visited.has(name)) {
        throw new Error(
            `Circular color token alias: ${[...visited, name].join(" -> ")}`,
        );
    }

    const value = tokens.get(name);

    if (!value) {
        throw new Error(
            `Color token is unavailable or is not a hex light-dark() pair: ${name}`,
        );
    }

    if (!value.alias) {
        return value;
    }

    return resolveToken(tokens, value.alias, new Set([...visited, name]));
}

function rgb(hex) {
    const normalized =
        hex.length === 4
            ? `#${[...hex.slice(1)].map((value) => value.repeat(2)).join("")}`
            : hex;

    return [1, 3, 5].map((offset) =>
        Number.parseInt(normalized.slice(offset, offset + 2), 16),
    );
}

function luminance(hex) {
    const channels = rgb(hex).map((channel) => {
        const value = channel / 255;

        return value <= 0.04045
            ? value / 12.92
            : ((value + 0.055) / 1.055) ** 2.4;
    });

    return 0.2126 * channels[0] + 0.7152 * channels[1] + 0.0722 * channels[2];
}

function contrast(foreground, background) {
    const first = luminance(foreground);
    const second = luminance(background);

    return (Math.max(first, second) + 0.05) / (Math.min(first, second) + 0.05);
}

function resolveColorReference(tokens, reference, mode) {
    if (/^#[0-9a-f]{3}(?:[0-9a-f]{3})?$/i.test(reference)) {
        return reference;
    }

    return resolveToken(tokens, reference)[mode];
}

const tokens = parseTokens(source);
const failures = [];
const rows = [];

for (const check of budget.checks) {
    let colors;

    try {
        colors = resolveToken(tokens, check.token);
    } catch (error) {
        failures.push(error.message);
        continue;
    }

    for (const mode of ["light", "dark"]) {
        const ratio = contrast(colors[mode], budget.referenceSurfaces[mode]);
        const acceptedFloor = strict
            ? check.minimum
            : (check.acceptedFloor?.[mode] ?? check.minimum);
        const debt = ratio < check.minimum && ratio >= acceptedFloor;

        rows.push({
            token: check.token,
            mode,
            role: check.role,
            foreground: colors[mode],
            background: budget.referenceSurfaces[mode],
            ratio: `${ratio.toFixed(2)}:1`,
            minimum: `${check.minimum.toFixed(1)}:1`,
            status: debt
                ? "BASELINED DEBT"
                : ratio >= acceptedFloor
                  ? "PASS"
                  : "FAIL",
        });

        if (ratio < acceptedFloor) {
            failures.push(
                `${check.token} in ${mode} is ${ratio.toFixed(2)}:1; the accepted floor is ${acceptedFloor.toFixed(2)}:1.`,
            );
        }
    }
}

for (const composition of budget.compositions ?? []) {
    for (const mode of ["light", "dark"]) {
        let foreground;
        let background;

        try {
            foreground = resolveColorReference(
                tokens,
                composition.foreground,
                mode,
            );
            background = resolveColorReference(
                tokens,
                composition.background,
                mode,
            );
        } catch (error) {
            failures.push(error.message);
            continue;
        }

        const ratio = contrast(foreground, background);
        const acceptedFloor = strict
            ? composition.minimum
            : (composition.acceptedFloor?.[mode] ?? composition.minimum);
        const debt = ratio < composition.minimum && ratio >= acceptedFloor;

        rows.push({
            token: composition.name,
            mode,
            role: composition.role,
            foreground,
            background,
            ratio: `${ratio.toFixed(2)}:1`,
            minimum: `${composition.minimum.toFixed(1)}:1`,
            status: debt
                ? "BASELINED DEBT"
                : ratio >= acceptedFloor
                  ? "PASS"
                  : "FAIL",
        });

        if (ratio < acceptedFloor) {
            failures.push(
                `${composition.name} in ${mode} is ${ratio.toFixed(2)}:1; the accepted floor is ${acceptedFloor.toFixed(2)}:1.`,
            );
        }
    }
}

console.log(
    `Token contrast against light ${budget.referenceSurfaces.light} and dark ${budget.referenceSurfaces.dark}${strict ? " (strict)" : ""}`,
);
console.table(rows);

const debts = [...budget.checks, ...(budget.compositions ?? [])].filter(
    (check) => check.debt,
);

if (!strict && debts.length > 0) {
    console.log("\nBaselined contrast debt:");
    for (const check of debts) {
        console.log(`- ${check.token}: ${check.debt}`);
    }
}

if (failures.length > 0) {
    console.error(`\n${failures.map((failure) => `- ${failure}`).join("\n")}`);
    process.exitCode = 1;
}
