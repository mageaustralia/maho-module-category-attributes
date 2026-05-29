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
 * MageAustralia_CategoryAttributes — category attribute grid.
 *
 * Lists every catalog_category EAV attribute, joined to its attribute group
 * (the category edit-page tab) via eav_entity_attribute + eav_attribute_group,
 * exactly as the legacy Delta_Deltacats grid did. Columns: code, title, tab,
 * input type, user-defined. Row click opens the edit form.
 */
class MageAustralia_CategoryAttributes_Block_Adminhtml_Attribute_Grid extends Mage_Adminhtml_Block_Widget_Grid
{
    public function __construct()
    {
        parent::__construct();
        $this->setId('mageaustralia_categoryattributes_grid');
        $this->setDefaultSort('attribute_code');
        $this->setDefaultDir('asc');
        $this->setSaveParametersInSession(true);
        $this->setUseAjax(false);
    }

    #[\Override]
    protected function _prepareCollection()
    {
        /** @var MageAustralia_CategoryAttributes_Helper_Data $helper */
        $helper = Mage::helper('mageaustralia_categoryattributes');
        $resource = Mage::getSingleton('core/resource');
        $eea = $resource->getTableName('eav_entity_attribute');
        $eag = $resource->getTableName('eav_attribute_group');

        /** @var Mage_Eav_Model_Resource_Entity_Attribute_Collection $collection */
        $collection = Mage::getResourceModel('eav/entity_attribute_collection')
            ->setEntityTypeFilter($helper->getEntityTypeId());

        // Join the group assignment for the default attribute set so the Tab
        // column reflects where the attribute renders on the category edit page.
        // LEFT join + DISTINCT keeps attributes that aren't yet assigned to the
        // set visible (Tab shows blank for those).
        $setId = $helper->getDefaultAttributeSetId();
        $collection->getSelect()
            ->joinLeft(
                ['eea' => $eea],
                'eea.attribute_id = main_table.attribute_id AND eea.attribute_set_id = ' . (int) $setId,
                ['attribute_group_id'],
            )
            ->joinLeft(
                ['eag' => $eag],
                'eag.attribute_group_id = eea.attribute_group_id',
                ['attribute_group_name'],
            )
            ->group('main_table.attribute_id');

        $this->setCollection($collection);
        return parent::_prepareCollection();
    }

    #[\Override]
    protected function _prepareColumns()
    {
        $this->addColumn('attribute_code', [
            'header' => $this->__('Attribute Code'),
            'index'  => 'attribute_code',
            'align'  => 'left',
        ]);

        $this->addColumn('frontend_label', [
            'header' => $this->__('Title'),
            'index'  => 'frontend_label',
            'align'  => 'left',
        ]);

        $this->addColumn('attribute_group_name', [
            'header'   => $this->__('Tab'),
            'index'    => 'attribute_group_name',
            'align'    => 'left',
            'sortable' => false,
        ]);

        $this->addColumn('frontend_input', [
            'header' => $this->__('Input Type'),
            'index'  => 'frontend_input',
            'align'  => 'left',
            'width'  => '120px',
        ]);

        $this->addColumn('is_user_defined', [
            'header'  => $this->__('User Defined'),
            'index'   => 'is_user_defined',
            'type'    => 'options',
            'options' => [0 => $this->__('No'), 1 => $this->__('Yes')],
            'align'   => 'center',
            'width'   => '90px',
        ]);

        $this->addColumn('action', [
            'header'    => $this->__('Action'),
            'type'      => 'action',
            'getter'    => 'getId',
            'filter'    => false,
            'sortable'  => false,
            'width'     => '80px',
            'actions'   => [
                [
                    'caption' => $this->__('Edit'),
                    'url'     => ['base' => '*/*/edit'],
                    'field'   => 'id',
                ],
            ],
        ]);

        return parent::_prepareColumns();
    }

    #[\Override]
    public function getRowUrl($row)
    {
        return $this->getUrl('*/*/edit', ['id' => $row->getId()]);
    }
}
