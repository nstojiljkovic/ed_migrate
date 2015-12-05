<?php
namespace EssentialDots\EdMigrate\Service;

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
use EssentialDots\EdMigrate\Domain\Model\AbstractEntity;
use EssentialDots\EdMigrate\Domain\Model\Node;
use EssentialDots\EdMigrate\Transformation\TranslateElementWithAllLanguagesTransformation;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\SingletonInterface;
use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Utility\MathUtility;

/**
 * Class TranslationService
 *
 * @package EssentialDots\EdMigrate\Service
 */
class TranslationService implements SingletonInterface {

	/**
	 * @var TranslationService
	 */
	protected static $singletonInstance;

	/**
	 * @var array
	 */
	protected $visitedNodes = array();

	/**
	 * @var array
	 */
	protected $translateNodeCache = array();

	/**
	 * @var array
	 */
	protected $languagesPerPidCache = array();

	/**
	 * @return TranslationService|object
	 */
	public static function getInstance() {
		if (!self::$singletonInstance) {
			self::$singletonInstance = GeneralUtility::makeInstance('TYPO3\\CMS\\Extbase\\Object\\ObjectManager')->get('EssentialDots\\EdMigrate\\Service\\TranslationService');
		}

		return self::$singletonInstance;
	}

	/**
	 * @param AbstractEntity $node
	 * @return bool
	 */
	public function run(AbstractEntity $node) {
		return $this->runTranslateTransformations($node);
	}

	/**
	 * @param AbstractEntity $node
	 * @return bool
	 */
	protected function runTranslateTransformations(AbstractEntity $node) {
		$nodeHash = $node->_getTableName() . '-' . $node->getUid();
		if ($this->visitedNodes[$nodeHash]) {
			return TRUE;
		}
		$this->visitedNodes[$nodeHash] = TRUE;

		$this->translateNode($node);

		return TRUE;
	}

