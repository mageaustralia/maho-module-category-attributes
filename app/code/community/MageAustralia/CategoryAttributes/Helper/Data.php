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
 * MageAustralia_CategoryAttributes — data helper.
 *
 * Stateless business logic for creating, editing and deleting catalog_category
 * EAV attributes and the attribute groups (category edit-page tabs) they belong
 * to. All EAV mutation funnels through Mage_Eav_Model_Entity_Setup, the same API
 * the legacy Delta_Deltacats controller used and verified working on Maho:
 *
 *   - addAttribute('catalog_category', $code, [...])  — create
 *   - updateAttribute('catalog_category', $id, ...)   — edit editable bits
 *   - removeAttribute(entity_type_id, $id)            — delete (guarded)
 *   - addAttributeOption([...])                        — add select options
 *   - addAttributeGroup / updateAttributeGroup / removeAttributeGroup — tabs
 *
 * Per Maho standards this is a Helper (stateless), not a "Service" class.
 */
class MageAustralia_CategoryAttributes_Helper_Data extends Mage_Core_Helper_Abstract
{
    protected $_moduleName = 'MageAustralia_CategoryAttributes';

    public const ENTITY_TYPE = Mage_Catalog_Model_Category::ENTITY;

    /**
     * Catalog input types this module can create. DeltaCats dropped select /
     * multiselect; we add them (plus the native yes-no / image / date types).
     *
     * @var array<string,string>
     */
    public const INPUT_TYPES = [
        'text'        => 'Text Field',
        'textarea'    => 'Text Area',
        'date'        => 'Date',
        'boolean'     => 'Yes/No',
        'select'      => 'Dropdown',
        'multiselect' => 'Multiple Select',
        'image'       => 'Media Image',
    ];

    /**
     * System attribute groups that must never be deleted by the tab manager,
     * compared case-insensitively by name. These ship with Maho's default
     * category attribute set and host native attributes.
     *
     * @var string[]
     */
    public const PROTECTED_GROUPS = [
        'general',
        'general information',
        'display settings',
        'custom design',
    ];

    private ?Mage_Eav_Model_Entity_Type $_entityType = null;

    public function getEntityType(): Mage_Eav_Model_Entity_Type
    {
        if ($this->_entityType === null) {
            /** @var Mage_Eav_Model_Entity_Type $type */
            $type = Mage::getModel('eav/entity_type')->loadByCode(self::ENTITY_TYPE);
            $this->_entityType = $type;
        }
        return $this->_entityType;
    }

    public function getEntityTypeId(): int
    {
        return (int) $this->getEntityType()->getId();
    }

    /**
     * The attribute set id whose groups act as the category edit-page tabs.
     */
    public function getDefaultAttributeSetId(): int
    {
        return (int) $this->getEntityType()->getDefaultAttributeSetId();
    }

    /**
     * The default EAV setup instance (core_setup connection) — the PROVEN
     * creation path verified working on Maho dev.
     */
    public function getEavSetup(): Mage_Eav_Model_Entity_Setup
    {
        /** @var Mage_Eav_Model_Entity_Setup $setup */
        $setup = Mage::getModel('eav/entity_setup', 'core_setup');
        return $setup;
    }

    /**
     * Input types as a Varien-form option array.
     *
     * @return array<int,array{value:string,label:string}>
     */
    public function getInputTypeOptions(): array
    {
        $options = [];
        foreach (self::INPUT_TYPES as $value => $label) {
            $options[] = ['value' => $value, 'label' => $this->__($label)];
        }
        return $options;
    }

    public function isOptionInput(string $input): bool
    {
        return $input === 'select' || $input === 'multiselect';
    }

    /**
     * Groups of the default category attribute set, ordered by sort_order, as a
     * form option array keyed by attribute_group_name (mirrors DeltaCats, which
     * assigned attributes to a group by NAME via addAttribute(['group'=>name])).
     *
     * @return array<int,array{value:string,label:string}>
     */
    public function getGroupOptions(): array
    {
        $options = [];
        foreach ($this->getGroupCollection() as $group) {
            $name = (string) $group->getAttributeGroupName();
            $options[] = ['value' => $name, 'label' => $name];
        }
        return $options;
    }

    /**
     * Loaded collection of the default set's attribute groups, sort-ordered.
     *
     * @return Mage_Eav_Model_Resource_Entity_Attribute_Group_Collection
     */
    public function getGroupCollection()
    {
        /** @var Mage_Eav_Model_Resource_Entity_Attribute_Group_Collection $collection */
        $collection = Mage::getResourceModel('eav/entity_attribute_group_collection')
            ->setAttributeSetFilter($this->getDefaultAttributeSetId())
            ->setSortOrder();
        return $collection;
    }

    /**
     * Number of attributes assigned to a group (in the default set).
     */
    public function getGroupAttributeCount(int $groupId): int
    {
        $resource = Mage::getSingleton('core/resource');
        $read = $resource->getConnection('core_read');
        $select = $read->select()
            ->from($resource->getTableName('eav_entity_attribute'), ['count' => 'COUNT(*)'])
            ->where('attribute_group_id = ?', $groupId)
            ->where('attribute_set_id = ?', $this->getDefaultAttributeSetId());
        return (int) $read->fetchOne($select);
    }

    public function isProtectedGroupName(string $name): bool
    {
        return in_array(mb_strtolower(trim($name)), self::PROTECTED_GROUPS, true);
    }

