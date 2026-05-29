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
 * MageAustralia_CategoryAttributes — category attribute CRUD controller.
 *
 * Ports the legacy Delta_Deltacats_Adminhtml_Deltacats_DeltacatsController to
 * Maho-modern conventions:
 *   - #[Maho\Config\Route('/admin/categoryattributes_attribute/<action>')]
 *     routing (no <routers> XML), mirroring Mageaustralia_AttributeManager's
 *     IndexController.
 *   - _setForcedFormKeyActions() CSRF on every state-changing action.
 *   - _isAllowed() / ADMIN_RESOURCE ACL gating.
 *
 * Create / edit / delete all funnel through Mage_Eav_Model_Entity_Setup, the
 * same proven API DeltaCats used (addAttribute / updateAttribute /
 * addAttributeOption / removeAttribute). The class name yields controllerName
 * "categoryattributes_attribute", so getUrl('adminhtml/categoryattributes_attribute/<action>')
 * resolves these routes.
 */
class MageAustralia_CategoryAttributes_Adminhtml_CategoryAttributes_AttributeController extends Mage_Adminhtml_Controller_Action
{
    public const ADMIN_RESOURCE = 'catalog/mageaustralia_categoryattributes';

    #[\Override]
    public function preDispatch()
    {
        $this->_setForcedFormKeyActions(['save', 'delete']);
        parent::preDispatch();
        return $this;
    }

    #[\Override]
    protected function _isAllowed()
    {
        return Mage::getSingleton('admin/session')->isAllowed(self::ADMIN_RESOURCE);
    }

    private function _helper(): MageAustralia_CategoryAttributes_Helper_Data
    {
        return Mage::helper('mageaustralia_categoryattributes');
    }

    #[Maho\Config\Route('/admin/categoryattributes_attribute/index')]
    public function indexAction(): void
    {
        $this->loadLayout();
        $this->_setActiveMenu('catalog/mageaustralia_categoryattributes');
        $this->_title($this->__('Catalog'))->_title($this->__('Category Attributes'));
        $this->renderLayout();
    }

    #[Maho\Config\Route('/admin/categoryattributes_attribute/new')]
    public function newAction(): void
    {
        $this->_forward('edit');
    }

    #[Maho\Config\Route('/admin/categoryattributes_attribute/edit')]
    public function editAction(): void
    {
        $id = (int) $this->getRequest()->getParam('id', 0);
        $attribute = null;
        if ($id > 0) {
            $attribute = $this->_helper()->loadCategoryAttribute($id);
            if (!$attribute) {
                Mage::getSingleton('adminhtml/session')->addError($this->__('This category attribute no longer exists.'));
                $this->_redirect('*/*/index');
                return;
            }
        }

        Mage::register('current_category_attribute', $attribute);

        $this->loadLayout();
        $this->_setActiveMenu('catalog/mageaustralia_categoryattributes');
        $this->_title($this->__('Catalog'))->_title($this->__('Category Attributes'));
        $this->_title($attribute ? $attribute->getAttributeCode() : $this->__('New Attribute'));
        $this->renderLayout();
    }

    /**
     * Create a new attribute, or update the editable bits of an existing one.
     *
     * Create: addAttribute('catalog_category', $code, $data) — mirrors the
     * DeltaCats input-type branches via the helper's buildCreateData(); options
     * for select/multiselect are passed inline as ['option' => ['values'=>...]].
     *
     * Edit: updateAttribute() for label / required / unique / group / sort_order,
     * and option add/remove via addAttributeOption() (the safe AttributeManager
     * path) — never a generic eav attribute ->save() for options.
     */
    #[Maho\Config\Route('/admin/categoryattributes_attribute/save')]
    public function saveAction(): void
    {
        $post = $this->getRequest()->getPost();
        if (!$post) {
            $this->_redirect('*/*/index');
            return;
        }

        $helper = $this->_helper();
        $id = (int) $this->getRequest()->getParam('id', 0);
        $setup = $helper->getEavSetup();

        try {
            if ($id > 0) {
                $this->_saveExisting($id);
            } else {
                $this->_createNew($post);
            }

            Mage::getSingleton('adminhtml/session')->addSuccess($this->__('The category attribute has been saved.'));
            Mage::app()->cleanCache([Mage_Core_Model_Translate::CACHE_TAG]);
            $this->_redirect('*/*/index');
        } catch (Mage_Core_Exception $e) {
            Mage::getSingleton('adminhtml/session')->addError($e->getMessage());
            $this->_redirectAfterError($id);
        } catch (\Throwable $e) {
            Mage::logException($e);
            Mage::getSingleton('adminhtml/session')->addError($this->__('Could not save the attribute: %s', $e->getMessage()));
            $this->_redirectAfterError($id);
        }
    }

