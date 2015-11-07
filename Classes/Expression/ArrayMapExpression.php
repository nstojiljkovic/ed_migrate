<?php
namespace EssentialDots\EdMigrate\Expression;
use EssentialDots\EdMigrate\Domain\Model\AbstractEntity;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Fluid\View\StandaloneView;

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
 * Class ArrayMapExpression
 *
 * @package EssentialDots\EdMigrate\Expression
 */
class ArrayMapExpression implements ExpressionInterface {

	/**
	 * @var array|string|ExpressionInterface
	 */
	protected $value;

	/**
	 * @var string
	 */
	protected $table;

	/**
	 * @var string|ExpressionInterface
	 */
	protected $transform;

	/**
	 * @param array|string|ExpressionInterface $value
	 * @param string|ExpressionInterface $transform
	 * @param string $table
	 */
	public function __construct($value, $transform, $table = '') {
		$this->value = $value;
		$this->transform = $transform;
		$this->table = $table;
	}

	/**
	 * @param AbstractEntity $node
	 * @return string
	 */
	public function evaluate(AbstractEntity $node) {
		$value = $this->value instanceof ExpressionInterface ? $this->value->evaluate($node) : $this->value;
		$returnArray = is_array($value);
		if (!$returnArray) {
			$value = GeneralUtility::trimExplode(',', $value, TRUE);
		}

		/** @var \TYPO3\CMS\Extbase\Object\ObjectManagerInterface $objectManager */
		$objectManager = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance('TYPO3\\CMS\\Extbase\\Object\\ObjectManager');
		/** @var \EssentialDots\EdMigrate\Domain\Repository\NodeRepository $nodeRepository */
		$nodeRepository = $objectManager->get('EssentialDots\\EdMigrate\\Domain\\Repository\\NodeRepository');

		foreach ($value as &$v) {
			$vNode = $node;
			if ($this->table) {
				$vNode = $nodeRepository->findBy($this->table, 'uid = ' . $this->getDatabase()->fullQuoteStr($v, $this->table), 1);
			}
			if ($vNode) {
				$v = $this->transform instanceof ExpressionInterface ? $this->transform->evaluate($vNode) : (string) $this->transform;
			} else {
				$v = '';
			}
		}

		return $returnArray ? $value : implode(',', $value);
	}

	/**
	 * @return \TYPO3\CMS\Core\Database\DatabaseConnection
	 */
	protected function getDatabase() {
		return $GLOBALS['TYPO3_DB'];
	}
}