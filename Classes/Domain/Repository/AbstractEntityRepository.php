<?php
namespace EssentialDots\EdMigrate\Domain\Repository;
use TYPO3\CMS\Core\SingletonInterface;
use TYPO3\CMS\Core\Utility\ClassNamingUtility;
use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use EssentialDots\EdMigrate\Domain\Model\AbstractEntity;
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
 * Class AbstractRepository
 *
 * @package EssentialDots\EdMigrate\Domain\Repository
 */
abstract class AbstractEntityRepository implements SingletonInterface {

	/**
	 * @var \EssentialDots\EdMigrate\Persistence\PersistenceSession
	 * @inject
	 */
	protected $persistenceSession;

	/**
	 * @param $tableName
	 * @param string $whereClause
	 * @param int $limit
	 * @return AbstractEntity[]|AbstractEntity
	 */
	public function findBy($tableName, $whereClause = '', $limit = -1) {
		$res = $this->getDatabase()->exec_SELECTquery(
			'*',
			$tableName,
			$whereClause,
			'',
			'',
			$limit > 1 ? (string) $limit : ''
		);

		$result = array();

		while (($row = $this->getDatabase()->sql_fetch_assoc($res))) {
			if (($entity = $this->persistenceSession->getRegisteredEntity($tableName, $row['uid'])) === FALSE) {
				$entityClassName = ClassNamingUtility::translateRepositoryNameToModelName(get_class($this));
				/** @var AbstractEntity $entity */
				$entity = GeneralUtility::makeInstance($entityClassName, $tableName, $row);
				$this->persistenceSession->registerEntity($tableName, $row['uid'], $entity);
			}

			$result[] = $entity;
		}

		if ($limit === 1) {
			$result = reset($result);
		}

		return $result;
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