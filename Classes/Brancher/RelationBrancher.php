<?php
namespace EssentialDots\EdMigrate\Brancher;
use EssentialDots\EdMigrate\Domain\Model\AbstractEntity;
use EssentialDots\EdMigrate\Expression\ExpressionInterface;
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
class RelationBrancher implements BrancherInterface {

	/**
	 * @var string
	 */
	protected $allowedParentTables;

	/**
	 * @var string
	 */
	protected $tableName;

	/**
	 * @var string|ExpressionInterface
	 */
	protected $whereExpression;

	/**
	 * @param $allowedParentTables
	 * @param $tableName
	 * @param $whereExpression
	 */
	public function __construct($allowedParentTables, $tableName, $whereExpression) {
		$this->allowedParentTables = $allowedParentTables;
		$this->tableName = $tableName;
		$this->whereExpression = $whereExpression;
	}

	/**
	 * @param AbstractEntity $node
	 * @return AbstractEntity[]
	 */
	public function getChildren(AbstractEntity $node) {
		if (!in_array($node->_getTableName(), GeneralUtility::trimExplode(',', $this->allowedParentTables))) {
			return array();
		}

		/** @var \TYPO3\CMS\Extbase\Object\ObjectManagerInterface $objectManager */
		$objectManager = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance('TYPO3\\CMS\\Extbase\\Object\\ObjectManager');
		/** @var \EssentialDots\EdMigrate\Domain\Repository\NodeRepository $nodeRepository */
		$nodeRepository = $objectManager->get('EssentialDots\\EdMigrate\\Domain\\Repository\\NodeRepository');

		$whereExpression = $this->whereExpression instanceof ExpressionInterface ? $this->whereExpression->evaluate($node) : (string) $this->whereExpression;
		return $nodeRepository->findBy($this->tableName, $whereExpression);
	}
}