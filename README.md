# Clean Admin UI for XenForo 2.3

Restricts XenForo's upgrade checks, and the "a new version is available" notices in the admin
control panel, to super administrators. Other administrators never see them. The scheduled daily
check still runs, so a super administrator is told about new versions as usual.

By [Simon Hampel](https://xenforo.com/community/members/sim.4264/).

## Requirements

XenForo 2.3.4 or later.

Versions before 2.0.0 also hid several dashboard panels — the environment report, file checks and
staff online among them. XenForo 2.3.4 controls those with admin permissions of its own, so this
add-on no longer touches them.
