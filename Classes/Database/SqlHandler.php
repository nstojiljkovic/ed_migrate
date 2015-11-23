<?php
namespace EssentialDots\EdMigrate\Database;
use EssentialDots\EdMigrate\Domain\Model\AbstractEntity;
use EssentialDots\EdMigrate\Expression\ExpressionInterface;
use TYPO3\CMS\Core\SingletonInterface;
use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;
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
 * Class RelationBrancher
 *
 * @package EssentialDots\EdMigrate\Brancher
 */
class SqlHandler implements SingletonInterface {

	const UPDATE_TYPES = 'add,change,create_table,change_table';
	const REMOVE_TYPES = 'drop,drop_table,clear_table';

	/**
	 * @var \TYPO3\CMS\Install\Service\SqlSchemaMigrationService
	 * @inject
	 */
	protected $sqlHandler = NULL;

	/**
	 * @var \TYPO3\CMS\Extbase\Object\ObjectManager
	 * @inject
	 */
	protected $objectManager = NULL;

	/**
	 * @var array
	 */
	protected $loadedExtensions;

	/**
	 * @var array
	 */
	protected $consideredTypes;

	/**
	 * @var SqlHandler
	 */
	protected static $singletonInstance;

	/**
	 * @return SqlHandler|object
	 */
	public static function getInstance() {
		if (!self::$singletonInstance) {
			self::$singletonInstance = GeneralUtility::makeInstance('TYPO3\\CMS\\Extbase\\Object\\ObjectManager')->get('EssentialDots\\EdMigrate\\Database\\SqlHandler');
		}

		return self::$singletonInstance;
	}

	/**
	 * Constructor
	 */
	public function __construct() {
		/* @var $packageManager \TYPO3\CMS\Core\Package\PackageManager */
		$packageManager = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance('TYPO3\\CMS\\Core\\Package\\PackageManager');
		$this->loadedExtensions = array_keys($packageManager->getActivePackages());

		$this->consideredTypes = $this->getUpdateTypes();
	}

	/**
	 * Adds considered types.
	 *
	 * @param array $consideredTypes
	 * @return void
	 * @see getUpdateSql()
	 */
	public function addConsideredTypes(array $consideredTypes) {
		$this->consideredTypes = array_unique(
			array_merge($this->consideredTypes, $consideredTypes)
		);
	}

	/**
	 * Executes the database structure updates.
	 *
	 * @param array $arguments Optional arguemtns passed to this action
	 * @param boolean $allowKeyModifications Whether to allow key modifications
	 * @return array
	 */
	public function getStructureUpdateSql($isRemovalEnabled = FALSE, $allowKeyModifications = FALSE) {
		$database = $GLOBALS['TYPO3_DB'];

		$changes = $this->sqlHandler->getUpdateSuggestions(
			$this->getStructureDifferencesForUpdate($database, $allowKeyModifications)
		);

		if ($isRemovalEnabled) {
			// Disable the delete prefix, thus tables and fields can be removed directly:
			$this->sqlHandler->setDeletedPrefixKey('');
			// Add types considered for removal:
			$this->addConsideredTypes($this->getRemoveTypes());
			// Merge update suggestions:
			$removals = $this->sqlHandler->getUpdateSuggestions(
				$this->getStructureDifferencesForRemoval($database, $allowKeyModifications),
				'remove'
			);
			$changes = array_merge($changes, $removals);
		}

		$statements = array();

		// Concatenates all statements:
		foreach ($this->consideredTypes as $consideredType) {
			if (isset($changes[$consideredType]) && is_array($changes[$consideredType])) {
				$statements += $changes[$consideredType];
			}
		}

		return $statements;
	}

	/**
	 * Removes key modifications that will cause errors.
	 *
	 * @param array $differences The differneces to be cleaned up
	 * @return array The cleaned differences
	 */
	protected function removeKeyModifications(array $differences) {
		$differences = $this->unsetSubKey($differences, 'extra', 'keys', 'whole_table');
		$differences = $this->unsetSubKey($differences, 'diff', 'keys');

		return $differences;
	}

	/**
	 * Unsets a subkey in a given differences array.
	 *
	 * @param array $differences
	 * @param string $type e.g. extra or diff
	 * @param string $subKey e.g. keys or fields
	 * @param string $exception e.g. whole_table that stops the removal
	 * @return array
	 */
	protected function unsetSubKey(array $differences, $type, $subKey, $exception = '') {
		if (isset($differences[$type])) {
			foreach ($differences[$type] as $table => $information) {
				$isException = ($exception && isset($information[$exception]) && $information[$exception]);
				if (isset($information[$subKey]) && $isException === FALSE) {
					unset($differences[$type][$table][$subKey]);
				}
			}
		}

		return $differences;
	}

