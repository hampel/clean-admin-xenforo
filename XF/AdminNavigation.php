<?php namespace Hampel\CleanAdmin\XF;

class AdminNavigation extends XFCP_AdminNavigation
{
    protected function setupFilteredRecurse($rootId, $depth, array $map, array &$filtered)
    {
        if ($rootId == 'checksAndTests' && !$this->visitor->hasAdminPermission('cleanadminChecksTests'))
        {
            return [];
        }
        return parent::setupFilteredRecurse($rootId, $depth, $map, $filtered);
    }
}
