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
 * MageAustralia_CategoryAttributes — Manage Options grid for select/multiselect.
 *
 * Read-only helpers for the options template. Modelled on
 * Mageaustralia_AttributeManager's options grid: Admin + per-store labels,
 * Is Default, Position, Add/Delete — vanilla JS, escapeHtml only. The grid's
 * field names (option[value][...], option[delete][...], default[]) are the same
 * names the core attribute controller and our saveAction understand.
 */
class MageAustralia_CategoryAttributes_Block_Adminhtml_Attribute_Edit_Options extends Mage_Adminhtml_Block_Template
{
    public function __construct()
    {
        parent::__construct();
        $this->setTemplate('mageaustralia/categoryattributes/options.phtml');
    }

    public function getAttribute(): ?Mage_Eav_Model_Entity_Attribute
    {
        $attribute = Mage::registry('current_category_attribute');
        return $attribute instanceof Mage_Eav_Model_Entity_Attribute ? $attribute : null;
    }

    public function isMultiselect(): bool
    {
        return $this->getAttribute()?->getFrontendInput() === 'multiselect';
    }

    public function getDefaultInputType(): string
    {
        return $this->isMultiselect() ? 'checkbox' : 'radio';
    }

    /**
     * Stores incl. admin (store 0), as the native options tab builds it.
     *
     * @return Mage_Core_Model_Resource_Store_Collection
     */
    public function getStores()
    {
        $stores = $this->getData('stores');
        if ($stores === null) {
            $stores = Mage::getModel('core/store')->getResourceCollection()->setLoadDefault(true)->load();
            $this->setData('stores', $stores);
        }
        return $stores;
    }

    /**
     * @return array<int,string> option_id => value for a store
     */
    private function _storeOptionValues(int $storeId): array
    {
        $key = 'store_option_values_' . $storeId;
        $values = $this->getData($key);
        if ($values === null) {
            $values = [];
            $attribute = $this->getAttribute();
            if ($attribute && $attribute->getId()) {
                $collection = Mage::getResourceModel('eav/entity_attribute_option_collection')
                    ->setAttributeFilter($attribute->getId())
                    ->setStoreFilter($storeId, false)
                    ->load();
                foreach ($collection as $item) {
                    $values[(int) $item->getId()] = (string) $item->getValue();
                }
            }
            $this->setData($key, $values);
        }
        return $values;
    }

    /**
     * Existing option rows for an option-backed attribute, ascending position.
     *
     * @return array<int,array{id:int,sort_order:int,is_default:bool,stores:array<int,string>}>
     */
    public function getOptionRows(): array
    {
        $attribute = $this->getAttribute();
        if (!$attribute || !$attribute->getId()) {
            return [];
        }

        $defaults = [];
        foreach (explode(',', (string) $attribute->getDefaultValue()) as $v) {
            $v = (int) trim($v);
            if ($v > 0) {
                $defaults[$v] = true;
            }
        }

        $collection = Mage::getResourceModel('eav/entity_attribute_option_collection')
            ->setAttributeFilter($attribute->getId())
            ->setPositionOrder('asc', true)
            ->load();

        $storeIds = [];
        foreach ($this->getStores() as $store) {
            $storeIds[] = (int) $store->getId();
        }

        $rows = [];
        foreach ($collection as $option) {
            $optionId = (int) $option->getId();
            $stores = [];
            foreach ($storeIds as $sid) {
                $vals = $this->_storeOptionValues($sid);
                $stores[$sid] = $vals[$optionId] ?? '';
            }
            $rows[] = [
                'id'         => $optionId,
                'sort_order' => (int) $option->getSortOrder(),
                'is_default' => isset($defaults[$optionId]),
                'stores'     => $stores,
            ];
        }
        return $rows;
    }
}
