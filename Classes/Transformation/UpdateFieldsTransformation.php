<?php
namespace EssentialDots\EdMigrate\Transformation;
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
 * Class UpdateFieldsTransformation
 *
 * @package EssentialDots\EdMigrate\Transformation
 */
class UpdateFieldsTransformation implements TransformationInterface {

	/**
	 * @var array
	 */
	protected $updateProperties;

	/**
	 * @var string
	 */
	protected $tableName;

	/**
	 * @var ExpressionInterface
	 */
	protected $whereExpression;

	/**
	 * @var array
	 */
	protected $unsetProperties;

	/**
	 * @param $updateProperties
	 * @param $tableName
	 * @param ExpressionInterface $whereExpression
	 * @param $unsetProperties
	 */
	public function __construct($updateProperties, $tableName, ExpressionInterface $whereExpression = NULL, $unsetProperties = NULL) {
		$this->updateProperties = $updateProperties;
		$this->tableName = $tableName;
		$this->whereExpression = $whereExpression;
		$this->unsetProperties = $unsetProperties;
	}

	/**
	 * @param AbstractEntity $node
	 * @return bool
	 */
	public function run(AbstractEntity $node) {
		if ($node->_getTableName() === $this->tableName) {
			if ($this->whereExpression === NULL || $this->whereExpression->evaluate($node)) {
				foreach ($this->updateProperties as $propertyNamePath => $value) {
					$evaluatedValue = $value instanceof ExpressionInterface ? $value->evaluate($node) : (string) $value;
					list($propertyName, $flexFieldPath) = GeneralUtility::trimExplode(':', $propertyNamePath, TRUE, 2);
					$setter = 'set' . ucfirst($propertyName);
					echo '  \- ' . $propertyNamePath . ' => ' . json_encode($evaluatedValue) . PHP_EOL;
					if ($flexFieldPath) {
						$matches = NULL;
						if (is_array($evaluatedValue) && preg_match('/(.*)\/@each\/(.*)$/msU', $flexFieldPath, $matches) === 1) {
							foreach ($evaluatedValue as $k => $v) {
								$node->$setter($matches[1] . '/' . $k . '/' . $matches[2], $v);
							}
						} else {
							$node->$setter($flexFieldPath, is_array($evaluatedValue) ? implode(',', $evaluatedValue) : $evaluatedValue);
						}
					} else {
						$node->$setter($evaluatedValue);
					}
				}
				if (is_array($this->unsetProperties)) {
					foreach ($this->unsetProperties as $propertyNamePath) {
						list($propertyName, $flexFieldPath) = GeneralUtility::trimExplode(':', $propertyNamePath, TRUE, 2);
						$unsetter = 'del' . ucfirst($propertyName);
						echo '  \- ' . $propertyNamePath . ' => ' . $evaluatedValue . PHP_EOL;
						if ($flexFieldPath) {
							$node->$unsetter($flexFieldPath);
						} else {
							$node->$unsetter();
						}
					}

				}
			}
		}

		return TRUE;
	}
}