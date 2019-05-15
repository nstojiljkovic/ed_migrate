<?php

namespace EssentialDots\EdMigrate\Service;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2019 Essential Dots d.o.o. Belgrade
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

use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Class DatabaseService.php
 *
 * @package EssentialDots\EdMigrate\Service
 */
class DatabaseService {
	/**
	 * Returns the WHERE clause " AND NOT [tablename].[deleted-field]" if a deleted-field
	 * is configured in $GLOBALS['TCA'] for the tablename, $table
	 * This function should ALWAYS be called in the backend for selection on tables which
	 * are configured in $GLOBALS['TCA'] since it will ensure consistent selection of records,
	 * even if they are marked deleted (in which case the system must always treat them as non-existent!)
	 * In the frontend a function, ->enableFields(), is known to filter hidden-field, start- and endtime
	 * and fe_groups as well. But that is a job of the frontend, not the backend. If you need filtering
	 * on those fields as well in the backend you can use ->BEenableFields() though.
	 *
	 * @param string $table Table name present in $GLOBALS['TCA']
	 * @param string $tableAlias Table alias if any
	 * @return string WHERE clause for filtering out deleted records, eg " AND tablename.deleted=0
	 */
	public static function deleteClause($table, $tableAlias = '') {
		if (empty($GLOBALS['TCA'][$table]['ctrl']['delete'])) {
			return '';
		}
		$expressionBuilder = GeneralUtility::makeInstance(ConnectionPool::class)
			->getQueryBuilderForTable($table)
			->expr();
		return ' AND ' . $expressionBuilder->eq(
				($tableAlias ?: $table) . '.' . $GLOBALS['TCA'][$table]['ctrl']['delete'],
				0
			);
	}

	/**
	 * @return \TYPO3\CMS\Typo3DbLegacy\Database\DatabaseConnection
	 */
	public static function getDatabase() {
		// @extensionScannerIgnoreLine
		$result = $GLOBALS['TYPO3_DB'];

		if (ExtensionManagementUtility::isLoaded('ed_scale')) {
			/** @var \EssentialDots\EdScale\Database\DatabaseConnection $result */
			$result = $result->getConnectionByName('default');
		}

		return $result;
	}
}