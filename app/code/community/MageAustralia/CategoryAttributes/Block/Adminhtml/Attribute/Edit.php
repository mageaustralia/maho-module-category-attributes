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
 * MageAustralia_CategoryAttributes — attribute edit form container.
 */
class MageAustralia_CategoryAttributes_Block_Adminhtml_Attribute_Edit extends Mage_Adminhtml_Block_Widget_Form_Container
{
    public function __construct()
    {
        $this->_objectId = 'id';
        $this->_controller = 'adminhtml_attribute';
        $this->_blockGroup = 'mageaustralia_categoryattributes';
        parent::__construct();

        $this->_updateButton('save', 'label', $this->__('Save Attribute'));

        /** @var Mage_Eav_Model_Entity_Attribute|null $attribute */
        $attribute = Mage::registry('current_category_attribute');
        if ($attribute && $attribute->getId() && $attribute->getIsUserDefined()) {
            $this->_addButton('delete_attr', [
                'label'   => $this->__('Delete Attribute'),
                'class'   => 'delete',
                'onclick' => "deleteConfirm('"
                    . Mage::helper('core')->jsQuoteEscape($this->__('Are you sure you want to delete this category attribute?'))
                    . "', '"
                    . $this->getAttributeDeleteUrl($attribute)
                    . "')",
            ], 0, 100);
        } else {
            $this->_removeButton('delete');
        }
    }

    public function getAttributeDeleteUrl(Mage_Eav_Model_Entity_Attribute $attribute): string
    {
        return $this->getUrl('*/*/delete', [
            'id'       => $attribute->getId(),
            'form_key' => Mage::getSingleton('core/session')->getFormKey(),
        ]);
    }

    #[\Override]
    public function getHeaderText()
    {
        /** @var Mage_Eav_Model_Entity_Attribute|null $attribute */
        $attribute = Mage::registry('current_category_attribute');
        if ($attribute && $attribute->getId()) {
            return $this->__('Edit Category Attribute "%s"', $this->escapeHtml($attribute->getAttributeCode()));
        }
        return $this->__('New Category Attribute');
    }
}
