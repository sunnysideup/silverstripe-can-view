<?php

namespace Sunnysideup\ElementalSwitchTabs\Extensions;

use DNADesign\Elemental\Models\BaseElement;
use SilverStripe\Forms\LiteralField;
use SilverStripe\ORM\DataExtension;
use SilverStripe\Security\InheritedPermissionsExtension;
use SilverStripe\Security\Group;
use SilverStripe\Security\InheritedPermissions;
use SilverStripe\Security\InheritedPermissionsExtension;
use SilverStripe\Security\Member;
use SilverStripe\Security\Permission;
use SilverStripe\Security\PermissionChecker;
use SilverStripe\Security\PermissionProvider;
use SilverStripe\Security\Security;

class ElementalCanView extends DataExtension
{
    private static $db = [
        'CanViewType' => "Enum('Anyone, LoggedInUsers, OnlyTheseUsers, Inherit', 'Inherit')",
    ];

    private static $many_many = [
        'ViewerGroups' => Group::class,
    ];

    private static $defaults = [
        'CanViewType' => InheritedPermissions::INHERIT,
    ];

    public function canView($member, $content = [])
    {
        if (!$member) {
            $member = Security::getCurrentUser();
        }

        // Standard mechanism for accepting permission changes from extensions
        $extended = $this->extendedCan('canView', $member);
        if ($extended !== null) {
            return $extended;
        }

        // admin override
        if ($member && Permission::checkMember($member, ["ADMIN", "SITETREE_VIEW_ALL"])) {
            return true;
        }

        // Orphaned pages (in the current stage) are unavailable, except for admins via the CMS
        if ($this->isOrphaned()) {
            return false;
        }

        // Note: getInheritedPermissions() is disused in this instance
        // to allow parent canView extensions to influence subpage canView()

        // check for empty spec
        if (!$this->CanViewType || $this->CanViewType === InheritedPermissions::ANYONE) {
            return true;
        }

        // check for inherit
        if ($this->CanViewType === InheritedPermissions::INHERIT) {
            if ($this->ParentID) {
                return $this->Parent()->canView($member);
            } else {
                return $this->getSiteConfig()->canViewPages($member);
            }
        }

        // check for any logged-in users
        if ($this->CanViewType === InheritedPermissions::LOGGED_IN_USERS && $member && $member->ID) {
            return true;
        }

        // check for specific groups
        if ($this->CanViewType === InheritedPermissions::ONLY_THESE_USERS
            && $member
            && $member->inGroups($this->ViewerGroups())
        ) {
            return true;
        }

        return false;
    }
}
