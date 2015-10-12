<?php
namespace EssentialDots\EdMigrate\Core\Database;
use EssentialDots\EdScale\Database\Exception\MultipleConnectionsInQueryException;
use PHPSQL\Parser;
use TYPO3\CMS\Core\Database\PreparedStatement;
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
 * Class DatabaseConnection
 *
 * @package EssentialDots\EdMigrate\Core\Database
 */
class DatabaseConnection {

	/**
	 * @var \TYPO3\CMS\Core\Database\DatabaseConnection
	 */
	protected $defaultDatabaseConnection = NULL;

	/**
	 * @var \EssentialDots\EdMigrate\Persistence\PersistenceSession
	 */
	protected $persistenceSession = NULL;

	/**
	 * @param \TYPO3\CMS\Core\Database\DatabaseConnection $defaultDatabaseConnection
	 * @param \EssentialDots\EdMigrate\Persistence\PersistenceSession $persistenceSession
	 */
	public function __construct(\TYPO3\CMS\Core\Database\DatabaseConnection $defaultDatabaseConnection, \EssentialDots\EdMigrate\Persistence\PersistenceSession $persistenceSession) {
		$this->defaultDatabaseConnection = $defaultDatabaseConnection;
		$this->persistenceSession = $persistenceSession;
	}

	/**
	 * Creates and executes a SELECT SQL-statement AND gets a result set and returns an array with a single record in.
	 * LIMIT is automatically set to 1 and can not be overridden.
	 *
	 * @param string $select_fields List of fields to select from the table.
	 * @param string $from_table Table(s) from which to select.
	 * @param string $where_clause Optional additional WHERE clauses put in the end of the query. NOTICE: You must escape values in this argument with $this->fullQuoteStr() yourself!
	 * @param string $groupBy Optional GROUP BY field(s), if none, supply blank string.
	 * @param string $orderBy Optional ORDER BY field(s), if none, supply blank string.
	 * @param bool $numIndex If set, the result will be fetched with sql_fetch_row, otherwise sql_fetch_assoc will be used.
	 * @return array|FALSE|NULL Single row, FALSE on empty result, NULL on error
	 */
	// @codingStandardsIgnoreStart
	public function exec_SELECTgetSingleRow($select_fields, $from_table, $where_clause, $groupBy = '', $orderBy = '', $numIndex = FALSE) {
		$matches = NULL;
		if (
			!$groupBy &&
			!$orderBy &&
			!$numIndex &&
			preg_match('/^\s*uid\s*=\s*(\d+)\s*$/', $where_clause, $matches) === 1 &&
			($entity = $this->persistenceSession->getRegisteredEntity($from_table, $matches[1]))
		) {
			// just use cache!
			return $entity->_getRow();
		}

		return $this->defaultDatabaseConnection->exec_SELECTgetSingleRow($select_fields, $from_table, $where_clause, $groupBy, $orderBy, $numIndex);
	}
	// @codingStandardsIgnoreEnd

	/**
	 * @param $name
	 * @param $arguments
	 * @return mixed|NULL
	 */
	public function __call($name, $arguments) {
		if ($this->defaultDatabaseConnection && is_callable(array($this->defaultDatabaseConnection, $name))) {
			return call_user_func_array(array($this->defaultDatabaseConnection, $name), $arguments);
		}

		return NULL;
	}
}