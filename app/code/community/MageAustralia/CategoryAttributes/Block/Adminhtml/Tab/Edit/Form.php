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
 * MageAustralia_CategoryAttributes — tab (attribute group) add / edit form.
 */
class MageAustralia_CategoryAttributes_Block_Adminhtml_Tab_Edit_Form extends Mage_Adminhtml_Block_Widget_Form
{
    #[\Override]
    protected function _prepareForm()
    {
        /** @var Mage_Eav_Model_Entity_Attribute_Group|null $group */
        $group = Mage::registry('current_category_tab');
        $isEdit = $group && $group->getId();

        $form = new Varien_Data_Form([
            'id'     => 'edit_form',
            'action' => $this->getUrl('*/*/save', $isEdit ? ['id' => $group->getId()] : []),
            'method' => 'post',
        ]);
        $form->setUseContainer(true);

        $fieldset = $form->addFieldset('base_fieldset', [
            'legend' => $this->__('Tab Properties'),
        ]);

        $fieldset->addField('attribute_group_name', 'text', [
            'name'     => 'attribute_group_name',
            'label'    => $this->__('Title'),
            'title'    => $this->__('Title'),
            'required' => true,
            'class'    => 'required-entry',
            'note'     => $this->__('The tab title shown on the category edit page.'),
        ]);

        $fieldset->addField('sort_order', 'text', [
            'name'  => 'sort_order',
            'label' => $this->__('Position'),
            'title' => $this->__('Position'),
            'class' => 'validate-digits',
            'value' => $isEdit ? '' : 0,
            'note'  => $this->__('Lower numbers appear first.'),
        ]);

        if ($isEdit) {
            $form->addValues([
                'attribute_group_name' => $group->getAttributeGroupName(),
                'sort_order'           => (int) $group->getSortOrder(),
            ]);
        }

        $this->setForm($form);
        return parent::_prepareForm();
    }
}
