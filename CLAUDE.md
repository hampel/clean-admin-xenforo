# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## What this add-on is

**One class extension, and nothing else.** It restricts XenForo's upgrade check to super
administrators, so an ordinary administrator never sees the "a new version is available" notices
in the admin control panel.

The entire surface is:

- `XF/Repository/UpgradeCheckRepository.php` — overrides `canCheckForUpgrades()` to return
  `false` inside the admin control panel unless `\XF::visitor()->is_super_admin`, deferring to the
  parent everywhere else.
- `_output/class_extensions/…UpgradeCheckRepository.json` — registers the extension.
- `Setup.php` — no install, upgrade or uninstall steps; `postUpgrade()` only calls
  `enqueuePostUpgradeCleanUp()` on XF 2.3+.

## Requires XenForo 2.3.4, because core took over the rest

**Version 1.x did far more, and 2.0.0 deleted almost all of it.** It added three admin permissions
and seven template modifications to the admin index — hiding the environment report, file checks,
legacy config warning, staff online and stopped-job notices — plus an `AdminNavigation` extension
hiding *Checks and tests*. XF 2.3.4 gates those panels on admin permissions in core
(`XF\Admin\Controller\IndexController`, for example `hasAdminPermission('serverInfo')`), so the
add-on's versions became redundant and were removed.

That is why `addon.json` requires `2030470`. **Do not lower the floor without restoring what was
removed** — on an older XF the add-on would install and the panels it used to hide would be back.
The same floor makes the `>= 2030000` guard in `Setup::postUpgrade()` always true; it is harmless.

## The restriction is keyed on the admin app, not only on the visitor

**Core asks `canCheckForUpgrades()` in five places, and one of them is not a person looking at a
page.** The admin app itself, `IndexController` and `AddOnController` ask whether to show the
upgrade notice; `ToolsController` asks before a manual check and shows the `$error` argument when
refused. All four run in `XF\Admin\App`, with an administrator as the visitor.

The fifth is `XF\Job\UpgradeCheck`, the scheduled check, and it never runs in the admin app:
`job.php` sets up `XF\Pub\App`, cron runs `XF\Cli\App`, and the control panel's own job runner
runs manual jobs only. Its visitor is whoever triggered the run — the guest, from the CLI.

**So the override tests `\XF::app() instanceof \XF\Admin\App` before it tests the visitor.**
2.0.0 tested the visitor alone, and the scheduled check returned early on every run that a super
administrator did not happen to trigger, which on most forums is all of them. Do not simplify the
condition back to the visitor; that is the defect 2.0.1 fixes.

The refusal sets `$error` to the core `do_not_have_permission` phrase. Without it,
`ToolsController` falls back to a message blaming the board's configuration.

## Working on it

`cmd.php` resolves the install from its own location, not the working directory, so every XF
command works from this directory via the relative path:

```bash
php ../../../../cmd.php xf-dev:import --addon=Hampel/CleanAdmin   # after editing _output/
php ../../../../cmd.php xf-dev:class-lint                         # whole of core; does not check this add-on
php ../../../../cmd.php xf-addon:sync-json Hampel/CleanAdmin      # after editing addon.json
php ../../../../cmd.php xf:addon-upgrade Hampel/CleanAdmin        # apply a version bump
php ../../../../cmd.php xf-addon:build-release Hampel/CleanAdmin  # release only; writes _releases/
```

**Never run `xf-dev:export`** — it writes the database over `_output/`, and its `--addon` option
is optional, so omitting it exports every add-on in the install. `xf-addon:build-release` runs the
scoped `xf-addon:export` internally, which is safe.

**There is no test suite, deliberately** — no `phpunit.xml`, no `composer.json`, no `vendor/`.
`TESTING.md` carries what replaces it: a probe script that asks `canCheckForUpgrades()` under each
of the CLI, public and admin apps as three kinds of visitor, how to run one real scheduled check,
and the control panel checks that need a person.

## Versioning

**Open the development cycle before starting work on a released version**: bump to an alpha of the
next patch (`2.0.0` → `2000111` / `"2.0.1a1"`) and commit that on its own. Until it lands, the tree
and any zip built from it claim to be the last release, and XenForo names the release zip from the
version string alone and overwrites any existing file of that name.

Take the smallest bump that is not the last release. Raising later is free; lowering is not
possible, because `AddOn::canUpgrade()` requires a strictly greater `version_id`.

`CHANGELOG.md` is for stable releases only, so an open alpha correctly has no entry in it.

## Build and release

`build.json` has no `additional_files` and no Composer step — two `exec` lines that delete the
dev-only files from the upload tree and then move every remaining `*.md` out of it, so `README` and
`CHANGELOG` appear at the zip root rather than being uploaded to a user's server.

**Dev-only files are kept out of three places by three separate mechanisms**, and none covers the
others. The release builder walks the filesystem and knows nothing about git, so gitignoring a file
does not keep it out of the zip:

| kept out of | by | applies to |
|---|---|---|
| the git repository | `.gitignore` | `CLAUDE.local.md`, `_data/`, `_releases/` |
| `git archive` output (GitHub "Download ZIP") | `.gitattributes` `export-ignore` | `CLAUDE.md`, `CLAUDE.local.md`, `.gitignore`, `.gitattributes` |
| the XenForo release zip | `build.json` `exec` `rm -fv` | `CLAUDE.md`, `CLAUDE.local.md` |

**The `rm` must stay before the `mv`.** Placed after it, it silently does nothing: the file has
already moved to `_build/`, which is the zip root, and `-f` hides the miss. `exec` steps cannot
fail a build, so check the artefact rather than the exit code:

```bash
unzip -Z1 _releases/<file>.zip | grep -v '^upload/'        # zip root
unzip -Z1 _releases/<file>.zip | grep -iE 'claude|testing' # want no output
```

`CLAUDE.md` is committed and public. Anything specific to one machine — install paths, host names,
working notes — belongs in a gitignored `CLAUDE.local.md`. The test is whether it would still be
true for someone who cloned only this repository.

## Line endings

**`.gitattributes` pins `* text=auto eol=lf`, so the repository stores LF and checks LF back out on
every platform.** A Windows git client with `core.autocrlf=true` can still leave CRLF in a working
tree written before that rule existed, and `git status` no longer reports it. Do not work around
the rule, and do not commit a whole-tree diff in which every line changed with identical content
on both sides.
