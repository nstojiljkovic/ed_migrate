<?php
namespace EssentialDots\EdMigrate\Persistence;
use EssentialDots\EdMigrate\Domain\Model\AbstractEntity;
use EssentialDots\EdMigrate\Persistence\Mapper\DataMapFactory;
use TYPO3\CMS\Core\DataHandling\TableColumnType;
use TYPO3\CMS\Core\SingletonInterface;
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
 * Class PersistenceSession
 *
 * @package EssentialDots\EdMigrate\Persistence
 */
class PersistenceSession implements SingletonInterface {

	/**
	 * @var int
	 */
	public static $newRecordCounter = 1;

	/**
	 * @var array
	 */
	protected $entities = array();

	/**
	 * @var array
	 */
	protected $entitiesByTableAndId = array();

	/**
	 * @param $tableName
	 * @param $id
	 * @param AbstractEntity $entity
	 * @return void
	 */
	public function registerEntity($tableName, $id, AbstractEntity &$entity) {
		if ($this->getRegisteredEntity($tableName, $id) === FALSE) {
			$this->entities[spl_object_hash($entity)] = array(
				$tableName, $id
			);
			if (!array_key_exists($tableName, $this->entitiesByTableAndId)) {
				$this->entitiesByTableAndId[$tableName] = array(
					(string) $id => $entity
				);
			} else {
				$this->entitiesByTableAndId[$tableName][(string) $id] = $entity;
			}
		}
	}

	/**
	 * @param $tableName
	 * @param $id
	 * @return AbstractEntity|bool
	 */
	public function getRegisteredEntity($tableName, $id) {
		if (array_key_exists($tableName, $this->entitiesByTableAndId) && array_key_exists((string) $id, $this->entitiesByTableAndId[$tableName])) {
			return $this->entitiesByTableAndId[$tableName][(string) $id];
		}

		return FALSE;
	}

	/**
	 * @param AbstractEntity $entity
	 */
	public function unregisterEntity(AbstractEntity &$entity) {
		$splObjectHash = spl_object_hash($entity);
		if (array_key_exists($splObjectHash, $this->entities)) {
			list($tableName, $id) = $this->entities[$splObjectHash];
			if ($this->getRegisteredEntity($tableName, $id) !== FALSE) {
				unset($this->entitiesByTableAndId[$tableName][(string) $id]);
				unset($this->entities[spl_object_hash($entity)]);
			}
		}
	}

