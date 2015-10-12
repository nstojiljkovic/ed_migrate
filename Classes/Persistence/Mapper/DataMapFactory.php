<?php
namespace EssentialDots\EdMigrate\Persistence\Mapper;

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
use TYPO3\CMS\Core\SingletonInterface;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Persistence\Generic\Mapper\ColumnMap;

/**
 * Class DataMapFactory
 *
 * @package EssentialDots\EdMigrate\Persistence\Mapper
 */
class DataMapFactory implements SingletonInterface {

	/**
	 * @var array
	 */
	// @codingStandardsIgnoreStart
	protected $_cache = array();
	// @codingStandardsIgnoreEnd

	/**
	 * @var \TYPO3\CMS\Extbase\Object\ObjectManagerInterface
	 * @inject
	 */
	protected $objectManager;

	/**
	 * @var DataMapFactory
	 */
	protected static $singletonInstance;

	/**
	 * @return DataMapFactory|object
	 */
	public static function getInstance() {
		if (!self::$singletonInstance) {
			self::$singletonInstance = GeneralUtility::makeInstance('TYPO3\\CMS\\Extbase\\Object\\ObjectManager')->get('EssentialDots\\EdMigrate\\Persistence\\Mapper\\DataMapFactory');
		}

		return self::$singletonInstance;
	}

	/**
	 * @param $tableName
	 * @return \TYPO3\CMS\Extbase\Persistence\Generic\Mapper\DataMap
	 */
	public function getDataMap($tableName) {
		if (!$this->_cache[$tableName]) {
			$tcaColumnsDefinition = $this->getColumnsDefinition($tableName);

			/** @var $dataMap \TYPO3\CMS\Extbase\Persistence\Generic\Mapper\DataMap */
			$dataMap = $this->objectManager->get(\TYPO3\CMS\Extbase\Persistence\Generic\Mapper\DataMap::class, 'EssentialDots\\EdMigrate\\Domain\\Model\\AbstractEntity', $tableName, NULL, array());

			$controlFieldConfig = array(
				'type' => 'passthrough'
			);

			$columnMap = $this->createColumnMap('uid', 'uid');
			$columnMap = $this->setType($columnMap, $controlFieldConfig);
			$columnMap = $this->setRelations($columnMap, $controlFieldConfig);
			$columnMap = $this->setFieldEvaluations($columnMap, $controlFieldConfig);
			$dataMap->addColumnMap($columnMap);

			$columnMap = $this->createColumnMap('pid', 'pid');
			$columnMap = $this->setType($columnMap, $controlFieldConfig);
			$columnMap = $this->setRelations($columnMap, $controlFieldConfig);
			$columnMap = $this->setFieldEvaluations($columnMap, $controlFieldConfig);
			$dataMap->addColumnMap($columnMap);

			foreach ($tcaColumnsDefinition as $columnName => $columnDefinition) {
				if (isset($columnDefinition['mapOnProperty'])) {
					$propertyName = $columnDefinition['mapOnProperty'];
				} else {
					$propertyName = \TYPO3\CMS\Core\Utility\GeneralUtility::underscoredToLowerCamelCase($columnName);
				}

				$columnMap = $this->createColumnMap($columnName, $propertyName);
				$columnMap = $this->setType($columnMap, $columnDefinition['config']);
				$columnMap = $this->setRelations($columnMap, $columnDefinition['config']);
				$columnMap = $this->setFieldEvaluations($columnMap, $columnDefinition['config']);
				$dataMap->addColumnMap($columnMap);
			}

			$this->_cache[$tableName] = $dataMap;
		}

		return $this->_cache[$tableName];
	}

	/**
	 * Set the table column type
	 *
	 * @param ColumnMap $columnMap
	 * @param array $columnConfiguration
	 * @return ColumnMap
	 */
	protected function setType(ColumnMap $columnMap, $columnConfiguration) {
		$tableColumnType = (isset($columnConfiguration['type'])) ? $columnConfiguration['type'] : NULL;
		$columnMap->setType(\TYPO3\CMS\Core\DataHandling\TableColumnType::cast($tableColumnType));
		$tableColumnSubType = (isset($columnConfiguration['internal_type'])) ? $columnConfiguration['internal_type'] : NULL;
		$columnMap->setInternalType(\TYPO3\CMS\Core\DataHandling\TableColumnSubType::cast($tableColumnSubType));

		return $columnMap;
	}

