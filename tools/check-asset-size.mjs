import { gzipSync } from "node:zlib";
import { readdirSync, readFileSync } from "node:fs";
import { relative, resolve, sep } from "node:path";
import { fileURLToPath } from "node:url";

const repositoryRoot = fileURLToPath(new URL("..", import.meta.url));
const distributionRoot = resolve(repositoryRoot, "resources/assets/dist");
const budget = JSON.parse(
    readFileSync(
        new URL("./quality/asset-size-budget.json", import.meta.url),
        "utf8",
    ),
);

function listFiles(directory) {
    return readdirSync(directory, { withFileTypes: true })
        .flatMap((entry) => {
            const path = resolve(directory, entry.name);

            return entry.isDirectory() ? listFiles(path) : [path];
        })
        .sort();
}

function normalizedRelativePath(path) {
    return relative(distributionRoot, path).split(sep).join("/");
}

function humanBytes(value) {
    return `${(value / 1024).toFixed(2)} KiB`;
}

function percentage(value, maximum) {
    return `${((value / maximum) * 100).toFixed(1)}%`;
}

const configuredFiles = Object.keys(budget.files).sort();
const actualFiles = listFiles(distributionRoot).map(normalizedRelativePath);
const failures = [];

for (const missing of configuredFiles.filter(
    (path) => !actualFiles.includes(path),
)) {
    failures.push(`Expected asset is missing: ${missing}`);
}

for (const unexpected of actualFiles.filter(
    (path) => !configuredFiles.includes(path),
)) {
    failures.push(`Asset has no size budget: ${unexpected}`);
}

const rows = configuredFiles
    .filter((path) => actualFiles.includes(path))
    .map((path) => {
        const content = readFileSync(resolve(distributionRoot, path));
        const bytes = content.byteLength;
        const gzipBytes = gzipSync(content, {
            level: budget.compression.level,
        }).byteLength;
        const limits = budget.files[path];

        if (bytes > limits.maxBytes) {
            failures.push(
                `${path} is ${bytes} bytes; its raw budget is ${limits.maxBytes} bytes.`,
            );
        }

        if (gzipBytes > limits.maxGzipBytes) {
            failures.push(
                `${path} is ${gzipBytes} gzip bytes; its gzip budget is ${limits.maxGzipBytes} bytes.`,
            );
        }

        return { path, bytes, gzipBytes, limits };
    });

const totals = rows.reduce(
    (result, row) => ({
        bytes: result.bytes + row.bytes,
        gzipBytes: result.gzipBytes + row.gzipBytes,
    }),
    { bytes: 0, gzipBytes: 0 },
);

if (totals.bytes > budget.total.maxBytes) {
    failures.push(
        `Asset total is ${totals.bytes} bytes; the raw budget is ${budget.total.maxBytes} bytes.`,
    );
}

if (totals.gzipBytes > budget.total.maxGzipBytes) {
    failures.push(
        `Asset total is ${totals.gzipBytes} gzip bytes; the gzip budget is ${budget.total.maxGzipBytes} bytes.`,
    );
}

console.log(
    `Asset sizes (${budget.compression.algorithm} level ${budget.compression.level})`,
);
console.table(
    rows.map(({ path, bytes, gzipBytes, limits }) => ({
        asset: path,
        raw: humanBytes(bytes),
        "raw budget": percentage(bytes, limits.maxBytes),
        gzip: humanBytes(gzipBytes),
        "gzip budget": percentage(gzipBytes, limits.maxGzipBytes),
    })),
);
console.log(
    `Total: ${humanBytes(totals.bytes)} raw (${percentage(totals.bytes, budget.total.maxBytes)} of budget), ` +
        `${humanBytes(totals.gzipBytes)} gzip (${percentage(totals.gzipBytes, budget.total.maxGzipBytes)} of budget).`,
);

if (failures.length > 0) {
    console.error(`\n${failures.map((failure) => `- ${failure}`).join("\n")}`);
    process.exitCode = 1;
}
