<?php
namespace EssentialDots\EdMigrate\Transformation;
use EssentialDots\EdMigrate\Domain\Model\AbstractEntity;
use EssentialDots\EdMigrate\Domain\Model\Node;
use EssentialDots\EdMigrate\Expression\ExpressionInterface;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2015 Essential Dots d.o.o. Belgrade
 *  All rights reserved
 *
 *  This script is free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 3 of the License, or
 *  (at your option) any later version.
 *
 *  The GNU General Public License can be found at
 *  http://www.gnu.org/copyleft/gpl.html.
 *
 *  This script is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 *  This copyright notice MUST APPEAR in all copies of the script!
 ***************************************************************/

/**
 * Class UpdateGridColPosTransformation
 *
 * @package EssentialDots\EdMigrate\Transformation
 */
class UpdateGridColPosTransformation implements TransformationInterface {

	/**
	 * @var array
	 */
	protected $colPosMap;

	/**
	 * @var array
	 */
	protected $notUsedColPos;

	/**
	 * @var string
	 */
	protected $sortingField;

	/**
	 * @var string
	 */
	protected $columnField;

	/**
	 * @var UpdateFieldsTransformation
	 */
	protected $updateFieldsTransformation;

	/**
	 * @var UpdateFieldsTransformation
	 */
	protected $referenceUpdateFieldsTransformation;

	/**
	 * @var string
	 */
	protected $parentField;

	/**
	 * @var string
	 */
	protected $referenceParentField;

	/**
	 * @var string
	 */
	protected $tableName;

	/**
	 * @var string
	 */
	protected $childTableName;

	/**
	 * @var ExpressionInterface
	 */
	protected $whereClause;

	/**
	 * @param $colPosMap
	 * @param $notUsedColPos
	 * @param $sortingField
	 * @param $columnField
	 * @param array|NULL $updateFields
	 * @param string $parentField
	 * @param string $referenceParentField
	 * @param string $tableName
	 * @param string $childTableName
	 * @param ExpressionInterface|NULL $whereClause
	 */
	public function __construct(
		$colPosMap,
		$notUsedColPos,
		$sortingField, $columnField, array $updateFields = NULL,
		$parentField =  '',
		$referenceParentField =  '',
		$tableName = 'tt_content', $childTableName = 'tt_content',
		ExpressionInterface $whereClause = NULL
	) {
		$this->colPosMap = $colPosMap;
		$this->notUsedColPos = $notUsedColPos;
		$this->sortingField = $sortingField;
		$this->columnField = $columnField;
		if (is_array($updateFields) && count($updateFields) > 0) {
			$this->updateFieldsTransformation = new UpdateFieldsTransformation($updateFields, $childTableName);
			$this->referenceUpdateFieldsTransformation = new UpdateFieldsTransformation($updateFields, 'tt_content');
		}
		$this->parentField = $parentField;
		$this->referenceParentField = $referenceParentField;
		$this->tableName = $tableName;
		$this->childTableName = $childTableName;
		$this->whereClause = $whereClause;
	}