	/**
	 * This method tries to determine the type of type of relation to other tables and sets it based on
	 * the $TCA column configuration
	 *
	 * @param ColumnMap $columnMap The column map
	 * @param string $columnConfiguration The column configuration from $TCA
	 * @return ColumnMap
	 */
	protected function setRelations(ColumnMap $columnMap, $columnConfiguration) {
		if (isset($columnConfiguration)) {
			if (isset($columnConfiguration['MM'])) {
				$columnMap = $this->setManyToManyRelation($columnMap, $columnConfiguration);
//			} elseif (isset($propertyMetaData['elementType'])) {
//				$columnMap = $this->setOneToManyRelation($columnMap, $columnConfiguration);
//			} elseif (isset($propertyMetaData['type']) && strpbrk($propertyMetaData['type'], '_\\') !== FALSE) {
//				$columnMap = $this->setOneToOneRelation($columnMap, $columnConfiguration);
			} elseif (isset($columnConfiguration['type']) && $columnConfiguration['type'] === 'select' && isset($columnConfiguration['maxitems']) && $columnConfiguration['maxitems'] > 1) {
				$columnMap->setTypeOfRelation(ColumnMap::RELATION_HAS_MANY);
			} else {
				$columnMap->setTypeOfRelation(ColumnMap::RELATION_NONE);
			}

		} else {
			$columnMap->setTypeOfRelation(ColumnMap::RELATION_NONE);
		}
		return $columnMap;
	}

	/**
	 * Sets field evaluations based on $TCA column configuration.
	 *
	 * @param ColumnMap $columnMap The column map
	 * @param NULL|array $columnConfiguration The column configuration from $TCA
	 * @return ColumnMap
	 */
	protected function setFieldEvaluations(ColumnMap $columnMap, array $columnConfiguration = NULL) {
		if (!empty($columnConfiguration['eval'])) {
			$fieldEvaluations = \TYPO3\CMS\Core\Utility\GeneralUtility::trimExplode(',', $columnConfiguration['eval'], TRUE);
			$dateTimeEvaluations = array('date', 'datetime');

			if (!empty(array_intersect($dateTimeEvaluations, $fieldEvaluations)) && !empty($columnConfiguration['dbType'])) {
				$columnMap->setDateTimeStorageFormat($columnConfiguration['dbType']);
			}
		}

		return $columnMap;
	}

	/**
	 * This method sets the configuration for a 1:1 relation based on
	 * the $TCA column configuration
	 *
	 * @param string|ColumnMap $columnMap The column map
	 * @param string $columnConfiguration The column configuration from $TCA
	 * @return ColumnMap
	 */
	protected function setOneToOneRelation(ColumnMap $columnMap, $columnConfiguration) {
		$columnMap->setTypeOfRelation(ColumnMap::RELATION_HAS_ONE);
		$columnMap->setChildTableName($columnConfiguration['foreign_table']);
		$columnMap->setChildTableWhereStatement($columnConfiguration['foreign_table_where']);
		$columnMap->setChildSortByFieldName($columnConfiguration['foreign_sortby']);
		$columnMap->setParentKeyFieldName($columnConfiguration['foreign_field']);
		$columnMap->setParentTableFieldName($columnConfiguration['foreign_table_field']);
		if (is_array($columnConfiguration['foreign_match_fields'])) {
			$columnMap->setRelationTableMatchFields($columnConfiguration['foreign_match_fields']);
		}
		return $columnMap;
	}

