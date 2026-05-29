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
 * MageAustralia_CategoryAttributes — category tabs (attribute groups) grid.
 *
 * Lists the category default attribute set's groups (Title = attribute_group_name,
 * Position = sort_order, plus a live attribute count). Row click opens edit.
 */
class MageAustralia_CategoryAttributes_Block_Adminhtml_Tab_Grid extends Mage_Adminhtml_Block_Widget_Grid
{
    public function __construct()
    {
        parent::__construct();
        $this->setId('mageaustralia_categoryattributes_tab_grid');
        $this->setDefaultSort('sort_order');
        $this->setDefaultDir('asc');
        $this->setSaveParametersInSession(true);
        $this->setUseAjax(false);
    }

    #[\Override]
    protected function _prepareCollection()
    {
        /** @var MageAustralia_CategoryAttributes_Helper_Data $helper */
        $helper = Mage::helper('mageaustralia_categoryattributes');
        $collection = $helper->getGroupCollection();
        $this->setCollection($collection);
        return parent::_prepareCollection();
    }

    #[\Override]
    protected function _afterLoadCollection()
    {
        /** @var MageAustralia_CategoryAttributes_Helper_Data $helper */
        $helper = Mage::helper('mageaustralia_categoryattributes');
        foreach ($this->getCollection() as $group) {
            $group->setData('attribute_count', $helper->getGroupAttributeCount((int) $group->getId()));
            $group->setData('is_protected', $helper->isProtectedGroupName((string) $group->getAttributeGroupName()) ? 1 : 0);
        }
        return parent::_afterLoadCollection();
    }

    #[\Override]
    protected function _prepareColumns()
    {
        $this->addColumn('attribute_group_id', [
            'header' => $this->__('ID'),
            'index'  => 'attribute_group_id',
            'width'  => '50px',
            'type'   => 'number',
        ]);

        $this->addColumn('attribute_group_name', [
            'header' => $this->__('Title'),
            'index'  => 'attribute_group_name',
            'align'  => 'left',
        ]);

        $this->addColumn('sort_order', [
            'header' => $this->__('Position'),
            'index'  => 'sort_order',
            'type'   => 'number',
            'width'  => '90px',
        ]);

        $this->addColumn('attribute_count', [
            'header'   => $this->__('Attributes'),
            'index'    => 'attribute_count',
            'type'     => 'number',
            'width'    => '90px',
            'sortable' => false,
            'filter'   => false,
        ]);

        $this->addColumn('is_protected', [
            'header'   => $this->__('System Tab'),
            'index'    => 'is_protected',
            'type'     => 'options',
            'options'  => [0 => $this->__('No'), 1 => $this->__('Yes')],
            'width'    => '90px',
            'sortable' => false,
            'filter'   => false,
        ]);

        $this->addColumn('action', [
            'header'   => $this->__('Action'),
            'type'     => 'action',
            'getter'   => 'getId',
            'filter'   => false,
            'sortable' => false,
            'width'    => '80px',
            'actions'  => [
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
