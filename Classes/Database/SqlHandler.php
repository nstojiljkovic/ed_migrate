<?php
namespace EssentialDots\EdMigrate\Database;
use EssentialDots\EdMigrate\Service\DatabaseService;
use TYPO3\CMS\Core\SingletonInterface;
use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Annotation as Extbase;

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

	/**
	 * @var SqlHandler
	 */
	protected static $singletonInstance;

	/**
	 * @return SqlHandler|object
	 */
	public static function getInstance() {
		if (!self::$singletonInstance) {
			// @codingStandardsIgnoreStart
			self::$singletonInstance = GeneralUtility::makeInstance(\TYPO3\CMS\Extbase\Object\ObjectManager::class)->get(\EssentialDots\EdMigrate\Database\SqlHandler::class);
			// @codingStandardsIgnoreEnd
		}

		return self::$singletonInstance;
	}

	/**
	 * Executes the database structure updates.
	 *
	 * @param bool $isRemovalEnabled
	 * @return array
	 */
	public function getStructureUpdateSql($isRemovalEnabled = FALSE) {
		/** @var \TYPO3\CMS\Core\Database\Schema\SqlReader $sqlReader */
		$sqlReader = GeneralUtility::makeInstance(\TYPO3\CMS\Core\Database\Schema\SqlReader::class);
		$sqlStatements = $sqlReader->getCreateTableStatementArray($sqlReader->getTablesDefinitionString());

		/** @var \TYPO3\CMS\Core\Database\Schema\SchemaMigrator $schemaMigrationService */
		$schemaMigrationService = GeneralUtility::makeInstance(\TYPO3\CMS\Core\Database\Schema\SchemaMigrator::class);

		$addCreateChange = $schemaMigrationService->getUpdateSuggestions($sqlStatements, FALSE);
		// Aggregate the per-connection statements into one flat array
		$addCreateChange = array_merge_recursive(...array_values($addCreateChange));

		$relevantKeys = ['add', 'create_table', 'change', 'change_table', 'drop', 'drop_table'];
		$statements = [];

		foreach ($relevantKeys as $k) {
			if (isset($addCreateChange[$k]) && is_array($addCreateChange[$k])) {
				$statements = array_merge($statements, array_values($addCreateChange[$k]));
			}
		}

		if ($isRemovalEnabled) {
			// Difference from current to expected
			$dropRename = $schemaMigrationService->getUpdateSuggestions($sqlStatements, TRUE);
			// Aggregate the per-connection statements into one flat array
			$dropRename = array_merge_recursive(...array_values($dropRename));
			foreach ($relevantKeys as $k) {
				if (isset($dropRename[$k]) && is_array($dropRename[$k])) {
					$statements = array_merge($statements, array_values($dropRename[$k]));
				}
			}
		}

		$statements = array_filter($statements, function ($v) {
			return is_string($v);
		});
		$statements = array_map(function ($s) {
			return str_replace(',', ",\n", $s) . ';';
		}, $statements);

		return $statements;
	}
}