	/**
	 * This method sets the configuration for a 1:n relation based on
	 * the $TCA column configuration
	 *
	 * @param string|ColumnMap $columnMap The column map
	 * @param string $columnConfiguration The column configuration from $TCA
	 * @return ColumnMap
	 */
	protected function setOneToManyRelation(ColumnMap $columnMap, $columnConfiguration) {
		$columnMap->setTypeOfRelation(ColumnMap::RELATION_HAS_MANY);
		$columnMap->setChildTableName($columnConfiguration['foreign_table']);
		$columnMap->setChildTableWhereStatement($columnConfiguration['foreign_table_where']);
		$columnMap->setChildSortByFieldName($columnConfiguration['foreign_sortby']);
		$columnMap->setParentKeyFieldName($columnConfiguration['foreign_field']);
		$columnMap->setParentTableFieldName($columnConfiguration['foreign_table_field']);
		if (is_array($columnConfiguration['foreign_match_fields'])) {
			$columnMap->setRelationTableMatchFields($columnConfiguration['foreign_match_fields']);
		}
		return $columnMap;
	}

	/**
	 * This method sets the configuration for a m:n relation based on
	 * the $TCA column configuration
	 *
	 * @param string|ColumnMap $columnMap The column map
	 * @param string $columnConfiguration The column configuration from $TCA
	 * @throws \TYPO3\CMS\Extbase\Persistence\Generic\Exception\UnsupportedRelationException
	 * @return ColumnMap
	 */
	protected function setManyToManyRelation(ColumnMap $columnMap, $columnConfiguration) {
		if (isset($columnConfiguration['MM'])) {
			$columnMap->setTypeOfRelation(ColumnMap::RELATION_HAS_AND_BELONGS_TO_MANY);
			$columnMap->setChildTableName($columnConfiguration['foreign_table']);
			$columnMap->setChildTableWhereStatement($columnConfiguration['foreign_table_where']);
			$columnMap->setRelationTableName($columnConfiguration['MM']);
			if (is_array($columnConfiguration['MM_match_fields'])) {
				$columnMap->setRelationTableMatchFields($columnConfiguration['MM_match_fields']);
			}
			if (is_array($columnConfiguration['MM_insert_fields'])) {
				$columnMap->setRelationTableInsertFields($columnConfiguration['MM_insert_fields']);
			}
			$columnMap->setRelationTableWhereStatement($columnConfiguration['MM_table_where']);
			if (!empty($columnConfiguration['MM_opposite_field'])) {
				$columnMap->setParentKeyFieldName('uid_foreign');
				$columnMap->setChildKeyFieldName('uid_local');
				$columnMap->setChildSortByFieldName('sorting_foreign');
			} else {
				$columnMap->setParentKeyFieldName('uid_local');
				$columnMap->setChildKeyFieldName('uid_foreign');
				$columnMap->setChildSortByFieldName('sorting');
			}
		} else {
			throw new \TYPO3\CMS\Extbase\Persistence\Generic\Exception\UnsupportedRelationException(
				'The given information to build a many-to-many-relation was not sufficient. Check your TCA definitions. mm-relations must have at least a defined "MM" or "foreign_selector".',
				1268817963
			);
		}
		if ($this->getControlSection($columnMap->getRelationTableName()) !== NULL) {
			$columnMap->setRelationTablePageIdColumnName('pid');
		}
		return $columnMap;
	}

	/**
	 * Creates the ColumnMap object for the given columnName and propertyName
	 *
	 * @param string $columnName
	 * @param string $propertyName
	 *
	 * @return ColumnMap
	 */
	protected function createColumnMap($columnName, $propertyName) {
		return $this->objectManager->get(\TYPO3\CMS\Extbase\Persistence\Generic\Mapper\ColumnMap::class, $columnName, $propertyName);
	}

	/**
	 * Returns the TCA ctrl section of the specified table; or NULL if not set
	 *
	 * @param string $tableName An optional table name to fetch the columns definition from
	 * @return array The TCA columns definition
	 */
	protected function getControlSection($tableName) {
		return is_array($GLOBALS['TCA'][$tableName]['ctrl']) ? $GLOBALS['TCA'][$tableName]['ctrl'] : NULL;
	}

	/**
	 * Returns the TCA columns array of the specified table
	 *
	 * @param string $tableName An optional table name to fetch the columns definition from
	 * @return array The TCA columns definition
	 */
	protected function getColumnsDefinition($tableName) {
		return is_array($GLOBALS['TCA'][$tableName]['columns']) ? $GLOBALS['TCA'][$tableName]['columns'] : array();
	}
}
