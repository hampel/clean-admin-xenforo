<?php namespace Hampel\CleanAdmin\XF\Repository;

class UpgradeCheckRepository extends XFCP_UpgradeCheckRepository
{
    public function canCheckForUpgrades(&$error = null)
    {
        if (\XF::visitor()->is_super_admin)
        {
            return parent::canCheckForUpgrades($error);
        }

        return false;
    }
}
