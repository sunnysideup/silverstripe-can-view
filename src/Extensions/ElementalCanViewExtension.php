<?php

namespace Sunnysideup\ElementalCanView\Extensions;

use Sunnysideup\ElementalCanView\Api\PermissionCanViewListMaker;

use SilverStripe\Forms\FieldList;
use DNADesign\Elemental\Models\BaseElement;
use SilverStripe\Forms\LiteralField;
use SilverStripe\ORM\DataExtension;
use SilverStripe\Security\Group;
use SilverStripe\Security\InheritedPermissions;
use SilverStripe\Security\InheritedPermissionsExtension;
use SilverStripe\Security\Member;
use SilverStripe\Security\Permission;
use SilverStripe\Security\PermissionChecker;
use SilverStripe\Security\PermissionProvider;
use SilverStripe\Security\Security;

class ElementalCanViewExtension extends DataExtension
{
    private static $db = [
        'CanViewType' => "Enum('".
            InheritedPermissions::ANYONE.", ".
            InheritedPermissions::LOGGED_IN_USERS.", ".
            InheritedPermissions::ONLY_THESE_USERS."', '".
            InheritedPermissions::ANYONE.
        "')",
    ];

    private static $many_many = [
        'ViewerGroups' => Group::class,
    ];

    private static $defaults = [
        'CanViewType' => InheritedPermissions::ANYONE,
    ];

    public function canView($member, $content = [])
    {
        if (!$member) {
            $member = Security::getCurrentUser();
        }

        // admin override
        if ($member && Permission::checkMember($member, ["ADMIN", "SITETREE_VIEW_ALL"])) {
            return true;
        }

        // if there is no meaningfull response go back to actual element itself!
        if (!$this->CanViewType || $this->CanViewType === InheritedPermissions::ANYONE) {
            return null;
        }

        // check for any logged-in users
        if ($this->CanViewType === InheritedPermissions::LOGGED_IN_USERS) {
            if(! ($member && $member->ID)) {
                return false;
            }
        }

        // check for specific groups
        if ($this->CanViewType === InheritedPermissions::ONLY_THESE_USERS) {
            if(! ($member && $member->inGroups($this->ViewerGroups()))) {
                return false;
            }
        }

        //important - return back to actual element
        return null;
    }

    public function updateCMSFields(FieldList $fields)
    {
        $owner = $this->owner;
        $viewAllGroupsMap = PermissionCanViewListMaker::get_list(Permission::get_groups_by_permission(['SITETREE_VIEW_ALL', 'ADMIN']));
        $fields->fieldsToTab(
            'Root.Permissions',
            [
                $viewersOptionsField = new OptionsetField(
                    "CanViewType",
                    _t(__CLASS__.'.ACCESSHEADER', "Who can view this page?")
                ),
                $viewerGroupsField = TreeMultiselectField::create(
                    "ViewerGroups",
                    _t(__CLASS__.'.VIEWERGROUPS', "Viewer Groups"),
                    Group::class
                ),
            ]
        );

        $viewersOptionsSource = [
            InheritedPermissions::ANYONE => _t(__CLASS__.'.ACCESSANYONEWITHPAGEACCESS', "Anyone who can view the page"),
            InheritedPermissions::LOGGED_IN_USERS => _t(__CLASS__.'.ACCESSLOGGEDIN', "Logged-in users"),
            InheritedPermissions::ONLY_THESE_USERS => _t(
                __CLASS__.'.ACCESSONLYTHESE',
                "Only these groups (choose from list)"
            ),
        ];
        $viewersOptionsField->setSource($viewersOptionsSource);

        if ($viewAllGroupsMap) {
            $viewerGroupsField->setDescription(_t(
                __CLASS__.'.VIEWER_GROUPS_FIELD_DESC',
                'Groups with global view permissions: {groupList}',
                ['groupList' => implode(', ', array_values($viewAllGroupsMap))]
            ));
        }

        if (!Permission::check('SITETREE_GRANT_ACCESS')) {
            $fields->makeFieldReadonly($viewersOptionsField);
            if ($this->CanEditType === InheritedPermissions::ONLY_THESE_USERS) {
                $fields->makeFieldReadonly($viewerGroupsField);
            } else {
                $fields->removeByName('ViewerGroups');
            }

        }

        return $fields;
    }
}
