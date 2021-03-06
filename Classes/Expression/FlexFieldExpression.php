<?php
namespace EssentialDots\EdMigrate\Expression;
use EssentialDots\EdMigrate\Domain\Model\AbstractEntity;
use EssentialDots\EdMigrate\Utility\ArrayUtility;

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
 * Class FlexFieldExpression
 *
 * @package EssentialDots\EdMigrate\Expression
 */
class FlexFieldExpression implements ExpressionInterface {

	/**
	 * @var string|ExpressionInterface
	 */
	protected $flexPropertyName;

	/**
	 * @var string|ExpressionInterface
	 */
	protected $flexFieldPath;

	/**
	 * @param $flexPropertyName
	 * @param $flexFieldPath
	 */
	public function __construct($flexPropertyName, $flexFieldPath) {
		$this->flexPropertyName = $flexPropertyName;
		$this->flexFieldPath = $flexFieldPath;
	}

	/**
	 * @param AbstractEntity $node
	 * @return string
	 */
	public function evaluate(AbstractEntity $node) {
		$flexPropertyName = $this->flexPropertyName instanceof ExpressionInterface ? $this->flexPropertyName->evaluate($node) : (string) $this->flexPropertyName;
		$flexFieldPath = $this->flexFieldPath instanceof ExpressionInterface ? $this->flexFieldPath->evaluate($node) : (string) $this->flexFieldPath;
		$getter = 'get' . ucfirst($flexPropertyName);

		$matches = NULL;
		if (preg_match('/(.*)\/@each\/(.*)$/msU', $flexFieldPath, $matches) === 1) {
			$value = array();
			if (is_array($arr = $node->$getter($matches[1]))) {
				foreach ($arr as $k => &$v) {
					$value[$k] = ArrayUtility::getInstance()->getByKey($v, $matches[2], NULL, '/');
				}
			}
		} else {
			$value = $node->$getter($flexFieldPath);
		}

		return $value;
	}
}