<?php

namespace EssentialDots\EdMigrate\Core\Database;

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
 * Class DummyReferenceIndex
 *
 * @package EssentialDots\EdMigrate\Core\Database
 */
class DummyReferenceIndex extends \TYPO3\CMS\Core\Database\ReferenceIndex {

	/**
	 * @param string $tableName
	 * @param int $uid
	 * @param bool|FALSE $testOnly
	 * @return array
	 */
	public function updateRefIndexTable($tableName, $uid, $testOnly = FALSE) {
		return array();
	}

	/**
	 * @param string $tableName
	 * @param int $uid
	 * @return array
	 */
	public function generateRefIndexData($tableName, $uid) {
		return array();
	}

	/**
	 * @param string $table
	 * @param int $uid
	 * @param string $field
	 * @param string $flexPointer
	 * @param int $deleted
	 * @param string $refTable
	 * @param int $refUid
	 * @param string $refString
	 * @param int $sort
	 * @param string $softRefKey
	 * @param string $softRefId
	 * @return array
	 */
	public function createEntryData($table, $uid, $field, $flexPointer, $deleted, $refTable, $refUid, $refString = '', $sort = -1, $softRefKey = '', $softRefId = '') {
		return array();
	}

	/**
	 * @param string $table
	 * @param array $row
	 * @param string $onlyField
	 * @return array
	 */
	public function getRelations($table, $row, $onlyField = '') {
		return array();
	}

	// @codingStandardsIgnoreStart
	/**
	 * Callback function for traversing the FlexForm structure in relation to finding DB references!
	 *
	 * @param array $dsArr Data structure for the current value
	 * @param mixed $dataValue Current value
	 * @param array $PA Additional configuration used in calling function
	 * @param string $structurePath Path of value in DS structure
	 * @see DataHandler::checkValue_flex_procInData_travDS()
	 * @see FlexFormTools::traverseFlexFormXMLData()
	 */
	public function getRelations_flexFormCallBack($dsArr, $dataValue, $PA, $structurePath) {
	}

	/**
	 * @param string $value
	 * @param array $conf
	 * @param int $uid
	 * @return array|bool
	 */
	public function getRelations_procFiles($value, $conf, $uid) {
		return FALSE;
	}

	/**
	 * @param string $value
	 * @param array $conf
	 * @param int $uid
	 * @param string $table
	 * @param string $field
	 * @return array
	 */
	public function getRelations_procDB($value, $conf, $uid, $table = '', $field = '') {
		return array();
	}

	/**
	 * @param string $hash
	 * @param mixed $newValue
	 * @param bool|FALSE $returnDataArray
	 * @param bool|FALSE $bypassWorkspaceAdminCheck
	 * @return bool
	 */
	public function setReferenceValue($hash, $newValue, $returnDataArray = FALSE, $bypassWorkspaceAdminCheck = FALSE) {
		return FALSE;
	}

	/**
	 * @param array $refRec
	 * @param array $itemArray
	 * @param string $newValue
	 * @param array $dataArray
	 * @param string $flexPointer
	 * @return bool
	 */
	public function setReferenceValue_dbRels($refRec, $itemArray, $newValue, &$dataArray, $flexPointer = '') {
		return FALSE;
	}

	/**
	 * @param array $refRec
	 * @param array $itemArray
	 * @param string $newValue
	 * @param array $dataArray
	 * @param string $flexPointer
	 * @return bool
	 */
	public function setReferenceValue_fileRels($refRec, $itemArray, $newValue, &$dataArray, $flexPointer = '') {
		return FALSE;
	}

	/**
	 * @param array $refRec
	 * @param array $softref
	 * @param string $newValue
	 * @param array $dataArray
	 * @param string $flexPointer
	 * @return bool
	 */
	public function setReferenceValue_softreferences($refRec, $softref, $newValue, &$dataArray, $flexPointer = '') {
		return FALSE;
	}
	// @codingStandardsIgnoreEnd

	/**
	 * @param array $configuration
	 * @return bool
	 */
	public function isReferenceField(array $configuration) {
		return FALSE;
	}

	/**
	 * @param string $msg
	 */
	public function error($msg) {
	}

	/**
	 * @param bool $testOnly
	 * @param bool|FALSE $cliEcho
	 * @return array
	 */
	public function updateIndex($testOnly, $cliEcho = FALSE) {
		return array();
	}
}