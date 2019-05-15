<?php
namespace EssentialDots\EdMigrate\Transformation;
use EssentialDots\EdMigrate\Domain\Model\AbstractEntity;
use EssentialDots\EdMigrate\Domain\Model\Node;
use EssentialDots\EdMigrate\Expression\ExpressionInterface;
use EssentialDots\EdMigrate\Service\DatabaseService;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Utility\MathUtility;

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
 * Class UpdateColPosTransformation
 *
 * @package EssentialDots\EdMigrate\Transformation
 */
class UpdateColPosTransformation implements TransformationInterface {

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
	protected $tableName;

	/**
	 * @var ExpressionInterface
	 */
	protected $whereExpression;

	/**
	 * @param $colPosMap
	 * @param $notUsedColPos
	 * @param string $tableName
	 * @param ExpressionInterface|NULL $whereExpression
	 */
	public function __construct($colPosMap, $notUsedColPos, $tableName = '*', ExpressionInterface $whereExpression = NULL) {
		$this->colPosMap = $colPosMap;
		$this->notUsedColPos = $notUsedColPos;
		$this->tableName = $tableName;
		$this->whereExpression = $whereExpression;
	}

	/**
	 * @param AbstractEntity $node
	 * @return bool
	 */
	public function run(AbstractEntity $node) {
		if (
			($node->_getTableName() === 'pages' || $node->_getTableName() === 'tt_content') &&
			MathUtility::canBeInterpretedAsInteger($node->getUid())
		) {
			/** @var \TYPO3\CMS\Extbase\Object\ObjectManagerInterface $objectManager */
			$objectManager = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance('TYPO3\\CMS\\Extbase\\Object\\ObjectManager');
			/** @var \EssentialDots\EdMigrate\Domain\Repository\NodeRepository $nodeRepository */
			$nodeRepository = $objectManager->get('EssentialDots\\EdMigrate\\Domain\\Repository\\NodeRepository');
			/** @var \EssentialDots\EdMigrate\Persistence\PersistenceSession $persistenceSession */
			$persistenceSession = $objectManager->get('EssentialDots\\EdMigrate\\Persistence\\PersistenceSession');

			$ttContentEnableFields = DatabaseService::deleteClause('tt_content');

			if (
				$node->_getTableName() === 'pages' &&
				($this->tableName === '*' || $this->tableName === 'pages') &&
				($this->whereExpression === NULL || $this->whereExpression->evaluate($node))
			) {
				$colPosMap = array();
				$allUids = array(0);
				foreach ($this->colPosMap as $colPos => $uidList) {
					$colPosMap[$colPos] = $uidList instanceof ExpressionInterface ? $uidList->evaluate($node) : (string) $uidList;
					$allUids = array_merge($allUids, GeneralUtility::intExplode(',', $colPosMap[$colPos], TRUE));
				}

				$contentElements = $nodeRepository->findBy('tt_content', '(uid IN (' . implode(',', $allUids) . ') OR (l18n_parent = 0 AND pid = ' . (int) $node->getUid() . '))' . $ttContentEnableFields);
//				$contentElements = $nodeRepository->findBy('tt_content',
//					'(uid IN (' . implode(',', $allUids) . ') OR ((l18n_parent = 0 OR sys_language_uid = 0 OR sys_language_uid = -1) AND pid = ' .
// 					(int) $node->getUid() . '))' . $ttContentEnableFields);
				foreach ($contentElements as $contentElement) {
					$finalColPos = $this->notUsedColPos;
					$finalSorting = -1;
					foreach ($colPosMap as $colPos => $uidList) {
						if (GeneralUtility::inList($uidList, $contentElement->getUid())) {
							$finalColPos = $colPos;
							$finalSorting = array_search((string) $contentElement->getUid(), GeneralUtility::trimExplode(',', $uidList, TRUE));
							break;
						}
					}

					if ($contentElement->getPid() === $node->getUid()) {
						$contentElement->setColpos($finalColPos);
						$contentElement->setSorting($finalSorting);
						echo '  \- element[' . $contentElement->getUid() . '][colPos] => ' . $contentElement->getColpos()  . ', sort: ' . $finalSorting . PHP_EOL;
					} else {
						/** @var Node $referenceElement */
						$referenceElement = GeneralUtility::makeInstance('EssentialDots\\EdMigrate\\Domain\\Model\\Node', 'tt_content', array());
						$referenceElement->setCtype('shortcut');
						$referenceElement->setPid($node->getUid());
						$referenceElement->setRecords($contentElement->getUid());
						$referenceElement->setColpos($finalColPos);
						$referenceElement->setSorting($finalSorting);
						$persistenceSession->registerEntity('tt_content', $referenceElement->getUid(), $referenceElement);
						echo '  \- reference[' . $contentElement->getUid() . '][colPos] => ' . $contentElement->getColpos() . ' on page ' . $node->getUid() . ', sort: ' . $finalSorting . PHP_EOL;
					}
				}
			} elseif (
				$node->_getTableName() === 'tt_content' &&
				(int) $node->getL18nParent() === 0 &&
				($this->tableName === '*' || $this->tableName === 'tt_content') &&
				($this->whereExpression === NULL || $this->whereExpression->evaluate($node))
			) {
				$contentElements = $nodeRepository->findBy('tt_content', 'l18n_parent = ' . (int) $node->getUid() . $ttContentEnableFields);
				foreach ($contentElements as $contentElement) {
					if ($contentElement->getSysLanguageUid() === $node->getSysLanguageUid()) {
						// conflict resolution, templavoila can have this kind of weird data
						$contentElement->setSorting(-1);
						$contentElement->setColpos($this->notUsedColPos);
					} else {
						$contentElement->setSorting($node->getSorting());
						$contentElement->setColpos($node->getColpos());
					}

					echo '  \- translation[' . $contentElement->getUid() . '][colPos] => ' . $contentElement->getColpos() . PHP_EOL;
				}
			}
		}

		return TRUE;
	}
}