	/**
	 * Gets the differences in the database structure by comparing
	 * the current structure with the SQL definitions of all extensions
	 * and the TYPO3 core in t3lib/stddb/tables.sql.
	 *
	 * This method searches for fields/tables to be added/updated.
	 *
	 * @param string $database
	 * @param boolean $allowKeyModifications Whether to allow key modifications
	 * @return array The database statements to update the structure
	 */
	protected function getStructureDifferencesForUpdate($database, $allowKeyModifications = FALSE) {
		$differences = $this->sqlHandler->getDatabaseExtra(
			$this->getDefinedFieldDefinitions(),
			$this->sqlHandler->getFieldDefinitions_database($database)
		);

		if (!$allowKeyModifications) {
			$differences = $this->removeKeyModifications($differences);
		}

		return $differences;
	}

	/**
	 * Gets the differences in the database structure by comparing
	 * the current structure with the SQL definitions of all extensions
	 * and the TYPO3 core in t3lib/stddb/tables.sql.
	 *
	 * This method searches for fields/tables to be removed.
	 *
	 * @param string $database
	 * @param boolean $allowKeyModifications Whether to allow key modifications
	 * @return array The database statements to update the structure
	 */
	protected function getStructureDifferencesForRemoval($database, $allowKeyModifications = FALSE) {
		$differences = $this->sqlHandler->getDatabaseExtra(
			$this->sqlHandler->getFieldDefinitions_database($database),
			$this->getDefinedFieldDefinitions()
		);

		if (!$allowKeyModifications) {
			$differences = $this->removeKeyModifications($differences);
		}

		return $differences;
	}

	/**
	 * Gets the defined field definitions from the ext_tables.sql files.
	 *
	 * @return array The accordant definitions
	 */
	protected function getDefinedFieldDefinitions() {
		$content = '';

		$content .= implode(chr(10), $this->getAllRawStructureDefinitions());
		if (class_exists('\TYPO3\CMS\Core\Cache\DatabaseSchemaService')) {
			// Add SQL content coming from the caching framework
			$databaseSchemaService = new \TYPO3\CMS\Core\Cache\DatabaseSchemaService();
			$content .= chr(10) . $databaseSchemaService->getCachingFrameworkRequiredDatabaseSchema();
		} elseif (class_exists('\TYPO3\CMS\Core\Cache\Cache')) {
			// Add SQL content coming from the caching framework
			$content .= chr(10) . \TYPO3\CMS\Core\Cache\Cache::getDatabaseTableDefinitions();
		}

		if (class_exists('\TYPO3\CMS\Core\Category\CategoryRegistry')) {
			// Add SQL content coming from the category registry
			$content .= chr(10) . \TYPO3\CMS\Core\Category\CategoryRegistry::getInstance()->getDatabaseTableDefinitions();
		}

		if (method_exists($this->sqlHandler, 'getFieldDefinitions_fileContent')) {
			$result = $this->sqlHandler->getFieldDefinitions_fileContent($content);
		} else {
			$result = $this->sqlHandler->getFieldDefinitions_sqlContent($content);
		}

		return $result;
	}

	/**
	 * Gets all structure definitions of extensions the TYPO3 Core.
	 *
	 * @return array All structure definitions
	 */
	protected function getAllRawStructureDefinitions() {
		$rawDefinitions = array();
		if (ExtensionManagementUtility::isLoaded('core')) {
			$rawDefinitions[] = file_get_contents(ExtensionManagementUtility::extPath('core', 'ext_tables.sql'));
		} else {
			$rawDefinitions[] = file_get_contents(PATH_t3lib . 'stddb/tables.sql');
		}

		foreach ($this->loadedExtensions as $key => $extension) {
			if (is_array($extension) && $extension['ext_tables.sql']) {
				$rawDefinitions[] = file_get_contents($extension['ext_tables.sql']);
			} elseif (
				ExtensionManagementUtility::isLoaded($key) &&
				file_exists(ExtensionManagementUtility::extPath($key, 'ext_tables.sql'))
			) {
				$rawDefinitions[] = file_get_contents(ExtensionManagementUtility::extPath($key, 'ext_tables.sql'));
			} elseif (
				ExtensionManagementUtility::isLoaded($extension) &&
				file_exists(ExtensionManagementUtility::extPath($extension, 'ext_tables.sql'))
			) {
				$rawDefinitions[] = file_get_contents(ExtensionManagementUtility::extPath($extension, 'ext_tables.sql'));
			}
		}

		return $rawDefinitions;
	}

	/**
	 * Gets the defined update types.
	 *
	 * @return array
	 */
	protected function getUpdateTypes() {
		return \TYPO3\CMS\Core\Utility\GeneralUtility::trimExplode(',', self::UPDATE_TYPES, TRUE);
	}

	/**
	 * Gets the defined remove types.
	 *
	 * @return array
	 */
	protected function getRemoveTypes() {
		return \TYPO3\CMS\Core\Utility\GeneralUtility::trimExplode(',', self::REMOVE_TYPES, TRUE);
	}
}