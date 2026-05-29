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
 * MageAustralia_CategoryAttributes — category attribute grid container.
 */
class MageAustralia_CategoryAttributes_Block_Adminhtml_Attribute extends Mage_Adminhtml_Block_Widget_Grid_Container
{
    public function __construct()
    {
        $this->_controller = 'adminhtml_attribute';
        $this->_blockGroup = 'mageaustralia_categoryattributes';
        $this->_headerText = $this->__('Category Attributes');
        $this->_addButtonLabel = $this->__('Add New Category Attribute');
        parent::__construct();
    }
}
