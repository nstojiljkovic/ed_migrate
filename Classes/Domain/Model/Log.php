<?php
namespace EssentialDots\EdMigrate\Domain\Model;

/***************************************************************
 *
 *  Copyright notice
 *
 *  (c) 2015
 *
 *  All rights reserved
 *
 *  This script is part of the TYPO3 project. The TYPO3 project is
 *  free software; you can redistribute it and/or modify
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
 * Log
 */
class Log extends \TYPO3\CMS\Extbase\DomainObject\AbstractEntity {

	/**
	 * version
	 *
	 * @var string
	 */
	protected $version = '';

	/**
	 * startTime
	 *
	 * @var \DateTime
	 */
	protected $startTime = NULL;

	/**
	 * endTime
	 *
	 * @var string
	 */
	protected $endTime = '';

	/**
	 * namespace
	 *
	 * @var string
	 */
	protected $namespace = '';

	/**
	 * Returns the version
	 *
	 * @return string $version
	 */
	public function getVersion() {
		return $this->version;
	}

	/**
	 * Sets the version
	 *
	 * @param string $version
	 * @return void
	 */
	public function setVersion($version) {
		$this->version = $version;
	}

	/**
	 * Returns the startTime
	 *
	 * @return \DateTime $startTime
	 */
	public function getStartTime() {
		return $this->startTime;
	}

	/**
	 * Sets the startTime
	 *
	 * @param \DateTime $startTime
	 * @return void
	 */
	public function setStartTime(\DateTime $startTime) {
		$this->startTime = $startTime;
	}

	/**
	 * Returns the endTime
	 *
	 * @return string $endTime
	 */
	public function getEndTime() {
		return $this->endTime;
	}

	/**
	 * Sets the endTime
	 *
	 * @param string $endTime
	 * @return void
	 */
	public function setEndTime($endTime) {
		$this->endTime = $endTime;
	}

	/**
	 * Returns the namespace
	 *
	 * @return string $namespace
	 */
	public function getNamespace() {
		return $this->namespace;
	}

	/**
	 * Sets the namespace
	 *
	 * @param string $namespace
	 * @return void
	 */
	public function setNamespace($namespace) {
		$this->namespace = $namespace;
	}

}