	/**
	 * @return void
	 */
	public function persistChangedEntities() {
		$reflectedClass = new \ReflectionClass(GeneralUtility::class);
		$reflectedProperty = $reflectedClass->getProperty('finalClassNameCache');
		$reflectedProperty->setAccessible(TRUE);
		$staticProps = $reflectedClass->getStaticProperties();
		unset($staticProps['finalClassNameCache']['TYPO3\\CMS\\Core\\Database\\ReferenceIndex']);
		$reflectedProperty->setValue('finalClassNameCache', $staticProps['finalClassNameCache']);
		$reflectedProperty->setAccessible(FALSE);

		$oldReferenceIndexClassName = $GLOBALS['TYPO3_CONF_VARS']['SYS']['Objects']['TYPO3\\CMS\\Core\\Database\\ReferenceIndex']['className'];
		$GLOBALS['TYPO3_CONF_VARS']['SYS']['Objects']['TYPO3\\CMS\\Core\\Database\\ReferenceIndex']['className'] = 'EssentialDots\\EdMigrate\\Core\\Database\\DummyReferenceIndex';
		$oldDb = $GLOBALS['TYPO3_DB'];
		$GLOBALS['TYPO3_DB'] = new \EssentialDots\EdMigrate\Core\Database\DatabaseConnection($oldDb, $this);

		/** @var \TYPO3\CMS\Core\DataHandling\DataHandler $tce */
		$tce = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance('EssentialDots\\EdMigrate\\Core\\DataHandling\\DataHandler');
		$tce->process_datamap();
		$tce->enableLogging = FALSE;
		$tce->checkSimilar = FALSE;
		$tce->reverseOrder = TRUE;
		$tce->checkStoredRecords = FALSE;
		$tce->isImporting = TRUE;
		$tce->updateModeL10NdiffData = FALSE;
		$tce->bypassAccessCheckForRecords = TRUE;
		$tce->bypassWorkspaceRestrictions = TRUE;
		$tce->bypassFileHandling = TRUE;
		$tce->removeFilesStore = array();

		$dataMapFactory = DataMapFactory::getInstance();
		$data = array();

		$newEntities = array();

		foreach ($this->entitiesByTableAndId as &$entities) {
			foreach ($entities as &$entity) {
				/** @var AbstractEntity $entity */
				if ($entity->_hasChangedFields()) {
					$tableName = $entity->_getTableName();
					$dataMap = $dataMapFactory->getDataMap($tableName);

					$changedFields = $entity->_getChangedFields();
					$entityData = array();
					foreach ($changedFields as $changedField) {
						$columnMap = $dataMap->getColumnMap($changedField);
						if (!$columnMap) {
							throw new \RuntimeException('Column map could not be found for property ' . $changedField . ' of object ' . get_class($entity) . '.', 11);
						}
//						if ($columnMap->getTypeOfRelation() !== ColumnMap::RELATION_NONE) {
//							throw new \RuntimeException ('Setting relation fields has not been implemented yet.');
//						}
						switch ($columnMap->getType()) {
							case TableColumnType::FLEX:
								// same as regular plain text fields
							default:
								$getter = 'get' . ucfirst($changedField);
								$entityData[$columnMap->getColumnName()] = $entity->$getter();
						}
					}
					$entity->_resetChangedFields();
					if (is_string($entity->getUid()) && substr($entity->getUid(), 0, 3) === 'NEW') {
						$newEntities[$entity->getUid()] = $entity;
					}

					if ($entity->hasPid()) {
						$entityData['pid'] = $entity->getPid();
					}

//					$transOrigPointerField = $GLOBALS['TCA'][$tableName]['ctrl']['transOrigPointerField'];
//					$languageField = $GLOBALS['TCA'][$tableName]['ctrl']['languageField'];

					$data[$tableName][$entity->getUid()] = $entityData;
				}
			}
		}

		$tce->start($data, NULL);
		$tce->process_datamap();

		if ($tce->errorLog) {
			echo 'TCE->errorLog:' . \TYPO3\CMS\Core\Utility\DebugUtility::viewArray($tce->errorLog) . PHP_EOL;
		}

		if (count($newEntities) > 0) {
			// update uids of new records
			foreach ($newEntities as $newId => &$entity) {
				if ($tce->substNEWwithIDs[$newId]) {
					$entity->_setUid($tce->substNEWwithIDs[$newId]);
					$data[$entity->_getTableName()][$entity->getUid()] = $data[$entity->_getTableName()][$newId];
					unset($data[$entity->_getTableName()][$newId]);
				}
			}

			// double check if all 'NEW' references have been substituted with the proper values
			// core doesn't do it without error, for example, group elements are not evaluated at all
			// @see \TYPO3\CMS\Core\DataHandling\DataHandler::checkValueForGroupSelect
			$this->substituteNewIds($data, $tce, 0, 2);
			if (count($data) > 0) {
				$tce->start($data, NULL);
				$tce->process_datamap();
				if ($tce->errorLog) {
					echo 'TCE->errorLog:' . \TYPO3\CMS\Core\Utility\DebugUtility::viewArray($tce->errorLog) . PHP_EOL;
				}
			}
		}

		if ($oldReferenceIndexClassName) {
			$GLOBALS['TYPO3_CONF_VARS']['SYS']['Objects']['TYPO3\\CMS\\Core\\Database\\ReferenceIndex']['className'] = $oldReferenceIndexClassName;
		} else {
			unset($GLOBALS['TYPO3_CONF_VARS']['SYS']['Objects']['TYPO3\\CMS\\Core\\Database\\ReferenceIndex']['className']);
		}
		$GLOBALS['TYPO3_DB'] = $oldDb;
	}

	/**
	 * @param array $arr
	 * @param \TYPO3\CMS\Core\DataHandling\DataHandler $tce
	 * @param int $depth
	 * @param int $limitClearDepth
	 * @return void
	 */
	protected function substituteNewIds(array &$arr, \TYPO3\CMS\Core\DataHandling\DataHandler &$tce, $depth = 0, $limitClearDepth = 9999) {
		$unsetKeys = array();
		foreach ($arr as $k => &$v) {
			if (is_array($v)) {
				$this->substituteNewIds($v, $tce, $depth + 1, $limitClearDepth);
				if (count($v) === 0) {
					$unsetKeys[] = $k;
				}
			} else {
				if (strpos(',' . $v, ',NEW') !== FALSE) {
					// substitute new uid marker with act
					$v = preg_replace_callback(
						'/NEW(\d+)/',
						function($matches) use (&$tce) {
							return $tce->substNEWwithIDs[$matches[0]] ?: $matches[0];
						},
						$v
					);
				} else {
					$unsetKeys[] = $k;
				}
			}
		}
		if ($depth <= $limitClearDepth) {
			foreach ($unsetKeys as $k) {
				unset($arr[$k]);
			}
		}
	}
}