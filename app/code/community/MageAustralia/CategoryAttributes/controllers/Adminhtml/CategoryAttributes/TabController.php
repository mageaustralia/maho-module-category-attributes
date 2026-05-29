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
 * MageAustralia_CategoryAttributes — category attribute TABS manager.
 *
 * The Mango "Custom Category Attributes" feature: CRUD over the category default
 * attribute set's eav_attribute_group rows, which Maho renders as the tabs on the
 * category edit page. Routing via #[Maho\Config\Route] (controllerName
 * "categoryattributes_tab"); CSRF + ACL like the attribute controller.
 *
 * Delete is guarded: a group may only be removed if it is (a) not a protected
 * system group (General / General Information / Display Settings / Custom Design)
 * and (b) empty (holds no attributes).
 */
class MageAustralia_CategoryAttributes_Adminhtml_CategoryAttributes_TabController extends Mage_Adminhtml_Controller_Action
{
    public const ADMIN_RESOURCE = 'catalog/mageaustralia_categoryattributes/tabs';

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

    #[Maho\Config\Route('/admin/categoryattributes_tab/index')]
    public function indexAction(): void
    {
        $this->loadLayout();
        $this->_setActiveMenu('catalog/mageaustralia_categoryattributes_tabs');
        $this->_title($this->__('Catalog'))->_title($this->__('Category Attribute Tabs'));
        $this->renderLayout();
    }

    #[Maho\Config\Route('/admin/categoryattributes_tab/new')]
    public function newAction(): void
    {
        $this->_forward('edit');
    }

    #[Maho\Config\Route('/admin/categoryattributes_tab/edit')]
    public function editAction(): void
    {
        $id = (int) $this->getRequest()->getParam('id', 0);
        $group = null;
        if ($id > 0) {
            $group = $this->_loadGroup($id);
            if (!$group) {
                Mage::getSingleton('adminhtml/session')->addError($this->__('This tab no longer exists.'));
                $this->_redirect('*/*/index');
                return;
            }
        }
        Mage::register('current_category_tab', $group);

        $this->loadLayout();
        $this->_setActiveMenu('catalog/mageaustralia_categoryattributes_tabs');
        $this->_title($this->__('Catalog'))->_title($this->__('Category Attribute Tabs'));
        $this->_title($group ? $group->getAttributeGroupName() : $this->__('New Tab'));
        $this->renderLayout();
    }

    /**
     * Create or update a group. addAttributeGroup() on the EAV setup both
     * inserts a new group and updates an existing one (keyed by id); we always
     * target the default category attribute set.
     */
    #[Maho\Config\Route('/admin/categoryattributes_tab/save')]
    public function saveAction(): void
    {
        $post = $this->getRequest()->getPost();
        if (!$post) {
            $this->_redirect('*/*/index');
            return;
        }

        $helper = $this->_helper();
        $id = (int) $this->getRequest()->getParam('id', 0);
        $name = trim((string) ($post['attribute_group_name'] ?? ''));
        $sortOrder = (int) ($post['sort_order'] ?? 0);

        try {
            if ($name === '') {
                throw new Mage_Core_Exception($this->__('Tab name is required.'));
            }

            $setup = $helper->getEavSetup();
            $setId = $helper->getDefaultAttributeSetId();

            if ($id > 0) {
                $group = $this->_loadGroup($id);
                if (!$group) {
                    throw new Mage_Core_Exception($this->__('This tab no longer exists.'));
                }
                // Renaming a protected system group is refused — that would
                // orphan the tab native attributes expect to render under.
                if ($helper->isProtectedGroupName((string) $group->getAttributeGroupName())
                    && mb_strtolower($name) !== mb_strtolower((string) $group->getAttributeGroupName())
                ) {
                    throw new Mage_Core_Exception($this->__('"%s" is a system tab and cannot be renamed.', $group->getAttributeGroupName()));
                }
                $setup->updateAttributeGroup($helper->getEntityTypeId(), $setId, $id, 'attribute_group_name', $name);
                $setup->updateAttributeGroup($helper->getEntityTypeId(), $setId, $id, 'sort_order', $sortOrder);
            } else {
                if ($this->_groupNameExists($name)) {
                    throw new Mage_Core_Exception($this->__('A tab with this name already exists.'));
                }
                $setup->addAttributeGroup($helper->getEntityTypeId(), $setId, $name, $sortOrder);
            }

            Mage::app()->cleanCache([Mage_Core_Model_Translate::CACHE_TAG]);
            Mage::getSingleton('adminhtml/session')->addSuccess($this->__('The tab has been saved.'));
            $this->_redirect('*/*/index');
        } catch (Mage_Core_Exception $e) {
            Mage::getSingleton('adminhtml/session')->addError($e->getMessage());
            $this->_redirectAfterError($id);
        } catch (\Throwable $e) {
            Mage::logException($e);
            Mage::getSingleton('adminhtml/session')->addError($this->__('Could not save the tab: %s', $e->getMessage()));
            $this->_redirectAfterError($id);
        }
    }

    /**
     * Delete an empty, non-system group only.
     */
    #[Maho\Config\Route('/admin/categoryattributes_tab/delete')]
    public function deleteAction(): void
    {
        $id = (int) $this->getRequest()->getParam('id', 0);
        $helper = $this->_helper();

        try {
            $group = $this->_loadGroup($id);
            if (!$group) {
                throw new Mage_Core_Exception($this->__('This tab no longer exists.'));
            }
            $name = (string) $group->getAttributeGroupName();

            if ($helper->isProtectedGroupName($name)) {
                throw new Mage_Core_Exception($this->__('"%s" is a system tab and cannot be deleted.', $name));
            }
            $count = $helper->getGroupAttributeCount($id);
            if ($count > 0) {
                throw new Mage_Core_Exception(
                    $this->__('"%s" still has %d attribute(s). Move or delete them before removing the tab.', $name, $count),
                );
            }

            $helper->getEavSetup()->removeAttributeGroup($helper->getEntityTypeId(), $helper->getDefaultAttributeSetId(), $id);
            Mage::app()->cleanCache([Mage_Core_Model_Translate::CACHE_TAG]);
            Mage::getSingleton('adminhtml/session')->addSuccess($this->__('The tab has been deleted.'));
        } catch (Mage_Core_Exception $e) {
            Mage::getSingleton('adminhtml/session')->addError($e->getMessage());
        } catch (\Throwable $e) {
            Mage::logException($e);
            Mage::getSingleton('adminhtml/session')->addError($this->__('Could not delete the tab: %s', $e->getMessage()));
        }

        $this->_redirect('*/*/index');
    }

    private function _loadGroup(int $id): ?Mage_Eav_Model_Entity_Attribute_Group
    {
        if ($id <= 0) {
            return null;
        }
        /** @var Mage_Eav_Model_Entity_Attribute_Group $group */
        $group = Mage::getModel('eav/entity_attribute_group')->load($id);
        if (!$group->getId()) {
            return null;
        }
        // Only groups of the category default set are manageable here.
        if ((int) $group->getAttributeSetId() !== $this->_helper()->getDefaultAttributeSetId()) {
            return null;
        }
        return $group;
    }

    private function _groupNameExists(string $name): bool
    {
        foreach ($this->_helper()->getGroupCollection() as $group) {
            if (mb_strtolower((string) $group->getAttributeGroupName()) === mb_strtolower($name)) {
                return true;
            }
        }
        return false;
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
