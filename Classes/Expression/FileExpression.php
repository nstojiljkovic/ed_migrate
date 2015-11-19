<?php
namespace EssentialDots\EdMigrate\Expression;
use EssentialDots\EdMigrate\Domain\Model\AbstractEntity;
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
class FileExpression extends AbstractFileExpression {

	/**
	 * @var string|ExpressionInterface
	 */
	protected $sourceFile;

	/**
	 * @var string|ExpressionInterface
	 */
	protected $sourceFolder;

	/**
	 * @var string|ExpressionInterface
	 */
	protected $targetFolder;

	/**
	 * @param $sourceFile
	 * @param null $sourceFolder
	 * @param null $targetFolder
	 */
	public function __construct($sourceFile, $sourceFolder = NULL, $targetFolder = NULL) {
		$this->sourceFile = $sourceFile;
		$this->sourceFolder = $sourceFolder;
		$this->targetFolder = $targetFolder;
	}

	/**
	 * @param AbstractEntity $node
	 * @return string
	 */
	public function evaluate(AbstractEntity $node) {
		$sourceFile = $this->sourceFile instanceof ExpressionInterface ? $this->sourceFile->evaluate($node) : (string)$this->sourceFile;
		if (!$sourceFile) {
			return '';
		}

		$sourceFolder = $this->sourceFolder instanceof ExpressionInterface ? $this->sourceFolder->evaluate($node) : (string)$this->sourceFolder;
		$targetFolder = $this->targetFolder instanceof ExpressionInterface ? $this->targetFolder->evaluate($node) : (string)$this->targetFolder;

		if (is_array($sourceFile)) {
			$result = array();
			foreach ($sourceFile as $k => &$v) {
				$result[$k] = $this->evaluateSingleFile($sourceFolder, $v, $targetFolder);
			}

			return $result;
		}

		return $this->evaluateSingleFile($sourceFolder, $sourceFile, $targetFolder);
	}

	/**
	 * @param $sourceFolder
	 * @param $sourceFile
	 * @param $targetFolder
	 * @return string
	 * @throws \Exception
	 * @throws \TYPO3\CMS\Core\Resource\Exception\InsufficientFolderAccessPermissionsException
	 */
	protected function evaluateSingleFile($sourceFolder, $sourceFile, $targetFolder) {
		/** @var \TYPO3\CMS\Core\Resource\ResourceFactory $resourceFactory */
		$resourceFactory = \TYPO3\CMS\Core\Resource\ResourceFactory::getInstance();
		$sourceFileIdentifier = $sourceFolder . $sourceFile;
		try {
			$sourceFileResource = $resourceFactory->getFileObjectFromCombinedIdentifier($sourceFileIdentifier);
		} catch (\Exception $e) {
			$sourceFileResource = FALSE;
		}
		if ($sourceFileResource) {
			$matches = NULL;
			$decodedUid = $targetFolder;
			if (preg_match('/(\d+):\/(.*)/', $targetFolder, $matches) === 1) {
				$storageUid = $matches[1];
				$requiredFullPath = $matches[2];
				$fullPathArr = GeneralUtility::trimExplode(DIRECTORY_SEPARATOR, $matches[2], TRUE);
				$sanitizedFullPathArr = array();

				$storage = $resourceFactory->getStorageObject($storageUid);
				$driverObject = $resourceFactory->getDriverObject($storage->getDriverType(), $storage->getConfiguration());
				$driverObject->processConfiguration();

				foreach ($fullPathArr as $segment) {
					$sanitizedSegment = $driverObject->sanitizeFileName($segment);
					if ($sanitizedSegment) {
						$sanitizedFullPathArr[] = $sanitizedSegment;
					}
				}

				$sanitizedFullPath = implode(DIRECTORY_SEPARATOR, $sanitizedFullPathArr);

				if ($sanitizedFullPath === $requiredFullPath || $sanitizedFullPath . DIRECTORY_SEPARATOR === $requiredFullPath) {
					$decodedUid = $storageUid . ':/' . $sanitizedFullPath;

					$folder = $storage->getRootLevelFolder();
					foreach ($fullPathArr as $segment) {
						if (!$folder->hasFolder($segment)) {
							$newFolder = $driverObject->createFolder($segment, $folder->getIdentifier(), FALSE);
							$folder = $storage->getFolder($newFolder);
						} else {
							$folder = $folder->getSubfolder($segment);
						}
					}
				}

				$targetFolderResource = $resourceFactory->getFolderObjectFromCombinedIdentifier($decodedUid);
				if (($targetFile = $this->findExistingFileByStorageFolderAndSha1($storageUid, '/' . $sanitizedFullPath, $sourceFileResource->getSha1())) === NULL) {
					$targetFile = $sourceFileResource->copyTo($targetFolderResource);
				}

				if ($targetFile) {
					return (string) $targetFile->getUid();
				}
			}
		}

		return '0';
	}
}