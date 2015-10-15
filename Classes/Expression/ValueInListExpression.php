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
 * Class ValueInListExpression
 *
 * @package EssentialDots\EdMigrate\Expression
 */
class ValueInListExpression implements ExpressionInterface {

	/**
	 * @var string|ExpressionInterface
	 */
	protected $value;

	/**
	 * @var string|ExpressionInterface
	 */
	protected $list;

	/**
	 * @var string|ExpressionInterface
	 */
	protected $returnIfTrue;

	/**
	 * @var string|ExpressionInterface
	 */
	protected $returnIfFalse;

	/**
	 * @param $value
	 * @param $list
	 * @param $returnIfTrue
	 * @param $returnIfFalse
	 */
	public function __construct($value, $list, $returnIfTrue, $returnIfFalse) {
		$this->value = $value;
		$this->list = $list;
		$this->returnIfTrue = $returnIfTrue;
		$this->returnIfFalse = $returnIfFalse;
	}

	/**
	 * @param AbstractEntity $node
	 * @return string
	 */
	public function evaluate(AbstractEntity $node) {
		$value = $this->value instanceof ExpressionInterface ? $this->value->evaluate($node) : (string) $this->value;
		$list = $this->list instanceof ExpressionInterface ? $this->list->evaluate($node) : (string) $this->list;
		$returnIfTrue = $this->returnIfTrue instanceof ExpressionInterface ? $this->returnIfTrue->evaluate($node) : (string) $this->returnIfTrue;
		$returnIfFalse = $this->returnIfFalse instanceof ExpressionInterface ? $this->returnIfFalse->evaluate($node) : (string) $this->returnIfFalse;

		return in_array($value, GeneralUtility::trimExplode(',', $list)) ? $returnIfTrue : $returnIfFalse;
	}
}