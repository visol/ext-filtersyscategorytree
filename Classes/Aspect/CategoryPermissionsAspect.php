<?php
namespace Visol\Filtersyscategorytree\Aspect;

/**
 * This file is part of the TYPO3 CMS project.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 * The TYPO3 project - inspiring people to share!
 */

use TYPO3\CMS\Backend\Tree\TreeNode;
use TYPO3\CMS\Backend\Tree\TreeNodeCollection;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Tree\TableConfiguration\DatabaseTreeDataProvider;

/**
 * We do not have AOP in TYPO3 for now, thus the aspect which
 * deals with tree data security is a slot which reacts on a signal
 * on data data object initialization.
 *
 * The aspect define category mount points according to BE User permissions.
 */
class CategoryPermissionsAspect extends \TYPO3\CMS\Backend\Security\CategoryPermissionsAspect {

	/**
	 * The slot for the signal in DatabaseTreeDataProvider.
	 *
	 * @param DatabaseTreeDataProvider $dataProvider
	 * @param TreeNode $treeData
	 * @return void
	 */
	public function addUserPermissionsToCategoryTreeData(DatabaseTreeDataProvider $dataProvider, $treeData) {

		if (!$this->backendUserAuthentication->isAdmin() && $dataProvider->getTableName() === $this->categoryTableName) {

			// Get User permissions related to category
			$categoryMountPoints = $this->backendUserAuthentication->getCategoryMountPoints();

			// Backup child nodes to be processed.
			$treeNodeCollection = $treeData->getChildNodes();

			if (!empty($categoryMountPoints) && !empty($treeNodeCollection)) {


				$categoryMountPoints = $this->unifyCategoryMounts($categoryMountPoints, $treeNodeCollection);

				// Create an empty tree node collection to receive the secured nodes.
				/** @var TreeNodeCollection $securedTreeNodeCollection */
				$securedTreeNodeCollection = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance('TYPO3\\CMS\\Backend\\Tree\\TreeNodeCollection');

				foreach ($categoryMountPoints as $categoryMountPoint) {

					$treeNode = $this->lookUpCategoryMountPointInTreeNodes((int)$categoryMountPoint, $treeNodeCollection);
					if (!is_null($treeNode)) {
						$securedTreeNodeCollection->append($treeNode);
					}
				}

				// Reset child nodes.
				$treeData->setChildNodes($securedTreeNodeCollection);
			}
		}
	}

	/**
	 * Remove duplicated category mounts
	 *
	 * @param array $categoryMountPoints
	 * @param TreeNodeCollection $treeNodeCollection
	 * @return array
	 */
	protected function unifyCategoryMounts(array $categoryMountPoints, \TYPO3\CMS\Backend\Tree\TreeNodeCollection $treeNodeCollection) {
		$data = $rootlineInformation = array();

		$this->calculateParentNodes($rootlineInformation, $categoryMountPoints, 0, $data, $treeNodeCollection);

		foreach ($categoryMountPoints as $key => $id) {
			$rootlineUp = $rootlineInformation[$id];
			if (!empty($rootlineUp)) {
				foreach ($rootlineUp as $rootlineId) {
					if (in_array($rootlineId, $categoryMountPoints)) {
						unset($categoryMountPoints[$key]);
					}
				}
			}

		}
		return $categoryMountPoints;
	}

	/**
	 * Get the parent nodes for every node
	 *
	 * @param array $rootlineInformation
	 * @param array $categoryMountPoints
	 * @param int $level
	 * @param array $data
	 * @param TreeNodeCollection $treeNodeCollection
	 */
	protected function calculateParentNodes(array &$rootlineInformation, array $categoryMountPoints, $level, $data, \TYPO3\CMS\Backend\Tree\TreeNodeCollection $treeNodeCollection) {
		foreach ($treeNodeCollection as $treeNode) {
			if ($level === 0) {
				$data = array();
			}

			$id = (int)$treeNode->getId();

			foreach ($categoryMountPoints as $p) {
				if ((int)$p === $id) {
					$rootlineInformation[$id] = $data;
				}
			}

			if ($treeNode->hasChildNodes()) {
				$data[] = $id;
				$childNodes = $treeNode->getChildNodes();
				$this->calculateParentNodes($rootlineInformation, $categoryMountPoints, $level + 1, $data, $childNodes);
			}
		}
	}
}