    /**
     * @param array<string,mixed> $post
     */
    private function _createNew(array $post): void
    {
        $helper = $this->_helper();
        $code = mb_strtolower(trim((string) ($post['attribute_code'] ?? '')));

        $error = $helper->validateNewAttributeCode($code);
        if ($error !== null) {
            throw new Mage_Core_Exception($error);
        }
        $group = trim((string) ($post['attribute_group_name'] ?? ''));
        if ($group === '') {
            throw new Mage_Core_Exception($this->__('Please choose a Tab (attribute group) for the attribute.'));
        }

        $data = $helper->buildCreateData($post);
        $input = (string) ($post['frontend_input'] ?? 'text');

        // For select/multiselect, pass options inline to addAttribute().
        if ($helper->isOptionInput($input)) {
            $option = $this->getRequest()->getPost('option', []);
            $default = $this->getRequest()->getPost('default', []);
            if (!is_array($option)) {
                $option = [];
            }
            if (!is_array($default)) {
                $default = [];
            }
            $built = $helper->buildCreateOptions($option, $default);
            if ($built['values'] !== []) {
                $data['option'] = [
                    'values'   => $built['values'],
                    'defaults' => $built['defaults'],
                ];
                if ($input === 'select' && $built['defaults'] !== []) {
                    // single-select default = first flagged position
                    $data['default'] = [reset($built['defaults'])];
                } elseif ($input === 'multiselect' && $built['defaults'] !== []) {
                    $data['default'] = $built['defaults'];
                }
            }
        }

        $helper->getEavSetup()->addAttribute(MageAustralia_CategoryAttributes_Helper_Data::ENTITY_TYPE, $code, $data);
    }

    private function _saveExisting(int $id): void
    {
        $helper = $this->_helper();
        $attribute = $helper->loadCategoryAttribute($id);
        if (!$attribute) {
            throw new Mage_Core_Exception($this->__('This category attribute no longer exists.'));
        }

        $request = $this->getRequest();
        $group = trim((string) $request->getPost('attribute_group_name', ''));
        if ($group === '') {
            throw new Mage_Core_Exception($this->__('Please choose a Tab (attribute group) for the attribute.'));
        }

        // Editable bits only — never recreate the attribute or change its code.
        $updateData = [
            'frontend_label'    => (string) $request->getPost('frontend_label', $attribute->getFrontendLabel()),
            'is_required'       => (int) $request->getPost('is_required', 0),
            'is_unique'         => (int) $request->getPost('is_unique', 0),
            'sort_order'        => (int) $request->getPost('sort_order', 0),
            'note'              => (string) $request->getPost('note', ''),
            'frontend_class'    => (string) $request->getPost('frontend_class', ''),
        ];

        $setup = $helper->getEavSetup();
        $setup->updateAttribute($helper->getEntityTypeId(), $id, $updateData);

        // Reassign to the chosen group (addAttributeToSet handles re-grouping
        // within the same set; sort_order positions it inside the tab).
        $setId = $helper->getDefaultAttributeSetId();
        $setup->addAttributeToSet(
            $helper->getEntityTypeId(),
            $setId,
            $group,
            $id,
            (int) $request->getPost('sort_order', 0),
        );

        // Options (edit): add any new option rows via the safe addAttributeOption
        // path; remove options flagged for deletion. We never call a generic
        // eav attribute ->save() for options (it fatals on null source model).
        if ($helper->isOptionInput((string) $attribute->getFrontendInput())) {
            $this->_applyOptionEdits($attribute);
        }
    }

