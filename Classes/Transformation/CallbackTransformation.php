<?php
namespace EssentialDots\EdAaqi\Transformation;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2017 Essential Dots d.o.o. Belgrade
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

use EssentialDots\EdMigrate\Domain\Model\AbstractEntity;
use EssentialDots\EdMigrate\Transformation\TransformationInterface;

/**
 * Class CallbackTransformation
 *
 * @package EssentialDots\EdAaqi\Transformation
 */
class CallbackTransformation implements TransformationInterface {

	/**
	 * @var callable
	 */
	protected $callback;

	/**
	 * UpdateHospitalizationJsonFieldTransformation constructor.
	 * @param callable $callback
	 */
	public function __construct(Callable $callback) {
		$this->callback = $callback;
	}

	/**
	 * @param AbstractEntity $node
	 * @return bool
	 */
	public function run(AbstractEntity $node) {
		try {
			call_user_func($this->callback, $node);
		} catch (\Exception $e) {
			return FALSE;
		}

		return TRUE;
	}
}