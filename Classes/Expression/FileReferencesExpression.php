<?php
namespace EssentialDots\EdMigrate\Expression;
use EssentialDots\EdMigrate\Domain\Model\AbstractEntity;
use EssentialDots\EdMigrate\Domain\Model\Node;
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
 * Class FileReferencesExpression
 *
 * @package EssentialDots\EdMigrate\Expression
 */
class FileReferencesExpression extends AbstractFileExpression {

	/**
	 * @var string|ExpressionInterface
	 */
	protected $parentUid;

	/**
	 * @var string|ExpressionInterface
	 */
	protected $parentPid;

	/**
	 * @var string|ExpressionInterface
	 */
	protected $parentTableName;

	/**
	 * @var string|ExpressionInterface
	 */
	protected $parentFieldName;

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
	 * @param $parentUid
	 * @param $parentPid
	 * @param $parentTableName
	 * @param $parentFieldName
	 * @param $sourceFile
	 * @param null $sourceFolder
	 * @param null $targetFolder
	 */
	public function __construct($parentUid, $parentPid, $parentTableName, $parentFieldName, $sourceFile, $sourceFolder = NULL, $targetFolder = NULL) {
		$this->parentUid = $parentUid;
		$this->parentPid = $parentPid;
		$this->parentTableName = $parentTableName;
		$this->parentFieldName = $parentFieldName;
		$this->sourceFile = $sourceFile;
		$this->sourceFolder = $sourceFolder;
		$this->targetFolder = $targetFolder;
	}

	/**
	 * @param AbstractEntity $node
	 * @return string
	 */
	public function evaluate(AbstractEntity $node) {
		$sourceFile = $this->sourceFile instanceof ExpressionInterface ? $this->sourceFile->evaluate($node) : (string) $this->sourceFile;
		if (!$sourceFile) {
			return 0;
		}

		$parentUid = $this->parentUid instanceof ExpressionInterface ? $this->parentUid->evaluate($node) : (string) $this->parentUid;
		$parentPid = $this->parentPid instanceof ExpressionInterface ? $this->parentPid->evaluate($node) : (string) $this->parentPid;
		$parentTableName = $this->parentTableName instanceof ExpressionInterface ? $this->parentTableName->evaluate($node) : (string) $this->parentTableName;
		$parentFieldName = $this->parentFieldName instanceof ExpressionInterface ? $this->parentFieldName->evaluate($node) : (string) $this->parentFieldName;
		$sourceFolder = $this->sourceFolder instanceof ExpressionInterface ? $this->sourceFolder->evaluate($node) : (string) $this->sourceFolder;
		$targetFolder = $this->targetFolder instanceof ExpressionInterface ? $this->targetFolder->evaluate($node) : (string) $this->targetFolder;

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
					/** @var Node $entity */
					$entity = GeneralUtility::makeInstance('EssentialDots\\EdMigrate\\Domain\\Model\\Node', 'sys_file_reference', array());
					$entity->setUidLocal($targetFile->getUid());
					$entity->setSysLanguageUid(0);
					$entity->setL10nParent(0);
					$entity->setHidden(0);
					$entity->setUidForeign($parentUid);
					$entity->setTablenames($parentTableName);
					$entity->setFieldname($parentFieldName);
					$entity->setPid($parentPid);
					$entity->setTableLocal('sys_file');
					/** @var \TYPO3\CMS\Extbase\Object\ObjectManager $objectManager */
					$objectManager = GeneralUtility::makeInstance('TYPO3\\CMS\\Extbase\\Object\\ObjectManager');
					/** @var \EssentialDots\EdMigrate\Persistence\PersistenceSession $persistenceSession */
					$persistenceSession = $objectManager->get('EssentialDots\\EdMigrate\\Persistence\\PersistenceSession');
					$persistenceSession->registerEntity('sys_file_reference', $entity->getUid(), $entity);
					return $entity->getUid();
				}
			}
		}

		return '0';
	}
}