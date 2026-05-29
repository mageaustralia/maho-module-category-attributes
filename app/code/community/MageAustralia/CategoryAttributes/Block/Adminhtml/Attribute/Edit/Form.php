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
 * MageAustralia_CategoryAttributes — attribute add / edit form.
 *
 * Ports Delta_Deltacats' Edit/Tab/Form + Grouptitle into a single fieldset, and
 * ADDS the select / multiselect / yes-no input types DeltaCats lacked. The
 * "Manage Options" grid (for select/multiselect) is rendered by an attached
 * child template, modelled on Mageaustralia_AttributeManager's options grid
 * (vanilla JS, escapeHtml only).
 */
class MageAustralia_CategoryAttributes_Block_Adminhtml_Attribute_Edit_Form extends Mage_Adminhtml_Block_Widget_Form
{
    public function getAttribute(): ?Mage_Eav_Model_Entity_Attribute
    {
        $attribute = Mage::registry('current_category_attribute');
        return $attribute instanceof Mage_Eav_Model_Entity_Attribute ? $attribute : null;
    }

    #[\Override]
    protected function _prepareForm()
    {
        /** @var MageAustralia_CategoryAttributes_Helper_Data $helper */
        $helper = Mage::helper('mageaustralia_categoryattributes');
        $attribute = $this->getAttribute();
        $isEdit = $attribute && $attribute->getId();

        $form = new Varien_Data_Form([
            'id'     => 'edit_form',
            'action' => $this->getUrl('*/*/save', $isEdit ? ['id' => $attribute->getId()] : []),
            'method' => 'post',
        ]);
        $form->setUseContainer(true);

        $fieldset = $form->addFieldset('base_fieldset', [
            'legend' => $this->__('Attribute Properties'),
        ]);

        $codeConfig = [
            'name'     => 'attribute_code',
            'label'    => $this->__('Attribute Code'),
            'title'    => $this->__('Attribute Code'),
            'required' => !$isEdit,
            'class'    => 'validate-code',
            'note'     => $this->__('Lowercase letters, numbers and underscores only; must start with a letter. Max 30 chars. Cannot be changed after creation.'),
        ];
        // Only lock the code when EDITING an existing attribute. Passing
        // 'readonly' => false still renders the readonly attribute on the field,
        // which would make the New form's code uneditable, so omit the key entirely.
        if ($isEdit) {
            $codeConfig['readonly'] = true;
        }
        $fieldset->addField('attribute_code', 'text', $codeConfig);

        $fieldset->addField('frontend_label', 'text', [
            'name'     => 'frontend_label',
            'label'    => $this->__('Title'),
            'title'    => $this->__('Title'),
            'required' => true,
            'class'    => 'required-entry',
            'note'     => $this->__('The label shown on the category edit page.'),
        ]);

        $inputField = $fieldset->addField('frontend_input', 'select', [
            'name'   => 'frontend_input',
            'label'  => $this->__('Catalog Input Type'),
            'title'  => $this->__('Catalog Input Type'),
            'values' => $helper->getInputTypeOptions(),
            'value'  => 'text',
            'note'   => $this->__('How admins enter the value on the category edit page.'),
        ]);
        if ($isEdit) {
            // Input type can't change after creation (backend/source are fixed).
            $inputField->setReadonly(true, true);
        }

        $fieldset->addField('is_required', 'select', [
            'name'   => 'is_required',
            'label'  => $this->__('Values Required'),
            'title'  => $this->__('Values Required'),
            'values' => $this->_yesNo(),
        ]);

        $fieldset->addField('is_unique', 'select', [
            'name'   => 'is_unique',
            'label'  => $this->__('Unique Value'),
            'title'  => $this->__('Unique Value'),
            'values' => $this->_yesNo(),
            'note'   => $this->__('Not shared with other categories.'),
        ]);

        $fieldset->addField('frontend_class', 'select', [
            'name'   => 'frontend_class',
            'label'  => $this->__('Input Validation'),
            'title'  => $this->__('Input Validation'),
            'values' => [
                ['value' => '', 'label' => $this->__('None')],
                ['value' => 'validate-number', 'label' => $this->__('Decimal Number')],
                ['value' => 'validate-digits', 'label' => $this->__('Integer Number')],
                ['value' => 'validate-email', 'label' => $this->__('Email')],
                ['value' => 'validate-url', 'label' => $this->__('URL')],
                ['value' => 'validate-alpha', 'label' => $this->__('Letters')],
                ['value' => 'validate-alphanum', 'label' => $this->__('Letters (a-z, A-Z) or Numbers (0-9)')],
            ],
        ]);

        $fieldset->addField('note', 'text', [
            'name'  => 'note',
            'label' => $this->__('Note / Comment'),
            'title' => $this->__('Note / Comment'),
            'note'  => $this->__('Optional help text shown under the field.'),
        ]);

        $fieldset->addField('attribute_group_name', 'select', [
            'name'     => 'attribute_group_name',
            'label'    => $this->__('Tab'),
            'title'    => $this->__('Tab'),
            'required' => true,
            'class'    => 'required-entry',
            'values'   => $helper->getGroupOptions(),
            'note'     => $this->__('The category edit-page tab (attribute group) this attribute appears under.'),
        ]);

        $fieldset->addField('sort_order', 'text', [
            'name'  => 'sort_order',
            'label' => $this->__('Sort Order'),
            'title' => $this->__('Sort Order'),
            'class' => 'validate-digits',
            'value' => $isEdit ? '' : 0,
        ]);

        // Manage Options grid for select/multiselect — rendered by a child block.
        $optionsHtml = $this->getLayout()
            ->createBlock('mageaustralia_categoryattributes/adminhtml_attribute_edit_options')
            ->toHtml();
        $fieldset->addField('manage_options', 'note', [
            'label' => $this->__('Manage Options'),
            'text'  => $optionsHtml,
        ]);

        // Populate values on edit.
        if ($isEdit) {
            $form->addValues([
                'attribute_code'       => $attribute->getAttributeCode(),
                'frontend_label'       => $attribute->getFrontendLabel(),
                'frontend_input'       => $attribute->getFrontendInput(),
                'is_required'          => (int) $attribute->getIsRequired(),
                'is_unique'            => (int) $attribute->getIsUnique(),
                'frontend_class'       => (string) $attribute->getFrontendClass(),
                'note'                 => (string) $attribute->getNote(),
                'sort_order'           => (int) $attribute->getSortOrder(),
                'attribute_group_name' => $this->_currentGroupName($attribute),
            ]);
        }

        $this->setForm($form);
        return parent::_prepareForm();
    }

    /**
     * @return array<int,array{value:int,label:string}>
     */
    private function _yesNo(): array
    {
        return [
            ['value' => 1, 'label' => $this->__('Yes')],
            ['value' => 0, 'label' => $this->__('No')],
        ];
    }

    private function _currentGroupName(Mage_Eav_Model_Entity_Attribute $attribute): string
    {
        /** @var MageAustralia_CategoryAttributes_Helper_Data $helper */
        $helper = Mage::helper('mageaustralia_categoryattributes');
        $resource = Mage::getSingleton('core/resource');
        $read = $resource->getConnection('core_read');
        $select = $read->select()
            ->from(['eea' => $resource->getTableName('eav_entity_attribute')], [])
            ->join(
                ['eag' => $resource->getTableName('eav_attribute_group')],
                'eag.attribute_group_id = eea.attribute_group_id',
                ['attribute_group_name'],
            )
            ->where('eea.attribute_id = ?', (int) $attribute->getId())
            ->where('eea.attribute_set_id = ?', $helper->getDefaultAttributeSetId())
            ->limit(1);
        return (string) $read->fetchOne($select);
    }
}
