<?php namespace Hampel\CleanAdmin\XF\Repository;

class UpgradeCheckRepository extends XFCP_UpgradeCheckRepository
{
    public function canCheckForUpgrades(&$error = null)
    {
        // Restrict only what the control panel shows. The scheduled check runs as a job, from
        // job.php or the CLI, where the visitor is whoever triggered it rather than an administrator.
        if (\XF::app() instanceof \XF\Admin\App && !\XF::visitor()->is_super_admin)
        {
            $error = \XF::phrase('do_not_have_permission');
            return false;
        }

        return parent::canCheckForUpgrades($error);
    }
}
