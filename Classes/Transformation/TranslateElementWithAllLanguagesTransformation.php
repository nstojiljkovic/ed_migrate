<?php
namespace EssentialDots\EdMigrate\Transformation;
use EssentialDots\EdMigrate\Domain\Model\AbstractEntity;
use EssentialDots\EdMigrate\Expression\ExpressionInterface;
use EssentialDots\EdMigrate\Service\TranslationService;

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
 * Class TranslateElementWithAllLanguagesTransformation
 *
 * @package EssentialDots\EdMigrate\Transformation
 */
class TranslateElementWithAllLanguagesTransformation implements TransformationInterface {

	/**
	 * @var array
	 */
	protected $relationFields;

	/**
	 * @var string
	 */
	protected $childTableName;

	/**
	 * @var string
	 */
	protected $parentTableName;

	/**
	 * @var ExpressionInterface
	 */
	protected $whereExpression;

	/**
	 * @param array $relationFields
	 * @param $childTableName
	 * @param string $parentTableName
	 * @param ExpressionInterface|NULL $whereExpression
	 */
	public function __construct(array $relationFields, $childTableName, $parentTableName = '*', ExpressionInterface $whereExpression = NULL) {
		$this->relationFields = $relationFields;
		$this->childTableName = $childTableName;
		$this->parentTableName = $parentTableName;
		$this->whereExpression = $whereExpression;
	}

	/**
	 * @param AbstractEntity $node
	 * @return bool
	 */
	public function run(AbstractEntity $node) {
		return TranslationService::getInstance()->run($node);
	}

	/**
	 * @return array
	 */
	public function getRelationFields() {
		return $this->relationFields;
	}

	/**
	 * @return string
	 */
	public function getChildTableName() {
		return $this->childTableName;
	}

	/**
	 * @return string
	 */
	public function getParentTableName() {
		return $this->parentTableName;
	}

	/**
	 * @return ExpressionInterface
	 */
	public function getWhereExpression() {
		return $this->whereExpression;
	}
}