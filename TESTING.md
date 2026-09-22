# Testing

Manual and scripted checks for Clean Admin UI. There is no PHPUnit suite, deliberately: the add-on
is one condition in one method, and the two things worth checking — which XenForo app is running,
and whether the scheduled check really reaches XenForo — are not things a unit suite can see.

## Surfaces

One class extension, and nothing else:

| type | from | to |
|---|---|---|
| class extension | `XF\Repository\UpgradeCheckRepository` | `Hampel\CleanAdmin\XF\Repository\UpgradeCheckRepository` |

It overrides `canCheckForUpgrades()`. There are no listeners, template modifications, templates,
phrases, options, permissions, routes or cron entries, and `Setup.php` has no install or upgrade
steps.

Core calls `canCheckForUpgrades()` from five places:

| caller | app | what it decides |
|---|---|---|
| `XF\Admin\App` | admin | whether the upgrade notice appears on every control panel page |
| `XF\Admin\Controller\IndexController` | admin | the notice on the control panel home page |
| `XF\Admin\Controller\AddOnController` | admin | the notice on the add-ons list |
| `XF\Admin\Controller\ToolsController` | admin | whether *Check for upgrades* may run, and the error shown if not |
| `XF\Job\UpgradeCheck` | public or CLI | whether the scheduled daily check contacts XenForo at all |

## Fragile points

1. **The condition tests the app before the visitor, and must keep doing so.** The job never runs
   in the admin app — `job.php` sets up the public app and cron uses the CLI app — and its visitor
   is whoever triggered the run. Testing the visitor alone refuses the scheduled check on nearly
   every run. 2.0.0 shipped that way; 2.0.1 fixes it.
2. **A future XenForo release could add a caller.** One in the admin app is restricted, which is
   probably intended. One elsewhere is allowed, which is safe. A background task moved *into* the
   admin app would be refused — re-read the caller list after a XenForo upgrade.
3. **The manual check's error depends on `$error` being set.** Without it, `ToolsController` shows
   a generic message blaming the board's configuration.

## Automated

**The matrix probe.** Save this outside the repository, then run it once per app from the install
root. It changes nothing.

```php
<?php
// probe.php — usage: php probe.php <install root> 'XF\Cli\App'
[, $dir, $appClass] = $argv;
require $dir . '/src/XF.php';
XF::start($dir);
$app = XF::setupApp($appClass);
$db = $app->db();
$cases = [
    'guest'       => 0,
    'super admin' => $db->fetchOne('SELECT user_id FROM xf_admin WHERE is_super_admin = 1 LIMIT 1'),
    'plain admin' => $db->fetchOne('SELECT user_id FROM xf_admin WHERE is_super_admin = 0 LIMIT 1'),
];
foreach ($cases as $label => $id)
{
    if ($id === false) { echo "$label: none on this board\n"; continue; }
    \XF::setVisitor($id ? $app->em()->find('XF:User', $id) : $app->repository('XF:User')->getGuestUser());
    $error = null;
    $allowed = $app->repository('XF:UpgradeCheck')->canCheckForUpgrades($error);
    printf("%-12s %-12s %-6s %s\n", $appClass, $label, var_export($allowed, true), (string) $error);
}
```

```bash
for a in 'XF\Cli\App' 'XF\Pub\App' 'XF\Admin\App'; do php probe.php "$PWD" "$a"; done
```

Expected: every row `true` for the CLI and public apps. In the admin app, `true` for the super
administrator only; the guest and the ordinary administrator get `false` with *You do not have
permission to view this page or perform this action.*

**One real check.** This contacts XenForo's servers, which is what the scheduled job exists to do.
It runs the job's own code from the CLI app, as cron would, without disturbing the job queue:

```php
$job = new \XF\Job\UpgradeCheck($app, 0, []);
$job->run(30);
```

Then read the newest row of `xf_upgrade_check`: a fresh `check_date` and an empty `error_code` is
a pass. **The table keeps 30 days of rows** (`pruneUpgradeChecks()`), so an empty table means no
check in that window, not that none has ever run.

## Needs a human

- **Log in to the control panel as an ordinary administrator.** No upgrade notice on any page, and
  *Tools > Check for upgrades* (`admin.php?tools/upgrade-check`) refuses with the permission
  message. Then as a super administrator: the notice appears when an update exists, and the manual
  check runs. A probe proves the method's answer, not what the page renders.
- **The upgrade from the last published release.** `Setup.php` has no version-gated steps, so the
  upgrade cannot fail on one, but installing the previous zip and upgrading needs a disposable
  XenForo 2.3.4+ install whose development mode is off — a development install imports from
  `_output/` instead of the release's data.
