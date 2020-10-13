<?php
namespace EssentialDots\EdMigrate\Expression;
use EssentialDots\EdMigrate\Service\DatabaseService;
use TYPO3\CMS\Core\Resource\ResourceFactory;
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
 * Class FileExpression
 *
 * @package EssentialDots\EdMigrate\Expression
 */
abstract class AbstractFileExpression implements ExpressionInterface {

	/**
	 * @param $storage
	 * @param $folder
	 * @param $sha1
	 * @return null|\TYPO3\CMS\Core\Resource\File
	 */
	protected function findExistingFileByStorageFolderAndSha1($storage, $folder, $sha1) {
		$sysFileDeleteClause = DatabaseService::deleteClause('sys_file');
		$escStorage = DatabaseService::getDatabase()->fullQuoteStr($storage, 'sys_file');
		$escFolder = DatabaseService::getDatabase()->escapeStrForLike($folder, 'sys_file');
		$escSha1 = DatabaseService::getDatabase()->fullQuoteStr($sha1, 'sys_file');
		$res = DatabaseService::getDatabase()->sql_query(<<<SQL
			# @tables_used = sys_file;

			SELECT *
			FROM sys_file
			WHERE storage = {$escStorage} AND identifier LIKE '{$escFolder}%' AND sha1 = {$escSha1} {$sysFileDeleteClause}
SQL
		);

		while (($row = DatabaseService::getDatabase()->sql_fetch_assoc($res))) {
			if (preg_match('/' . preg_quote($folder, '/') . '\/([^\/]+)/msU', $row['identifier']) === 1) {
				/** @var \TYPO3\CMS\Core\Resource\ResourceFactory $resourceFactory */
				$resourceFactory = GeneralUtility::makeInstance(ResourceFactory::class);
				$fileResource = $resourceFactory->getFileObjectFromCombinedIdentifier($storage . ':' . $row['identifier']);
				if ($fileResource) {
					return $fileResource;
				}
			}
		}

		return NULL;
	}
}