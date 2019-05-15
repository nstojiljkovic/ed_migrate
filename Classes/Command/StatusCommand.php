<?php

namespace EssentialDots\EdMigrate\Command;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

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
 * Class StatusCommand
 *
 * @package EssentialDots\EdMigrate\Command
 */
class StatusCommand extends AbstractCommand {

	/**
	 * @return void
	 */
	protected function configure() {
		parent::configure();

		$this
			->setDescription('Prints a list of all migrations, along with their current status')
			->addArgument('namespace', InputArgument::REQUIRED, 'Migrations namespace');
	}

	/**
	 * @param InputInterface $input
	 * @param OutputInterface $output
	 * @return void
	 */
	protected function execute(InputInterface $input, OutputInterface $output) {
		$namespace = $input->getArgument('namespace');
		$statusData = array(
			array(
				'Status',
				'Migration ID',
				'Migration Name'
			)
		);
		$logEntries = $this->getLogEntries($namespace);
		foreach ($this->getMigrations($namespace) as $version => $migrationConf) {
			if ($logEntries[$version]) {
				if ($logEntries[$version]->getEndTime()) {
					$status = $this->cliSuccessWrap('up');
				} else {
					$status = $this->cliPendingWrap('pending');
				}
			} else {
				$status = $this->cliErrorWrap('down');
			}
			$statusData[] = array(
				$status,
				$version,
				$migrationConf[0]
			);
		}
		$this->cliEchoTable($output, $statusData);
	}
}