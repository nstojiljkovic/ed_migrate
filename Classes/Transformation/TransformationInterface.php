<?php
namespace EssentialDots\EdMigrate\Transformation;
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
 * Interface TransformationInterface
 *
 * @package EssentialDots\EdMigrate\Transformation
 */
interface TransformationInterface {

	/**
	 * @param AbstractEntity $node
	 * @return bool
	 */
	public function run(AbstractEntity $node);
}