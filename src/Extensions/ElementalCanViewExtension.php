<?php

namespace Sunnysideup\ElementalCanView\Extensions;

use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\OptionsetField;
use SilverStripe\Forms\TreeMultiselectField;
use SilverStripe\ORM\DataExtension;
use SilverStripe\Security\Group;
use SilverStripe\Security\InheritedPermissions;
use SilverStripe\Security\Permission;
use SilverStripe\Security\Security;
use Sunnysideup\ElementalCanView\Api\PermissionCanViewListMaker;

class ElementalCanViewExtension extends DataExtension
{

    private const NOT_LOGGED_IN_USERS = 'NotLoggedInUsers'

    private static $db = [
        'CanViewType' => "Enum('" .
            InheritedPermissions::ANYONE . ', ' .
            InheritedPermissions::NOT_LOGGED_IN_USERS . ', ' .
            InheritedPermissions::LOGGED_IN_USERS . ', ' .
            InheritedPermissions::ONLY_THESE_USERS . "', '" .
            InheritedPermissions::ANYONE .
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
        $owner = $this->getOwner();
        if (! $member) {
            $member = Security::getCurrentUser();
        }

        // admin override
        if ($member && Permission::checkMember($member, ['ADMIN', 'SITETREE_VIEW_ALL'])) {
            return true;
        }

        // if there is no meaningfull response go back to actual element itself!
        if (! $owner->CanViewType || InheritedPermissions::ANYONE === $owner->CanViewType) {
            return null;
        }

        // check for any  NOT logged-in users
        if (InheritedPermissions::NOT_LOGGED_IN_USERS === $owner->CanViewType) {
            if ($member && $member->ID) {
                return false;
            }
        }
        // check for any logged-in users
        if (InheritedPermissions::LOGGED_IN_USERS === $owner->CanViewType) {
            if (! ($member && $member->ID)) {
                return false;
            }
        }

        // check for specific groups
        if (InheritedPermissions::ONLY_THESE_USERS === $owner->CanViewType) {
            if (! ($member && $member->inGroups($owner->ViewerGroups()))) {
                return false;
            }
        }

        //important - return back to actual element
        return null;
    }

    public function updateCMSFields(FieldList $fields)
    {
        $owner = $this->getOwner();
        $viewAllGroupsMap = PermissionCanViewListMaker::get_list();
        $fields->addFieldsToTab(
            'Root.Permissions',
            [
                $viewersOptionsField = new OptionsetField(
                    'CanViewType',
                    _t(__CLASS__ . '.ACCESSHEADER', 'Who can view this elemental block?')
                ),
                $viewerGroupsField = TreeMultiselectField::create(
                    'ViewerGroups',
                    _t(__CLASS__ . '.VIEWERGROUPS', 'Viewer Groups'),
                    Group::class
                ),
            ]
        );

        $viewersOptionsSource = [
            InheritedPermissions::ANYONE => _t(__CLASS__ . '.ACCESSANYONEWITHPAGEACCESS', 'Anyone who can view the parent page'),
            InheritedPermissions::LOGGED_IN_USERS => _t(__CLASS__ . '.ACCESSLOGGEDIN', 'Logged-in users'),
            InheritedPermissions::ONLY_THESE_USERS => _t(
                __CLASS__ . '.ACCESSONLYTHESE',
                'Only these groups (choose from list)'
            ),
        ];
        $viewersOptionsField->setSource($viewersOptionsSource);

        if ($viewAllGroupsMap) {
            $viewerGroupsField->setDescription(_t(
                __CLASS__ . '.VIEWER_GROUPS_FIELD_DESC',
                'Groups with global view permissions: {groupList}',
                ['groupList' => implode(', ', array_values($viewAllGroupsMap))]
            ));
        }

        if (! Permission::check('SITETREE_GRANT_ACCESS')) {
            $fields->makeFieldReadonly($viewersOptionsField);
            if (InheritedPermissions::ONLY_THESE_USERS === $owner->CanEditType) {
                $fields->makeFieldReadonly($viewerGroupsField);
            } else {
                $fields->removeByName('ViewerGroups');
            }
        }

        return $fields;
    }
}
