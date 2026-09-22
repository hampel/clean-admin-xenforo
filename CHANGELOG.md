# Changelog

## 2.0.1 (2026-09-22)

- bugfix: the scheduled upgrade check never contacted XenForo unless a super administrator happened
  to trigger the job queue — the super-administrator restriction now applies only inside the admin
  control panel
- refusing *Check for upgrades* to an administrator now shows a permission error, instead of a
  message blaming the board's configuration

## 2.0.0 (2025-09-22)

- requires XenForo 2.3.4 or later
- removed the admin permissions, template modifications and navigation change that hid dashboard
  panels and *Checks and tests* — XenForo 2.3.4 controls these with its own admin permissions
- upgrade checks and upgrade notices are shown to super administrators only
- clean up files removed by an upgrade, on XenForo 2.3 or later

## 1.0.0 (2024-10-16)

- first production version
