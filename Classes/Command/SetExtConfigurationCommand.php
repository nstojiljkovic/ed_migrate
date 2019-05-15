<?php

namespace EssentialDots\EdMigrate\Command;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
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
 * Class SetExtConfigurationCommand
 *
 * @package EssentialDots\EdMigrate\Command
 */
class SetExtConfigurationCommand extends AbstractCommand {

	/**
	 * @return void
	 */
	protected function configure() {
		parent::configure();
		$this
			->setDescription('Sets extension configuration')
			->addArgument('extension', InputArgument::REQUIRED, 'Extension key')
			->addArgument('path', InputArgument::REQUIRED, 'Settings path')
			->addArgument('value', InputArgument::OPTIONAL, 'Settings value');
	}

	/**
	 * @param InputInterface $input
	 * @param OutputInterface $output
	 * @return void
	 */
	protected function execute(InputInterface $input, OutputInterface $output) {
		$extension = $input->getArgument('extension');
		$path = $input->getArgument('path');
		$value = $input->getArgument('value');

		GeneralUtility::makeInstance(ExtensionConfiguration::class)->set($extension, $path, $value);
	}
}