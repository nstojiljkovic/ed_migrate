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
use EssentialDots\EdMigrate\Brancher\BrancherInterface;
use EssentialDots\EdMigrate\Domain\Model\AbstractEntity;
use EssentialDots\EdMigrate\Transformation\TransformationInterface;
use TYPO3\CMS\Core\SingletonInterface;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Class MigrationService
 *
 * @package EssentialDots\EdMigrate\Service
 */
class MigrationService implements SingletonInterface {

	/**
	 * @var \TYPO3\CMS\Extbase\Object\ObjectManagerInterface
	 * @inject
	 */
	protected $objectManager;

	/**
	 * @var BrancherInterface[]
	 */
	protected $branchers = array();

	/**
	 * @var TransformationInterface[]
	 */
	protected $tranformations = array();

	/**
	 * @var AbstractEntity[]
	 */
	protected $rootNodes = array();

	/**
	 * @var MigrationService
	 */
	protected static $singletonInstance;

	/**
	 * @return MigrationService|object
	 */
	public static function getInstance() {
		if (!self::$singletonInstance) {
			self::$singletonInstance = GeneralUtility::makeInstance('TYPO3\\CMS\\Extbase\\Object\\ObjectManager')->get('EssentialDots\\EdMigrate\\Service\\MigrationService');
		}

		return self::$singletonInstance;
	}

	/**
	 * @param AbstractEntity $rootNode
	 * @return void
	 */
	public function addRootNode(AbstractEntity $rootNode) {
		$this->rootNodes[] = $rootNode;
	}

	/**
	 * @param TransformationInterface $transformation
	 */
	public function addTransformation(TransformationInterface $transformation) {
		$this->tranformations[] = $transformation;
	}

	/**
	 * @param string $class
	 * @return TransformationInterface[]
	 */
	public function getTransformations($class) {
		$result = array();
		foreach ($this->tranformations as $transformation) {
			if ($transformation instanceof $class) {
				$result[] = $transformation;
			}
		}

		return $result;
	}

	/**
	 * @param BrancherInterface $brancher
	 */
	public function addBrancher(BrancherInterface $brancher) {
		$this->branchers[] = $brancher;
	}

	/**
	 * @return bool
	 */
	public function run() {
		$traverseStack = $this->rootNodes;
		$traverseHash = array(spl_object_hash(reset($this->rootNodes)) => TRUE);
		while (($currentNode = array_pop($traverseStack))) {
			foreach ($this->branchers as &$brancher) {
				foreach ($brancher->getChildren($currentNode) as &$childNode) {
					$childSplObjectHash = spl_object_hash($childNode);
					// add child to the stack only if it's not already included!
					// prevent dead loops
					if (!array_key_exists($childSplObjectHash, $traverseHash) || !$traverseHash[$childSplObjectHash]) {
						$traverseHash[$childSplObjectHash] = TRUE;
						array_push($traverseStack, $childNode);
					}
				}
			}
			foreach ($this->tranformations as &$transformation) {
				$transformation->run($currentNode);
			}
			//unset($traverseHash[spl_object_hash($currentNode)]);
		}
	}
}
