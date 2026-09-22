# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## What this add-on is

**One class extension, and nothing else.** It restricts XenForo's upgrade check to super
administrators, so an ordinary administrator never sees the "a new version is available" notices
in the admin control panel.

The entire surface is:

- `XF/Repository/UpgradeCheckRepository.php` — overrides `canCheckForUpgrades()` to return
  `false` unless `\XF::visitor()->is_super_admin`, deferring to the parent otherwise.
- `_output/class_extensions/…UpgradeCheckRepository.json` — registers the extension.
- `Setup.php` — no install, upgrade or uninstall steps; `postUpgrade()` only calls
  `enqueuePostUpgradeCleanUp()` on XF 2.3+.

The other `_output/` directories (`admin_permissions`, `phrases`, `template_modifications`) hold
only an empty `_metadata.json`. They are left over from 1.x, not placeholders for something
pending.

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

## Every caller of `canCheckForUpgrades()` sees the visitor, including the background job

The override is visitor-based, and core asks the question in five places, not only where a human
is looking:

- `XF\Admin\App`, `XF\Admin\Controller\IndexController` and `AddOnController` — whether to show
  the notice. The intended effect.
- `XF\Admin\Controller\ToolsController` — the manual *Check for upgrades* action, which reports the
  `$error` argument. The override returns `false` without setting `$error`, so a non-super admin
  gets no explanation.
- **`XF\Job\UpgradeCheck::performUpgradeCheck()`** — the scheduled check. A job's visitor is
  whoever triggered the job run, which is usually not a super admin, and when it is not the job
  returns without checking and reschedules itself a day or more out.

Treat the last one as the thing to reason about before changing the condition. Keying on the
visitor is correct for display and wrong for a job; distinguishing them means checking context
rather than widening who counts as allowed.

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

There is no test suite: no `phpunit.xml`, no `composer.json`, no `vendor/`. Verification is by
hand in the admin control panel, logged in once as a super administrator and once as an ordinary
one.

## Versioning

**Open the development cycle before starting work on a released version**: bump to an alpha of the
next patch (`2.0.0` → `2000111` / `"2.0.1a1"`) and commit that on its own. Until it lands, the tree
and any zip built from it claim to be the last release, and XenForo names the release zip from the
version string alone and overwrites any existing file of that name.

Take the smallest bump that is not the last release. Raising later is free; lowering is not
possible, because `AddOn::canUpgrade()` requires a strictly greater `version_id`.

`CHANGELOG.md` is for stable releases only. It currently stops at 1.0.0 and has no entry for 2.0.0.

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