	/**
	 * @param AbstractEntity $node
	 * @return array
	 */
	protected function translateNode(AbstractEntity $node) {
		$nodeHash = $node->_getTableName() . '-' . $node->getUid();
		if (isset($this->translateNodeCache[$nodeHash])) {
			return $this->translateNodeCache[$nodeHash];
		}
		$result = array();
		$this->translateNodeCache[$nodeHash] = &$result;

		$tableName = $node->_getTableName();
		if (!empty($GLOBALS['TCA'][$tableName]['ctrl']['transForeignTable'])) {
			$tableName = $GLOBALS['TCA'][$tableName]['ctrl']['transForeignTable'];
		}

		if (($lanuageField = $GLOBALS['TCA'][$tableName]['ctrl']['languageField'])) {
			$languageGetter = 'get' . GeneralUtility::underscoredToUpperCamelCase($lanuageField);
			$languageSetter = 'set' . GeneralUtility::underscoredToUpperCamelCase($lanuageField);
			$languageHas = 'has' . GeneralUtility::underscoredToUpperCamelCase($lanuageField);

			if ($node->_getTableName() === 'pages' || $node->$languageHas()) {
				/** @var \TYPO3\CMS\Extbase\Object\ObjectManagerInterface $objectManager */
				$objectManager = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance('TYPO3\\CMS\\Extbase\\Object\\ObjectManager');
				/** @var \EssentialDots\EdMigrate\Domain\Repository\NodeRepository $nodeRepository */
				$nodeRepository = $objectManager->get('EssentialDots\\EdMigrate\\Domain\\Repository\\NodeRepository');
				/** @var \EssentialDots\EdMigrate\Persistence\PersistenceSession $persistenceSession */
				$persistenceSession = $objectManager->get('EssentialDots\\EdMigrate\\Persistence\\PersistenceSession');

				if ($node->_getTableName() === 'pages' || (int) $node->$languageGetter() <= 0) {
					$pid = $node->_getTableName() === 'pages' ? $node->getUid() : $node->getPid();
					$languageUids = $this->getLanguagesPerPid($pid);
					if ($node->_getTableName() !== 'pages') {
						$oldLanguage = (int) $node->$languageGetter();
						$node->$languageSetter(0);
					} else {
						$oldLanguage = 0;
					}

					if ($node->hasTxLanguagevisibilityVisibility()) {
						$languageVisibility = unserialize($node->getTxLanguagevisibilityVisibility());
					}

					foreach ($languageUids as $languageUid) {
						$transOrigPointerField = $GLOBALS['TCA'][$tableName]['ctrl']['transOrigPointerField'];
						$hiddenField = $GLOBALS['TCA'][$tableName]['ctrl']['enablecolumns']['disabled'];
						$nodeTranslationEl = $nodeRepository->findBy(
							$tableName,
							$transOrigPointerField . ' = ' . $node->getUid() . ' AND ' . $lanuageField . ' = ' . $languageUid . BackendUtility::deleteClause($tableName),
							1
						);
						if (!$nodeTranslationEl) {
							$r = $node->_getRow();
							unset($r['uid']);
							$r[$transOrigPointerField] = $node->getUid();
							$r['sys_language_uid'] = $languageUid;

							/** @var Node $nodeTranslationEl */
							$nodeTranslationEl = GeneralUtility::makeInstance('EssentialDots\\EdMigrate\\Domain\\Model\\Node', $tableName, $r);
							$persistenceSession->registerEntity($tableName, $nodeTranslationEl->getUid(), $nodeTranslationEl);
							echo ' \- ' . $node->getPid() . ' - ' . $node->getUid() . ' - ' . $languageUid . ' [' . $nodeTranslationEl->getUid() . ']' . PHP_EOL;
						}

						if (is_array($languageVisibility) && isset($languageVisibility[$languageUid])) {
							$hiddenFieldSetter = 'set' . ucfirst($hiddenField);
							$hiddenFieldGetter = 'get' . ucfirst($hiddenField);
							switch ($languageVisibility[$languageUid]) {
								case 'yes':
									// forced to YES
									$nodeTranslationEl->$hiddenFieldSetter($node->$hiddenFieldGetter());
									break;
								case 'no':
									// forced to NO
									$nodeTranslationEl->$hiddenFieldSetter(1);
									break;
								case 't':
									// if translated
									if (!MathUtility::canBeInterpretedAsInteger($nodeTranslationEl->getUid())) {
										$nodeTranslationEl->$hiddenFieldSetter($oldLanguage === -1 ? 0 : 1);
									}
									break;
								case 'f':
									// if translation in fallback
									if (!MathUtility::canBeInterpretedAsInteger($nodeTranslationEl->getUid())) {
										$nodeTranslationEl->$hiddenFieldSetter($oldLanguage === -1 ? 0 : 1);
									}
									break;
								case '-':
									// same as default
								default:
									// copy default language
									$nodeTranslationEl->$hiddenFieldSetter($node->$hiddenFieldGetter());
							}
						}

						$result[(string) $languageUid] = $nodeTranslationEl->getUid();

						$transformations = MigrationService::getInstance()->getTransformations(TranslateElementWithAllLanguagesTransformation::class);
						$traverseConfigurations = array();
						foreach ($transformations as $transformation) {
							/** @var TranslateElementWithAllLanguagesTransformation $transformation */
							$relationFields = $transformation->getRelationFields();
							$childTableName = $transformation->getChildTableName();
							$parentTableName = $transformation->getParentTableName();
							$whereClause = $transformation->getWhereClause();

							if (
								$node->_getTableName() === $parentTableName &&
								($whereClause === NULL || $whereClause->evaluate($node))
							) {
								foreach ($relationFields as $relationField) {
									list($propertyName, $flexFieldPath) = GeneralUtility::trimExplode(':', $relationField, TRUE, 2);
									$traverseConfigurations[] = array($childTableName, $propertyName, $flexFieldPath);
								}
							}
						}

						foreach ($traverseConfigurations as $traverseConfiguration) {
							list($childTableName, $propertyName, $flexFieldPath) = $traverseConfiguration;
							$getter = 'get' . ucfirst($propertyName);
							$setter = 'set' . ucfirst($propertyName);

							// take the relations from the original as translations can have wrong relations sometimes!
							if ($flexFieldPath) {
//								$relationUids = $nodeTranslationEl->$getter($flexFieldPath);
								$relationUids = $node->$getter($flexFieldPath);
							} else {
//								$relationUids = $nodeTranslationEl->$getter();
								$relationUids = $node->$getter();
							}
							$relationUids = GeneralUtility::intExplode(',', $relationUids, TRUE);
							$translationUids = array();

							// $childTransOrigPointerField = $GLOBALS['TCA'][$childTableName]['ctrl']['transOrigPointerField'];
							$childLanuageField = $GLOBALS['TCA'][$childTableName]['ctrl']['languageField'];
							$childLanuageFieldGetter = 'get' . GeneralUtility::underscoredToUpperCamelCase($childLanuageField);
							foreach ($relationUids as $relationUid) {
								$relatedNode = $nodeRepository->findBy($childTableName, 'uid = ' . $relationUid . BackendUtility::deleteClause($childTableName), 1);
								if (!$relatedNode) {
									continue;
								}
								$translationUid = $relatedNode->getUid();
								if ($childLanuageField && $relatedNode->$childLanuageFieldGetter() != $languageUid) {
									// get node translation uid!
									$translations = $this->translateNode($relatedNode);
									if (is_array($translations) && $translations[(string)$languageUid]) {
										$translationUid = $translations[(string)$languageUid];
									}
								}
								$translationUids[] = $translationUid;
							}
							if (strpos(implode(',', $translationUids), 'NEW') !== FALSE) {
								echo ' !!!!!!!!!!!!! ' . $childTableName . '-' . $propertyName . '-' . $flexFieldPath . ': ' . implode(',', $translationUids) . PHP_EOL;
							}
							if ($flexFieldPath) {
								$nodeTranslationEl->$setter($flexFieldPath, implode(',', $translationUids));
							} else {
								$nodeTranslationEl->$setter(implode(',', $translationUids));
							}
						}
					}
				} else {
					$translatedNodes = $nodeRepository->findBy($tableName, $GLOBALS['TCA'][$tableName]['ctrl']['transOrigPointerField'] . ' = ' . (int) $node->getUid() . BackendUtility::deleteClause($tableName));
					foreach ($translatedNodes as $translatedNode) {
						if ($translatedNode->$languageHas()) {
							$result[(string) $translatedNode->$languageGetter()] = $translatedNode->getUid();
						}
					}

				}
			}
		}

		return $this->translateNodeCache[$nodeHash];
	}

	/**
	 * @param $pid
	 * @return array
	 */
	protected function getLanguagesPerPid($pid) {
		$cacheKey = $pid;
		if (empty($this->languagesPerPidCache[$cacheKey])) {
			$this->languagesPerPidCache[$cacheKey] = array();

			$pR = $this->getDatabase()->exec_SELECTgetSingleRow(
				'GROUP_CONCAT(sys_language_uid) as languages',
				'pages_language_overlay',
				'pid = ' . (int) $pid . BackendUtility::deleteClause('pages_language_overlay')
			);
			if ($pR) {
				$this->languagesPerPidCache[$cacheKey] = GeneralUtility::intExplode(',', $pR['languages'], TRUE);
			}
		}

		return $this->languagesPerPidCache[$cacheKey];
	}

	/**
	 * @return \TYPO3\CMS\Core\Database\DatabaseConnection
	 */
	protected function getDatabase() {
		$result = $GLOBALS['TYPO3_DB'];

		if (ExtensionManagementUtility::isLoaded('ed_scale')) {
			/** @var \EssentialDots\EdScale\Database\DatabaseConnection $result */
			$result = $result->getConnectionByName('default');
		}

		return $result;
	}
}