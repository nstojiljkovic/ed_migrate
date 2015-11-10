<?php

namespace EssentialDots\EdMigrate\Tests\Unit\Domain\Model;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2015 Nikola Stojiljkovic, Essential Dots d.o.o. Belgrade
 *
 *  All rights reserved
 *
 *  This script is part of the TYPO3 project. The TYPO3 project is
 *  free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 2 of the License, or
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
 * Test case for class \EssentialDots\EdMigrate\Domain\Model\Log.
 *
 * @copyright Copyright belongs to the respective authors
 * @license http://www.gnu.org/licenses/gpl.html GNU General Public License, version 3 or later
 *
 * @author Nikola Stojiljkovic 
 */
class LogTest extends \TYPO3\CMS\Core\Tests\UnitTestCase {
	/**
	 * @var \EssentialDots\EdMigrate\Domain\Model\Log
	 */
	protected $subject = NULL;

	public function setUp() {
		$this->subject = new \EssentialDots\EdMigrate\Domain\Model\Log();
	}

	public function tearDown() {
		unset($this->subject);
	}

	/**
	 * @test
	 */
	public function getVersionReturnsInitialValueForString() {
		$this->assertSame(
			'',
			$this->subject->getVersion()
		);
	}

	/**
	 * @test
	 */
	public function setVersionForStringSetsVersion() {
		$this->subject->setVersion('Conceived at T3CON10');

		$this->assertAttributeEquals(
			'Conceived at T3CON10',
			'version',
			$this->subject
		);
	}

	/**
	 * @test
	 */
	public function getStartTimeReturnsInitialValueForDateTime() {
		$this->assertEquals(
			NULL,
			$this->subject->getStartTime()
		);
	}

	/**
	 * @test
	 */
	public function setStartTimeForDateTimeSetsStartTime() {
		$dateTimeFixture = new \DateTime();
		$this->subject->setStartTime($dateTimeFixture);

		$this->assertAttributeEquals(
			$dateTimeFixture,
			'startTime',
			$this->subject
		);
	}

	/**
	 * @test
	 */
	public function getEndTimeReturnsInitialValueForString() {
		$this->assertSame(
			'',
			$this->subject->getEndTime()
		);
	}

	/**
	 * @test
	 */
	public function setEndTimeForStringSetsEndTime() {
		$this->subject->setEndTime('Conceived at T3CON10');

		$this->assertAttributeEquals(
			'Conceived at T3CON10',
			'endTime',
			$this->subject
		);
	}

	/**
	 * @test
	 */
	public function getNamespaceReturnsInitialValueForString() {
		$this->assertSame(
			'',
			$this->subject->getNamespace()
		);
	}

	/**
	 * @test
	 */
	public function setNamespaceForStringSetsNamespace() {
		$this->subject->setNamespace('Conceived at T3CON10');

		$this->assertAttributeEquals(
			'Conceived at T3CON10',
			'namespace',
			$this->subject
		);
	}
}
