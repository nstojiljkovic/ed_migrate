<?php
namespace EssentialDots\EdMigrate\Domain\Model;
use EssentialDots\EdMigrate\Persistence\Mapper\DataMapFactory;
use EssentialDots\EdMigrate\Persistence\PersistenceSession;
use EssentialDots\EdMigrate\Utility\ArrayUtility;
use TYPO3\CMS\Core\Configuration\FlexForm\FlexFormTools;
use TYPO3\CMS\Core\DataHandling\TableColumnType;
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
 * Class AbstractEntity
 *
 * @package EssentialDots\EdMigrate\Domain\Model
 */
abstract class AbstractEntity {

	/**
	 * Hold the data of current object.
	 *
	 * @var array
	 */
	protected $originalRow;

	/**
	 * Hold the data of current object.
	 *
	 * @var array
	 */
	protected $row;

	/**
	 * @var string
	 */
	protected $tableName;

	/**
	 * @var array
	 */
	protected $changedFields = array();

	/**
	 * @var array
	 */
	protected $flexFieldsData = array();

	/**
	 * @var mixed
	 */
	protected $uid = NULL;

	/**
	 * @param $tableName
	 * @param $row
	 */
	public function __construct($tableName, &$row) {
		$this->tableName = $tableName;
		$this->row = $row;
		$this->originalRow = $row;
		if (!$row['uid']) {
			$this->uid = 'NEW' . PersistenceSession::$newRecordCounter++;
			$propertyNames = array();
			array_map(function ($value) use (&$propertyNames, &$tableName) {
				if ($GLOBALS['TCA'][$tableName]['columns'][$value]) {
					$propertyNames[] = GeneralUtility::underscoredToLowerCamelCase($value);
				}
			}, array_keys($row));
			$this->changedFields = $propertyNames;
		} else {
			$this->uid = $row['uid'];
		}
		unset($row['uid']);
	}

	// @codingStandardsIgnoreStart
	/**
	 * @return bool
	 */
	public function _isDirty() {
		return (is_string($this->uid) && substr($this->uid, 0, 3) === 'NEW') || $this->_hasChangedFields();
	}

	/**
	 * @return array
	 */
	public function _getChangedFields() {
		return $this->changedFields;
	}

	/**
	 * @return void
	 */
	public function _resetChangedFields() {
		$this->changedFields = array();
	}

	/**
	 * @return bool
	 */
	public function _hasChangedFields() {
		return count($this->changedFields) > 0;
	}

	/**
	 * @return string
	 */
	public function _getTableName() {
		return $this->tableName;
	}

	/**
	 * @return array
	 */
	public function _getRow() {
		return $this->row;
	}

	/**
	 * @return array
	 */
	public function _getOriginalRow() {
		return $this->originalRow;
	}

	/**
	 * @param array $originalRow
	 */
	public function _setOriginalRow($originalRow) {
		$this->originalRow = $originalRow;
	}

	/**
	 * @param mixed $uid
	 */
	public function _setUid($uid) {
		$this->uid = $uid;
	}
	// @codingStandardsIgnoreEnd

	/**
	 * @return mixed
	 */
	public function getUid() {
		return $this->uid;
	}

	/**
	 * @param mixed $uid
	 */
	public function setUid($uid) {
		throw new \BadMethodCallException ('You cannot change uid of a record.', 8);
	}