    /**
     * Validate a proposed attribute code. Returns an error string, or null if OK.
     * Rules mirror Maho's own attribute-code rules: lowercase letters/numbers/
     * underscore, must start with a letter, max 30 chars, and not already used.
     */
    public function validateNewAttributeCode(string $code): ?string
    {
        if ($code === '') {
            return $this->__('Attribute code is required.');
        }
        if (mb_strlen($code) > 30) {
            return $this->__('Attribute code must be 30 characters or fewer.');
        }
        if (!preg_match('/^[a-z][a-z0-9_]*$/', $code)) {
            return $this->__('Attribute code may only contain lowercase letters, numbers and underscores, and must start with a letter.');
        }
        if ($this->attributeCodeExists($code)) {
            return $this->__('A category attribute with this code already exists.');
        }
        return null;
    }

    public function attributeCodeExists(string $code): bool
    {
        $id = $this->getEavSetup()->getAttributeId($this->getEntityTypeId(), $code);
        return !empty($id);
    }

    /**
     * Load a catalog_category EAV attribute by id, or null if it isn't one.
     */
    public function loadCategoryAttribute(int $attributeId): ?Mage_Eav_Model_Entity_Attribute
    {
        if ($attributeId <= 0) {
            return null;
        }
        /** @var Mage_Eav_Model_Entity_Attribute $attribute */
        $attribute = Mage::getModel('eav/entity_attribute')->load($attributeId);
        if (!$attribute->getId()) {
            return null;
        }
        if ((int) $attribute->getEntityTypeId() !== $this->getEntityTypeId()) {
            return null;
        }
        return $attribute;
    }

    /**
     * Build the addAttribute() data array for a given input type, mirroring the
     * legacy DeltaCats input-type branches (image → backend image; boolean →
     * input select + boolean source; select/multiselect → table source + options;
     * default text/textarea/date). Adds STORE scope + user_defined like DeltaCats.
     *
     * @param array<string,mixed> $post
     * @return array<string,mixed>
     */
    public function buildCreateData(array $post): array
    {
        $input  = (string) ($post['frontend_input'] ?? 'text');
        $label  = (string) ($post['frontend_label'] ?? '');
        $data = [
            'type'             => null, // resolved per-input below
            'label'            => $label,
            'input'            => $input,
            'required'         => (int) ($post['is_required'] ?? 0),
            'unique'           => (int) ($post['is_unique'] ?? 0),
            'global'           => Mage_Catalog_Model_Resource_Eav_Attribute::SCOPE_STORE,
            'group'            => (string) ($post['attribute_group_name'] ?? ''),
            'sort_order'       => (int) ($post['sort_order'] ?? 0),
            'note'             => (string) ($post['note'] ?? ''),
            'user_defined'     => 1,
            'visible'          => 1,
            'default'          => '',
        ];

        $validation = trim((string) ($post['frontend_class'] ?? ''));
        if ($validation !== '') {
            $data['frontend_class'] = $validation;
        }

        switch ($input) {
            case 'image':
                $data['type']    = 'varchar';
                $data['backend'] = 'catalog/category_attribute_backend_image';
                break;
            case 'boolean':
                // DeltaCats stored boolean as input=select + boolean source.
                $data['type']   = 'int';
                $data['input']  = 'select';
                $data['source'] = 'eav/entity_attribute_source_boolean';
                break;
            case 'select':
                $data['type']   = 'int';
                $data['source'] = 'eav/entity_attribute_source_table';
                break;
            case 'multiselect':
                $data['type']    = 'varchar';
                $data['backend'] = 'eav/entity_attribute_backend_array';
                $data['source']  = 'eav/entity_attribute_source_table';
                break;
            case 'textarea':
                $data['type'] = 'text';
                break;
            case 'date':
                $data['type']    = 'datetime';
                $data['backend'] = 'eav/entity_attribute_backend_datetime';
                break;
            case 'text':
            default:
                $data['type'] = 'varchar';
                break;
        }

        return $data;
    }

    /**
     * Normalise the posted option grid into the addAttribute()
     * ['option' => ['values' => [...], 'value' => [...]]] shape on CREATE.
     *
     * Posted shape (same as the AttributeManager grid):
     *   option[value][option_N][store_id] = label
     *   default[] = option_N (rows flagged Is Default)
     *
     * On create the option ids do not exist yet, so we emit positional
     * ['values'] (admin labels) and remember which positions are default.
     *
     * @param array<string,mixed> $option
     * @param array<int,mixed>    $default
     * @return array{values:array<int,string>,defaults:array<int,int>}
     */
    public function buildCreateOptions(array $option, array $default): array
    {
        $values   = [];
        $defaults = [];
        $rows = (isset($option['value']) && is_array($option['value'])) ? $option['value'] : [];
        $index = 0;
        $keyToIndex = [];
        foreach ($rows as $rowKey => $stores) {
            $admin = '';
            if (is_array($stores)) {
                $admin = trim((string) ($stores[0] ?? ''));
            }
            if ($admin === '') {
                continue;
            }
            $values[$index] = $admin;
            $keyToIndex[(string) $rowKey] = $index;
            $index++;
        }
        foreach ($default as $rowKey) {
            $rowKey = (string) $rowKey;
            if (isset($keyToIndex[$rowKey])) {
                $defaults[] = $keyToIndex[$rowKey];
            }
        }
        return ['values' => $values, 'defaults' => $defaults];
    }
}