	/**
	 * @param AbstractEntity $node
	 * @return bool
	 */
	public function run(AbstractEntity $node) {
		if (
			$node->_getTableName() === $this->tableName &&
			($this->whereClause === NULL || $this->whereClause->evaluate($node))
		) {
			/** @var \TYPO3\CMS\Extbase\Object\ObjectManagerInterface $objectManager */
			$objectManager = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance('TYPO3\\CMS\\Extbase\\Object\\ObjectManager');
			/** @var \EssentialDots\EdMigrate\Domain\Repository\NodeRepository $nodeRepository */
			$nodeRepository = $objectManager->get('EssentialDots\\EdMigrate\\Domain\\Repository\\NodeRepository');
			/** @var \EssentialDots\EdMigrate\Persistence\PersistenceSession $persistenceSession */
			$persistenceSession = $objectManager->get('EssentialDots\\EdMigrate\\Persistence\\PersistenceSession');

			$sortingSetter = 'set' . ucfirst($this->sortingField);
			$sortingHas = 'has' . ucfirst($this->sortingField);

			$colPosGetter = 'get' . ucfirst($this->columnField);
			$colPosSetter = 'set' . ucfirst($this->columnField);

			$parentFieldSetter = 'set' . ucfirst($this->parentField);
			$parentFieldGetter = 'get' . ucfirst($this->parentField);
			$parentFieldHas = 'has' . ucfirst($this->parentField);

			$referenceParentFieldSetter = 'set' . ucfirst($this->referenceParentField);
			$referenceParentFieldHas = 'has' . ucfirst($this->referenceParentField);

			$colPosMap = array();
			$allUids = array(0);
			foreach ($this->colPosMap as $colPos => $uidList) {
				$finalUidList = $uidList instanceof ExpressionInterface ? $uidList->evaluate($node) : (string) $uidList;
				$uidArr = GeneralUtility::intExplode(',', $finalUidList, TRUE);
				// prevent simple recursion
				$uidArr = array_diff($uidArr, array($node->getUid()));
				$colPosMap[$colPos] = implode(',', $uidArr);
				$allUids = array_merge($allUids, $uidArr);
			}

//			$childElements =
// 				$nodeRepository->findBy($this->childTableName, '(uid IN (' . implode(',', $allUids) . ') OR (l18n_parent = 0 AND pid = ' . (int) $node->getUid() . '))' .
//				BackendUtility::deleteClause($this->childTableName));
			$childElements = $nodeRepository->findBy($this->childTableName, 'uid IN (' . implode(',', $allUids) . ')' . BackendUtility::deleteClause($this->childTableName));
			foreach ($childElements as $childElement) {
//				$found = FALSE;
				$finalColPos = $this->notUsedColPos;
				$finalSorting = -1;

				foreach ($colPosMap as $colPos => $uidList) {
					if (GeneralUtility::inList($uidList, $childElement->getUid())) {
						$finalColPos = $colPos;
						$finalSorting = array_search((string) $childElement->getUid(), GeneralUtility::trimExplode(',', $uidList, TRUE));
//						$found = TRUE;
						break;
					}
				}

				if (
					$this->childTableName !== 'tt_content' ||
					($node->_getTableName() !== 'pages' && $childElement->getPid() === $node->getPid()) ||
					($node->_getTableName() === 'pages' && $childElement->getPid() === $node->getUid())
				) {
					$childElement->$colPosSetter($finalColPos);
					if ($this->sortingField && $childElement->$sortingHas()) {
						$childElement->$sortingSetter($finalSorting);
					}
					if ($this->parentField && $childElement->$parentFieldHas()) {
						if ($childElement->$parentFieldGetter()) {
							if ($childElement->$parentFieldGetter() !== $node->getUid()) {
								// conflict resolution, we already have parent column configured.
								// double check both uids and see
								echo '  \- CONFLICT: elements[' . $childElement->$parentFieldGetter() . '] and [' . $node->getUid() . '] both claim they are parents of => ' . $childElement->getUid() . PHP_EOL;
								$oldParent = $nodeRepository->findBy($node->_getTableName(), 'uid = ' . (int)  $childElement->$parentFieldGetter(), 1);
								$pUid = $node->getUid();
								if ($oldParent) {
									$parentLanuageField = $GLOBALS['TCA'][$node->_getTableName()]['ctrl']['languageField'];
									$childLanuageField = $GLOBALS['TCA'][$childElement->_getTableName()]['ctrl']['languageField'];
									if ($parentLanuageField && $childLanuageField) {
										$parentLanuageFieldGetter = 'get' . GeneralUtility::underscoredToUpperCamelCase($parentLanuageField);
										$childLanuageFieldGetter = 'get' . GeneralUtility::underscoredToUpperCamelCase($childLanuageField);
										if ($oldParent->$parentLanuageFieldGetter() != $node->$parentLanuageFieldGetter()) {
											if ($oldParent->$parentLanuageFieldGetter() == $childElement->$childLanuageFieldGetter()) {
												$pUid = $childElement->$parentFieldGetter();
											} else {
												$pUid = $node->getUid();
											}
										} else {
											$pUid = $node->getUid();
										}
									}
								}

								$childElement->$parentFieldSetter($pUid);
								echo '               *** resolved parenthood to [' . $pUid . ']' . PHP_EOL;
							}
						} else {
							$childElement->$parentFieldSetter($node->getUid());
						}
					}
					if ($this->updateFieldsTransformation !== NULL) {
						$this->updateFieldsTransformation->run($childElement);
					}
					echo '  \- element[' . $childElement->getUid() . '][' . $this->columnField . '] => ' . $childElement->$colPosGetter() . ', ' . $this->sortingField . ': ' . $finalSorting . PHP_EOL;
				} else {
					/** @var Node $referenceElement */
					$referenceElement = GeneralUtility::makeInstance('EssentialDots\\EdMigrate\\Domain\\Model\\Node', 'tt_content', array());
					$referenceElement->setCtype('shortcut');
					$referenceElement->setPid($node->getUid());
					$referenceElement->setRecords($childElement->getUid());
					$referenceElement->setColpos($finalColPos);
					$referenceElement->setSorting($finalSorting);
					if ($this->referenceParentField && $childElement->$referenceParentFieldHas()) {
						$childElement->$referenceParentFieldSetter($node->getUid());
					}
					if ($this->referenceUpdateFieldsTransformation !== NULL) {
						$this->referenceUpdateFieldsTransformation->run($childElement);
					}
					$persistenceSession->registerEntity('tt_content', $referenceElement->getUid(), $referenceElement);
					echo '  \- reference[' . $childElement->getUid() . '][' . $this->columnField . '] => ' . $childElement->getColpos() .
						' on page ' . $node->getUid() . ', ' . $this->referenceParentField . ': ' . $finalSorting . PHP_EOL;
				}
			}
		}

		return TRUE;
	}
}