<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @package    MageAustralia_CategoryAttributes
 * @copyright  Copyright (c) 2026 Maho Australia
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * MageAustralia_CategoryAttributes — category tab (attribute group) grid container.
 */
class MageAustralia_CategoryAttributes_Block_Adminhtml_Tab extends Mage_Adminhtml_Block_Widget_Grid_Container
{
    public function __construct()
    {
        $this->_controller = 'adminhtml_tab';
        $this->_blockGroup = 'mageaustralia_categoryattributes';
        $this->_headerText = $this->__('Category Attribute Tabs');
        $this->_addButtonLabel = $this->__('Add New Tab');
        parent::__construct();
    }
}
