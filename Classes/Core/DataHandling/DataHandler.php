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
use TYPO3\CMS\Backend\Utility\BackendUtility;

/**
 * Class DataHandler
 *
 * @package EssentialDots\EdMigrate\Core\DataHandling
 */
class DataHandler extends \TYPO3\CMS\Core\DataHandling\DataHandler {

	/**
	 * @param string $table
	 * @param int $id
	 * @param int|string $perms
	 * @return bool
	 */
	public function doesRecordExist($table, $id, $perms) {
		if ($this->bypassAccessCheckForRecords) {
			return is_array(BackendUtility::getRecord($table, (int) $id, 'uid'));
		}

		return parent::doesRecordExist($table, $id, $perms);
	}

	/**
	 * @param string $table
	 * @param int $id
	 * @param bool|FALSE $data
	 * @param null $hookObjectsArr
	 * @return bool
	 */
	public function checkRecordUpdateAccess($table, $id, $data = FALSE, $hookObjectsArr = NULL) {
		return TRUE;
	}

	/**
	 * Do the actual clear cache
	 *
	 * @return void
	 */
	protected function processClearCacheQueue() {
		// do nothing
	}
}