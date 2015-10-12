<?php
namespace EssentialDots\EdMigrate\Persistence;
use EssentialDots\EdMigrate\Domain\Model\AbstractEntity;
use EssentialDots\EdMigrate\Persistence\Mapper\DataMapFactory;
use TYPO3\CMS\Core\DataHandling\TableColumnType;
use TYPO3\CMS\Core\SingletonInterface;
use TYPO3\CMS\Extbase\Persistence\Generic\Mapper\ColumnMap;
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
					$id => $entity
				);
			} else {
				$this->entitiesByTableAndId[$tableName][$id] = $entity;
			}
		}
	}

	/**
	 * @param $tableName
	 * @param $id
	 * @return AbstractEntity|bool
	 */
	public function getRegisteredEntity($tableName, $id) {
		if (array_key_exists($tableName, $this->entitiesByTableAndId) && array_key_exists($id, $this->entitiesByTableAndId[$tableName])) {
			return $this->entitiesByTableAndId[$tableName][$id];
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
				unset($this->entitiesByTableAndId[$tableName][$id]);
				unset($this->entities[spl_object_hash($entity)]);
			}
		}
	}

	/**
	 * @return void
	 */
	public function persistChangedEntities() {
		$oldReferenceIndexClassName = $GLOBALS['TYPO3_CONF_VARS']['SYS']['Objects']['TYPO3\\CMS\\Core\\Database\\ReferenceIndex']['className'];
		$GLOBALS['TYPO3_CONF_VARS']['SYS']['Objects']['TYPO3\\CMS\\Core\\Database\\ReferenceIndex']['className'] = 'EssentialDots\\EdMigrate\\Core\\Database\\DummyReferenceIndex';
		$oldDb = $GLOBALS['TYPO3_DB'];
		$GLOBALS['TYPO3_DB'] = new \EssentialDots\EdMigrate\Core\Database\DatabaseConnection($oldDb, $this);

		/** @var \TYPO3\CMS\Core\DataHandling\DataHandler $tce */
		$tce = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance('TYPO3\CMS\Core\DataHandling\DataHandler');
		$tce->process_datamap();
		$tce->enableLogging = FALSE;
		$tce->stripslashes_values = FALSE;
		$tce->checkSimilar = FALSE;
		$tce->reverseOrder = TRUE;
		$tce->checkStoredRecords = FALSE;
		$tce->isImporting = TRUE;
		$tce->updateModeL10NdiffData = FALSE;

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
							throw new \RuntimeException ('Column map could not be found for property ' . $changedField . ' of object ' . get_class($entity) . '.');
						}
						if ($columnMap->getTypeOfRelation() !== ColumnMap::RELATION_NONE) {
							throw new \RuntimeException ('Setting relation fields has not been implemented yet.');
						}
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

		$tce->removeFilesStore = array();
		$tce->start($data, NULL);
		$tce->process_datamap();

		if ($tce->errorLog) {
			echo 'TCE->errorLog:' . \TYPO3\CMS\Core\Utility\DebugUtility::viewArray($tce->errorLog) . PHP_EOL;
		}

		// update uids of new records
		foreach ($newEntities as $newId => &$entity) {
			if ($tce->substNEWwithIDs[$newId]) {
				$entity->_setUid($tce->substNEWwithIDs[$newId]);
			}
		}

		if ($oldReferenceIndexClassName) {
			$GLOBALS['TYPO3_CONF_VARS']['SYS']['Objects']['TYPO3\\CMS\\Core\\Database\\ReferenceIndex']['className'] = $oldReferenceIndexClassName;
		} else {
			unset($GLOBALS['TYPO3_CONF_VARS']['SYS']['Objects']['TYPO3\\CMS\\Core\\Database\\ReferenceIndex']['className']);
		}
		$GLOBALS['TYPO3_DB'] = $oldDb;
	}
}