	/**
	 * Magic function to provide get and set Methods for "row" attributes.
	 *
	 *
	 * @param string $function
	 * @param array $args
	 * @access public
	 * @throws \RuntimeException
	 * @throws \BadMethodCallException
	 * @return mixed
	 */
	public function __call($function, $args) {
		$propertyName = lcfirst(substr($function, 3));
		$method = substr($function, 0, 3);

		if ($method !== 'has' && $method !== 'get' && $method !== 'set' && $method !== 'del') {
			throw new \BadMethodCallException ($function . ' is not defined in ' . get_class($this), 9);
		}

		$dataMap = DataMapFactory::getInstance()->getDataMap($this->tableName);

		if (($columnMap = $dataMap->getColumnMap($propertyName)) === NULL) {
			if ($method === 'has') {
				return FALSE;
			} else {
				throw new \BadMethodCallException('Property ' . $propertyName . ' does not exist in ' . get_class($this), 10);
			}
		}

		if ($method === 'has') {
			return TRUE;
		} elseif ($method === 'get') {
//			if ($columnMap->getTypeOfRelation() === ColumnMap::RELATION_NONE) {
				if ($columnMap->getType()->equals(TableColumnType::FLEX)) {
					if (!$this->flexFieldsData[$columnMap->getColumnName()]) {
						$this->flexFieldsData[$columnMap->getColumnName()] = GeneralUtility::xml2array($this->row[$columnMap->getColumnName()]);
						$this->flexFieldsData[$columnMap->getColumnName()] = is_array($this->flexFieldsData[$columnMap->getColumnName()]) ? $this->flexFieldsData[$columnMap->getColumnName()] : array();
					}

					if (count($args) > 0) {
//						/** @var FlexFormTools $flexFormTools */
//						$flexFormTools = GeneralUtility::makeInstance(FlexFormTools::class);
//						return $flexFormTools->getArrayValueByPath($args[0], $this->flexFieldsData[$columnMap->getColumnName()]);
						return ArrayUtility::getInstance()->getByKey($this->flexFieldsData[$columnMap->getColumnName()], $args[0], NULL, '/');
					}

					return $this->flexFieldsData[$columnMap->getColumnName()];
				}

				return $this->row[$columnMap->getColumnName()];
//			} else {
//				throw new \RuntimeException ('Fetching relation fields has not been implemented yet.');
//			}
		} elseif ($method === 'del') {
//			if ($columnMap->getTypeOfRelation() !== ColumnMap::RELATION_NONE) {
//				throw new \RuntimeException ('Setting relation fields has not been implemented yet.');
//			}

			if (!in_array($propertyName, $this->changedFields)) {
				$this->changedFields[] = $propertyName;
			}

			if ($columnMap->getType()->equals(TableColumnType::FLEX)) {
				if (!$this->flexFieldsData[$columnMap->getColumnName()]) {
					$this->flexFieldsData[$columnMap->getColumnName()] = GeneralUtility::xml2array($this->row[$columnMap->getColumnName()]);
					$this->flexFieldsData[$columnMap->getColumnName()] = is_array($this->flexFieldsData[$columnMap->getColumnName()]) ? $this->flexFieldsData[$columnMap->getColumnName()] : array();
					if ($this->flexFieldsData[$columnMap->getColumnName()]['data'] === '') {
//						$this->flexFieldsData[$columnMap->getColumnName()]['data'] = array();
						unset($this->flexFieldsData[$columnMap->getColumnName()]['data']);
					}
				}

				/** @var FlexFormTools $flexFormTools */
				$flexFormTools = GeneralUtility::makeInstance(FlexFormTools::class);

				if (count($args) > 0) {
					ArrayUtility::getInstance()->unsetArrayValueByPath($args[0], $this->flexFieldsData[$columnMap->getColumnName()]);
				} else {
					$this->flexFieldsData[$columnMap->getColumnName()] = array();
				}

				$this->row[$columnMap->getColumnName()] = $flexFormTools->flexArray2Xml($this->flexFieldsData[$columnMap->getColumnName()]);
				return $this->flexFieldsData[$columnMap->getColumnName()];
			} else {
				$this->row[$columnMap->getColumnName()] = NULL;
				return $this->row[$columnMap->getColumnName()];
			}
		} else {
//			if ($columnMap->getTypeOfRelation() !== ColumnMap::RELATION_NONE) {
//				throw new \RuntimeException ('Setting relation fields has not been implemented yet.');
//			}

			if (!in_array($propertyName, $this->changedFields)) {
				$this->changedFields[] = $propertyName;
			}

			if ($columnMap->getType()->equals(TableColumnType::FLEX)) {
				if (!$this->flexFieldsData[$columnMap->getColumnName()]) {
					$this->flexFieldsData[$columnMap->getColumnName()] = GeneralUtility::xml2array($this->row[$columnMap->getColumnName()]);
					$this->flexFieldsData[$columnMap->getColumnName()] = is_array($this->flexFieldsData[$columnMap->getColumnName()]) ? $this->flexFieldsData[$columnMap->getColumnName()] : array();
					if ($this->flexFieldsData[$columnMap->getColumnName()]['data'] === '') {
//						$this->flexFieldsData[$columnMap->getColumnName()]['data'] = array();
						unset($this->flexFieldsData[$columnMap->getColumnName()]['data']);
					}
				}

				/** @var FlexFormTools $flexFormTools */
				$flexFormTools = GeneralUtility::makeInstance(FlexFormTools::class);

				if (count($args) > 1) {
					$flexFormTools->setArrayValueByPath($args[0], $this->flexFieldsData[$columnMap->getColumnName()], $args[1]);
				} else {
					$this->flexFieldsData[$columnMap->getColumnName()] = GeneralUtility::xml2array($args[0]);
					$this->flexFieldsData[$columnMap->getColumnName()] = is_array($this->flexFieldsData[$columnMap->getColumnName()]) ? $this->flexFieldsData[$columnMap->getColumnName()] : array();
				}

				$this->row[$columnMap->getColumnName()] = $flexFormTools->flexArray2Xml($this->flexFieldsData[$columnMap->getColumnName()]);
				return $this->flexFieldsData[$columnMap->getColumnName()];
			} else {
				$this->row[$columnMap->getColumnName()] = $args[0];
				return $this->row[$columnMap->getColumnName()];
			}
		}

		return NULL;
	}
}