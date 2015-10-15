<?php
namespace EssentialDots\EdMigrate\Transformation;
use EssentialDots\EdMigrate\Domain\Model\AbstractEntity;
use EssentialDots\EdMigrate\Domain\Model\Node;
use EssentialDots\EdMigrate\Expression\ExpressionInterface;
use TYPO3\CMS\Backend\Utility\BackendUtility;
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
	 * @param $colPosMap
	 * @param $notUsedColPos
	 */
	public function __construct($colPosMap, $notUsedColPos) {
		$this->colPosMap = $colPosMap;
		$this->notUsedColPos = $notUsedColPos;
	}

	/**
	 * @param AbstractEntity $node
	 * @return bool
	 */
	public function run(AbstractEntity $node) {
		if (($node->_getTableName() === 'pages' || $node->_getTableName() === 'tt_content') && MathUtility::canBeInterpretedAsInteger($node->getUid())) {
			/** @var \TYPO3\CMS\Extbase\Object\ObjectManagerInterface $objectManager */
			$objectManager = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance('TYPO3\\CMS\\Extbase\\Object\\ObjectManager');
			/** @var \EssentialDots\EdMigrate\Domain\Repository\NodeRepository $nodeRepository */
			$nodeRepository = $objectManager->get('EssentialDots\\EdMigrate\\Domain\\Repository\\NodeRepository');
			/** @var \EssentialDots\EdMigrate\Persistence\PersistenceSession $persistenceSession */
			$persistenceSession = $objectManager->get('EssentialDots\\EdMigrate\\Persistence\\PersistenceSession');

			$ttContentEnableFields = BackendUtility::deleteClause('tt_content');

			if ($node->_getTableName() === 'pages') {
				$colPosMap = array();
				$allUids = array(0);
				foreach ($this->colPosMap as $colPos => $uidList) {
					$colPosMap[$colPos] = $uidList instanceof ExpressionInterface ? $uidList->evaluate($node) : (string) $uidList;
					$allUids = array_merge($allUids, GeneralUtility::intExplode(',', $colPosMap[$colPos], TRUE));
				}

				$contentElements = $nodeRepository->findBy('tt_content', '(uid IN (' . implode(',', $allUids) . ') OR (l18n_parent = 0 AND pid = ' . (int) $node->getUid() . '))' . $ttContentEnableFields);
//				$contentElements = $nodeRepository->findBy('tt_content', 'uid IN (' . implode(',', $allUids) . ')' . $ttContentEnableFields);
				foreach ($contentElements as $contentElement) {
//					$found = FALSE;
					$finalColPos = $this->notUsedColPos;
					foreach ($colPosMap as $colPos => $uidList) {
						if (GeneralUtility::inList($uidList, $contentElement->getUid())) {
							$finalColPos = $colPos;
//							$found = TRUE;
							break;
						}
					}
//					if (!$found) {
//						// @todo: maybe delete content element?
//					}

					if ($contentElement->getPid() === $node->getUid()) {
						$contentElement->setColpos($finalColPos);
						echo '  \- element[' . $contentElement->getUid() . '][colPos] => ' . $contentElement->getColpos() . PHP_EOL;
					} else {
						/** @var Node $entity */
						$referenceElement = GeneralUtility::makeInstance('EssentialDots\\EdMigrate\\Domain\\Model\\Node', 'tt_content', array());
						$referenceElement->setCtype('shortcut');
						$referenceElement->setPid($node->getUid());
						$referenceElement->setRecords($contentElement->getUid());
						$referenceElement->setColpos($finalColPos);
						$persistenceSession->registerEntity('tt_content', $referenceElement->getUid(), $referenceElement);
						echo '  \- reference[' . $contentElement->getUid() . '][colPos] => ' . $contentElement->getColpos() . ' on page ' . $node->getUid() . PHP_EOL;
//						throw new \RuntimeException('Adding reference element has not been implemented yet. Found used element ' . $contentElement->getUid() . ' on page ' . $node->getUid());
					}
				}
			} elseif ((int) $node->getL18nParent() === 0 && $node->getColpos() != $this->notUsedColPos) {
				$contentElements = $nodeRepository->findBy('tt_content', 'l18n_parent = ' . (int) $node->getUid() . $ttContentEnableFields);
				foreach ($contentElements as $contentElement) {
					$contentElement->setColpos($node->getColpos());
					echo '  \- translation[' . $contentElement->getUid() . '][colPos] => ' . $contentElement->getColpos() . PHP_EOL;
				}
			}
		}

		return TRUE;
	}
}