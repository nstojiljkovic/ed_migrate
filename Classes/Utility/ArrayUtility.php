<?php
namespace EssentialDots\EdMigrate\Utility;

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
 * Class ArrayUtility
 *
 * @package EssentialDots\EdMigrate\Utility
 */
class ArrayUtility {

	/**
	 * @var string
	 */
	protected $whitespace = '[\\x20\\t\\r\\n\\f]';

	/**
	 * @var array
	 */
	protected $matchExpr;

	/**
	 * @var ArrayUtility
	 */
	protected static $instance;

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->matchExpr = array(
			'CHILD' =>
				'/^:(first|last|nth|nth-last)-(child)(?:\\(' . $this->whitespace .
				'*(even|odd|(([+-]|)(\\d*)n|)'  . $this->whitespace . '*(?:([+-]|)' . $this->whitespace .
				'*(\\d+)|))' . $this->whitespace . '*\\)|)/i',
		);
	}

	/**
	 * @return ArrayUtility
	 */
	public static function getInstance() {
		if (!self::$instance) {
			self::$instance = new ArrayUtility();
		}

		return self::$instance;
	}

	/**
	 * @param        $array
	 * @param        $key
	 * @param null $default
	 * @param string $delimiter
	 *
	 * @return mixed
	 */
	public static function get($array, $key, $default = NULL, $delimiter = '.') {
		return self::getInstance()->getByKey($array, $key, $default, $delimiter);
	}

	/**
	 * Get an item from an array using "dot" notation.
	 *
	 * @param $array
	 * @param $key
	 * @param null $default
	 * @param string $delimiter
	 * @return null|string
	 */
	public function getByKey($array, $key, $default = NULL, $delimiter = '.') {
		if (is_null($key)) {
			return $array;
		}

		// To retrieve the array item using dot syntax, we'll iterate through
		// each segment in the key and look for that value. If it exists, we
		// will return it, otherwise we will set the depth of the array and
		// look for the next segment.
		$segments = preg_split('~(?<!\\\)' . preg_quote($delimiter, '~') . '~', $key);
		while (($segment = array_shift($segments))) {
			$matches = NULL;
			if ($segment === '@each' || preg_match('/^@each\((.*)\|(.*)\)$/', $segment, $matches) === 1) {
				if (is_array($array)) {
					$result = array();
					foreach ($array as $item) {
						$v = $this->getByKey($item, implode($delimiter, $segments), $default, $delimiter);
						if ($matches) {
							$result[] = $matches[1] . $v . $matches[2];
						} else {
							$result[] = $v;
						}

					}
					return implode(',', $result);
				} else {
					return NULL;
				}
			}
			$matches = NULL;
			if (preg_match($this->matchExpr['CHILD'], $segment, $matches) === 1) {
				list($segmentCopy, $type, $what, $argument, $first, $last) = $this->preFilterChildren($matches);
				if (is_array($array)) {
					$result = array();
					$filteredArray = $this->filterChildren($array, $type, $first, $last);
					foreach ($filteredArray as $item) {
						$result[] = $this->getByKey($item, implode($delimiter, $segments), $default, $delimiter);

					}
					return implode(',', $result);
				} else {
					return NULL;
				}
			} elseif (!is_array($array) or !array_key_exists($segment, $array)) {
				return $default;
			}

			$array = $array[$segment];
		}

		return $array;
	}

	/**
	 * matches from matchExpr["CHILD"]
	 *    1 type (only|nth|...)
	 *    2 what (child|of-type)
	 *    3 argument (even|odd|\d*|\d*n([+-]\d+)?|...)
	 *    4 xn-component of xn+y argument ([+-]?\d*n|)
	 *    5 sign of xn-component
	 *    6 x of xn-component
	 *    7 sign of y-component
	 *    8 y of y-component
	 *
	 * @param $matches
	 *
	 * @return mixed
	 * @throws \Exception
	 */
	protected function preFilterChildren($matches) {
		$matches[1] = strtolower($matches[1]);

		if (substr($matches[1], 0, 3) === 'nth') {
			// nth-* requires argument
			if (!$matches[3]) {
				throw new \Exception($matches[0]);
			}

			// numeric x and y parameters for Expr.filter.CHILD
			// remember that false/true cast respectively to 0/1
			// @codingStandardsIgnoreStart
			$matches[4] = +($matches[4] ? $matches[5] . ($matches[6] ?: 1) : 2 * ($matches[3] === 'even' || $matches[3] === 'odd'));
			$matches[5] = +(($matches[7] . $matches[8]) ?: intval($matches[3] === 'odd'));
			// @codingStandardsIgnoreEnd

			// other types prohibit arguments
		} elseif ($matches[3]) {
			throw new \Exception($matches[0]);
		}

		return $matches;
	}

	/**
	 * @param $array
	 * @param string $type (first|last|nth|nth-last)
	 * @param $first
	 * @param $last
	 *
	 * @return array
	 */
	protected function filterChildren($array, $type, $first, $last) {
		$forward = substr($type, -4) !== 'last';

		// Shortcut for :nth-*(n)
		if ($first === 1 && $last === 0) {
			return $forward ? $array : array_reverse($array);
		}

		$values = array_values($array);
		$arrLength = count($values);
		$result = array();

		for ($i = 0; $i < $arrLength; $i++) {
			if ($first == 0) {
				if ($forward ? $last - 1 == $i : $arrLength - $last == $i) {
					$result[$i] = $values[$i];
				}
			} else {
				$diff = $forward ? $i + 1 : $arrLength - $i;
				$diff -= $last;
				if ($diff === $first ?: ($diff % $first === 0 && $diff / $first >= 0)) {
					$result[$i] = $values[$i];
				}
			}
		}

		$forward ? ksort($result) : krsort($result);
		return array_values($result);
	}
}