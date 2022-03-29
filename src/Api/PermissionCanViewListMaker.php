<?php

namespace Sunnysideup\ElementalCanView\Api;

use SilverStripe\Security\Permission;

class PermissionCanViewListMaker
{
    public static function get_list(): array
    {
        $mapFn = function ($groups = []) {
            $map = [];
            foreach ($groups as $group) {
                // Listboxfield values are escaped, use ASCII char instead of &raquo;
                $map[$group->ID] = $group->getBreadcrumbs(' > ');
            }

            asort($map);

            return $map;
        };

        return $mapFn(Permission::get_groups_by_permission(['SITETREE_VIEW_ALL', 'ADMIN']));
    }
}
