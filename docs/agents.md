# AI assistance

Use the package-owned [php-forge-debug-core](../skills/php-forge-debug-core/SKILL.md) skill for integrating PHP Forge debugger snapshots, storage and adapter boundaries. The canonical source is `skills/`; checkout discovery links in `.agents/skills/` and `.claude/skills/` point to that same source.

## Install in an application

### Automatic Composer installation

Composer is the primary distribution path. `extra.skills.source` declares the bundle; installing the package alone does
not register its skill at the application root. In a consumer already configured for `llm/skills`, explicitly trust
`php-forge/debug-core` and use that installed synchronizer's documented setup. Do not enable plugins or broaden trust by
copying an example.

```bash
composer install
composer skills:show
```

With verified auto-sync enabled, installation/update can synchronize the bundle; otherwise run
`composer skills:update` and inspect `composer skills:show`. Composer exit zero is not proof of client activation.

### Optional npx installation

An optional pinned installer can use a local checkout or an installed package that contains the bundle:

```bash
npx skills@1.7.2 add /absolute/path/to/debug-core --list
npx skills@1.7.2 add /absolute/path/to/debug-core --skill php-forge-debug-core --agent claude-code codex opencode
```

Keep project scope. Inspect a remote source with `--list` before claiming an unpublished skill is available there.
Copied installations must be refreshed after upgrades. The complete skill directory is the unit of installation.

## Discover and invoke

Open a fresh client session at the checkout or consumer root. Codex discovers `.agents/skills/php-forge-debug-core/` and supports
`$php-forge-debug-core` or its skill picker. Claude Code uses `.claude/skills/php-forge-debug-core/` and `/php-forge-debug-core`. For OpenCode, verify the
installed client's native skill tool and shared paths. Do not assume one client's invocation syntax works in another.

## Example prompts

Explicit Codex invocation:

```text
$php-forge-debug-core
Outline an adapter using the installed snapshot and routing contracts. Verify missing/corrupt snapshot handling and request isolation without changing production routes or exposing diagnostics.
```

For natural selection, repeat the task without the first line. Record whether the client loads this skill rather than
a global same-named copy. As an unrelated negative control, ask it to explain HTTP 301 versus 302 without inspecting the
repository or loading package skills.

## Verify the result

Capture the native load/tool evidence and selected source path, then execute the generated task checks against the
installed package. Cover the normal operation, the described boundary case and a failure/unsupported input. Inspect
generated code before executing it; use disposable fixtures for writes. Run the package's actual non-mutating checks
and report runtime versions, warnings and unavailable dependencies. Listing, manually reading the skill, or supplying
its body in a prompt does not prove native activation or functional execution.

[Back to README](../README.md)
