<?php
namespace EssentialDots\EdMigrate\Service;

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
use TYPO3\CMS\Core\Resource\DuplicationBehavior;

/**
 * Class LocalLangService
 *
 * @package EssentialDots\EdMigrate\Service
 */
class LocalLangService implements SingletonInterface {

	/**
	 * @var LocalLangService
	 */
	protected static $singletonInstance;

	/**
	 * @return LocalLangService|object
	 */
	public static function getInstance() {
		if (!self::$singletonInstance) {
			self::$singletonInstance = GeneralUtility::makeInstance('TYPO3\\CMS\\Extbase\\Object\\ObjectManager')->get('EssentialDots\\EdMigrate\\Service\\LocalLangService');
		}

		return self::$singletonInstance;
	}

	/**
	 * @param $source
	 * @param $destination
	 * @param bool $autoDeleteProcessedFilesAndFolders
	 * @return array
	 */
	public function migrateLocallangTranslationFile($source, $destination, $autoDeleteProcessedFilesAndFolders = TRUE) {

		/** @var \TYPO3\CMS\Core\Resource\ResourceFactory $resourceFactory */
		$resourceFactory = \TYPO3\CMS\Core\Resource\ResourceFactory::getInstance();
		$storageUid = 0;
		$storage = $resourceFactory->getStorageObject($storageUid);
		$l10nFolder = $storage->getFolder('typo3conf/l10n');
		if (!$l10nFolder) {
			throw new \RuntimeException ('Could not open folder: "typo3conf/l10n" .');
		}
		$languageFolders = $l10nFolder->getSubfolders();
		$processedFiles = array();
		foreach ($languageFolders as $languageFolder) {
			$lang = $languageFolder->getName();
			$sourceFilePath = dirname($source);
			$sourceFileName = $lang . '.' . basename($source);
			$destinationFilePath = dirname($destination);
			$destinationFileName = $lang . '.' . basename($destination);
			if ($languageFolder->hasFolder($sourceFilePath)) {
				$sourceFolder = $languageFolder->getSubfolder($sourceFilePath);
				if (!$sourceFolder) {
					throw new \RuntimeException ('Could not open source folder : "' . $sourceFilePath . '" .');
				}
				if ($sourceFolder->hasFile($sourceFileName)) {
					$sourceTranslationFileCombinedIdentifier = $sourceFolder->getCombinedIdentifier() . $sourceFileName;
					$sourceTranslationFile = $resourceFactory->getFileObjectFromCombinedIdentifier($sourceTranslationFileCombinedIdentifier);
					if ($sourceTranslationFile) {
						$destinationFolder = $languageFolder->createFolder($destinationFilePath);
						$destinationTranslationFile = $sourceTranslationFile->copyTo($destinationFolder, $destinationFileName, DuplicationBehavior::REPLACE);
						if ($destinationTranslationFile) {
							$processedFiles[$sourceTranslationFile->getIdentifier()] = $destinationTranslationFile->getIdentifier();
							if ($autoDeleteProcessedFilesAndFolders) {
								$sourceTranslationFile->delete();
							}
						}
					}
				}
				if ($autoDeleteProcessedFilesAndFolders) {
					$sourceRootDirectoryName = reset(explode(DIRECTORY_SEPARATOR, $sourceFilePath));
					if ($languageFolder->hasFolder($sourceRootDirectoryName)) {
						$sourceRootDirectory = $languageFolder->getSubfolder($sourceRootDirectoryName);
						// deletes source directory if it and its subdirectories don't have any files
						if ($sourceRootDirectory->getFileCount(array(), TRUE) === 0) {
							$sourceRootDirectory->delete();
						}
					}
				}
			}
		}
		return $processedFiles;
	}
}