    /**
     * Add new option rows and delete flagged ones for an existing option-backed
     * attribute, using only Mage_Eav_Model_Entity_Setup::addAttributeOption().
     */
    private function _applyOptionEdits(Mage_Eav_Model_Entity_Attribute $attribute): void
    {
        $request = $this->getRequest();
        $option = $request->getPost('option', []);
        $default = $request->getPost('default', []);
        if (!is_array($option)) {
            $option = [];
        }
        if (!is_array($default)) {
            $default = [];
        }

        $setup = $this->_helper()->getEavSetup();
        $attributeId = (int) $attribute->getId();

        $values = (isset($option['value']) && is_array($option['value'])) ? $option['value'] : [];
        $deletes = (isset($option['delete']) && is_array($option['delete'])) ? $option['delete'] : [];

        $optionPayload = [];
        $deletePayload = [];

        foreach ($values as $rowKey => $stores) {
            $rowKey = (string) $rowKey;
            $isDelete = !empty($deletes[$rowKey]);

            if (ctype_digit($rowKey)) {
                // Existing option row.
                if ($isDelete) {
                    $deletePayload[$rowKey] = $stores;
                }
                // (label edits to existing options are out of scope for the
                // grid here; AttributeManager owns full per-store label editing.)
                continue;
            }
            if ($isDelete) {
                continue; // a new row flagged delete = nothing to do
            }
            // New row: admin label must be present.
            $admin = is_array($stores) ? trim((string) ($stores[0] ?? '')) : '';
            if ($admin !== '') {
                $optionPayload[$rowKey] = $stores;
            }
        }

        if ($optionPayload !== []) {
            $setup->addAttributeOption([
                'attribute_id' => $attributeId,
                'value'        => $optionPayload,
            ]);
        }
        if ($deletePayload !== []) {
            $setup->addAttributeOption([
                'attribute_id' => $attributeId,
                'value'        => array_fill_keys(array_keys($deletePayload), []),
                'delete'       => array_fill_keys(array_keys($deletePayload), true),
            ]);
        }
    }

    /**
     * Delete a user-defined category attribute. Guards against deleting native /
     * system (is_user_defined = 0) attributes, exactly like the spec requires.
     */
    #[Maho\Config\Route('/admin/categoryattributes_attribute/delete')]
    public function deleteAction(): void
    {
        $id = (int) $this->getRequest()->getParam('id', 0);
        $helper = $this->_helper();

        try {
            $attribute = $helper->loadCategoryAttribute($id);
            if (!$attribute) {
                throw new Mage_Core_Exception($this->__('This category attribute no longer exists.'));
            }
            if (!$attribute->getIsUserDefined()) {
                throw new Mage_Core_Exception(
                    $this->__('"%s" is a system attribute and cannot be deleted.', $attribute->getAttributeCode()),
                );
            }

            $helper->getEavSetup()->removeAttribute($helper->getEntityTypeId(), $id);
            Mage::app()->cleanCache([Mage_Core_Model_Translate::CACHE_TAG]);
            Mage::getSingleton('adminhtml/session')->addSuccess($this->__('The category attribute has been deleted.'));
        } catch (Mage_Core_Exception $e) {
            Mage::getSingleton('adminhtml/session')->addError($e->getMessage());
        } catch (\Throwable $e) {
            Mage::logException($e);
            Mage::getSingleton('adminhtml/session')->addError($this->__('Could not delete the attribute: %s', $e->getMessage()));
        }

        $this->_redirect('*/*/index');
    }

    private function _redirectAfterError(int $id): void
    {
        if ($id > 0) {
            $this->_redirect('*/*/edit', ['id' => $id]);
        } else {
            $this->_redirect('*/*/new');
        }
    }
}
