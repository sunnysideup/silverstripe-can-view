<?php

namespace Sunnysideup\ElementalSwitchTabs\Extensions;

use SilverStripe\Forms\LiteralField;
use SilverStripe\ORM\DataExtension;

use DNADesign\Elemental\Models\BaseElement;

class ElementalSwitchTabsExtension extends DataExtension
{
    public function getLinksField(string $nameOfTab, string $label)
    {
        return LiteralField::create(
            'LinkToLink' . $nameOfTab,
            '<a href="#" onclick="' . $this->getJsFoTabSwitch($nameOfTab) . '">' . $label . '</a>'
        );
    }

    protected function getJsFoTabSwitch(string $nameOfTab): string
    {
        return <<<js
        if(jQuery(this).closest('div.element-editor__element').length > 0) {
            jQuery(this)
                .closest('div.element-editor__element')
                .find('button[name=\\'{$nameOfTab}\\']')
                .click();
        } else {
            jQuery('li[aria-controls=\\'Root_{$nameOfTab}\\'] a').click();
        }
        return false;
js;
    }


    /**
     * @return BaseElement|null
     */
    public function PreviousBlock()
    {
        if($this->exists()) {
            $parent = $this->getOwner()->Parent();
            if($parent) {
                return BaseElement::get()
                    ->filter(['Sort:LessThanOrEqual' => $this->Sort])
                    ->exclude(['ID' => $this->ID])
                    ->sort(['Sort' => 'ASC'])
                    ->last();
            }
        }
    }

    /**
     * @return BaseElement|null
     */
    public function NextBlock()
    {
        if($this->exists()) {
            $parent = $this->getOwner()->Parent();
            if($parent) {
                return BaseElement::get()
                    ->filter(['Sort:GreaterThanOrEqual' => $this->Sort])
                    ->exclude(['ID' => $this->ID])
                    ->sort(['Sort' => 'ASC'])
                    ->first();
            }
        }
    }
}
