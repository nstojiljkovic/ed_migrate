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
 * Class RunCliTaskTransformation
 *
 * @package EssentialDots\EdMigrate\Transformation
 */
class RunCliTaskTransformation implements TransformationInterface {

	/**
	 * @var ExpressionInterface
	 */
	protected $command;

	/**
	 * @var string
	 */
	protected $tableName;

	/**
	 * @var ExpressionInterface
	 */
	protected $whereClause;

	/**
	 * @param ExpressionInterface $command
	 * @param string $tableName
	 * @param ExpressionInterface $whereClause
	 */
	public function __construct($command, $tableName, ExpressionInterface $whereClause = NULL) {
		$this->command = $command;
		$this->tableName = $tableName;
		$this->whereClause = $whereClause;
	}

	/**
	 * @param AbstractEntity $node
	 * @return bool
	 */
	public function run(AbstractEntity $node) {
		if ($node->_getTableName() === $this->tableName) {
			if ($this->whereClause === NULL || $this->whereClause->evaluate($node)) {
				$command = 'typo3/cli_dispatch.phpsh ' . $this->command->evaluate($node);
				echo 'Running command: ' . PHP_EOL . $command . PHP_EOL;
				$command = PATH_site . $command;
				$returnVar = NULL;
				passthru($command, $returnVar);
				if ($returnVar !== 0) {
					exit($returnVar);
				}
			}
		}

		return TRUE;
	}
}