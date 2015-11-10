<?php
namespace EssentialDots\EdMigrate\Expression;
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
 * Class DatabaseExpression
 *
 * @package EssentialDots\EdMigrate\Expression
 */
class DatabaseExpression implements ExpressionInterface {

	/**
	 * @var string|ExpressionInterface
	 */
	protected $select;

	/**
	 * @var string|ExpressionInterface
	 */
	protected $from;

	/**
	 * @var string|ExpressionInterface
	 */
	protected $where;

	/**
	 * @param string|ExpressionInterface $select
	 * @param string|ExpressionInterface $from
	 * @param string|ExpressionInterface $where
	 */
	public function __construct($select, $from, $where) {
		$this->select = $select;
		$this->from = $from;
		$this->where = $where;
	}

	/**
	 * @param AbstractEntity $node
	 * @return string
	 */
	public function evaluate(AbstractEntity $node) {
		$select = $this->select instanceof ExpressionInterface ? $this->select->evaluate($node) : (string) $this->select;
		$from = $this->from instanceof ExpressionInterface ? $this->from->evaluate($node) : (string) $this->from;
		$where = $this->where instanceof ExpressionInterface ? $this->where->evaluate($node) : (string) $this->where;

		$row = $this->getDatabase()->exec_SELECTgetSingleRow(
			$select,
			$from,
			$where
		);

		return is_array($row) ? $row[$select] : NULL;
	}

	/**
	 * @return \TYPO3\CMS\Core\Database\DatabaseConnection
	 */
	protected function getDatabase() {
		return $GLOBALS['TYPO3_DB'];
	}
}