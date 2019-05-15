<?php

namespace EssentialDots\EdMigrate\Command;

use EssentialDots\EdMigrate\Database\SqlHandler;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;
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
 * Class CreateCommand
 *
 * @package EssentialDots\EdMigrate\Command
 */
class CreateCommand extends AbstractCommand {

	/**
	 * @return void
	 */
	protected function configure() {
		parent::configure();
		$this
			->setDescription('Creates a migration based on the database differences')
			->addArgument('namespace', InputArgument::REQUIRED, 'Migration namespace')
			->addArgument('migrationName', InputArgument::REQUIRED, 'Migration name')
			->addArgument('addRemovalQueries', InputArgument::OPTIONAL, 'Add removal queries', FALSE);
	}

	/**
	 * @param InputInterface $input
	 * @param OutputInterface $output
	 * @return void
	 */
	protected function execute(InputInterface $input, OutputInterface $output) {
		$namespace = $input->getArgument('namespace');
		$migrationName = $input->getArgument('migrationName');
		$addRemovalQueries = $input->getArgument('addRemovalQueries');

		if (strpos($migrationName, '_') !== FALSE) {
			throw new \RuntimeException('Please provide migrationName in camel case!');
		}
		$statements = SqlHandler::getInstance()->getStructureUpdateSql($addRemovalQueries);
		if (count($statements)) {
			if ('\\' == $namespace[0]) {
				$namespace = substr($namespace, 1);
			}
			$namespaceArr = explode('\\', $namespace);
			// shift company name
			$company = array_shift($namespaceArr);
			$extensionKey = GeneralUtility::camelCaseToLowerCaseUnderscored(array_shift($namespaceArr));

			if (ExtensionManagementUtility::isLoaded($extensionKey)) {
				array_unshift($namespaceArr, 'Classes');
				$folder = ExtensionManagementUtility::extPath($extensionKey, implode(DIRECTORY_SEPARATOR, $namespaceArr));
				if (!@file_exists($folder)) {
					if (!mkdir($folder, octdec($GLOBALS['TYPO3_CONF_VARS']['BE']['folderCreateMask']), TRUE)) {
						throw new \RuntimeException('Could not create folder: ' . $folder);
					}
					GeneralUtility::fixPermissions($folder);
				}

				$migrationName = ucfirst($migrationName);
				$indent = '		';
				$statementWrap = array(
					'DatabaseService::getDatabase()->sql_query(<<<SQL' . PHP_EOL . $indent . $indent[0],
					PHP_EOL . 'SQL' . PHP_EOL . $indent . ');' . PHP_EOL . $indent . '$this->output->write(\'.\');' . PHP_EOL);
				$className = 'Migration' . date('YmdHis') . $migrationName;
				array_walk($statements, function(&$value, $key, $statementWrap) {
					$value = $statementWrap[0] . $value . $statementWrap[1];
				}, $statementWrap);

				$res = file_put_contents(
					$folder . DIRECTORY_SEPARATOR . $className . '.php',
					str_replace(
						array (
							'###YEAR###',
							'###COMPANY###',
							'###CLASS-NAME###',
							'###NAMESPACE###',
							'###STATEMENTS###'
						),
						array (
							date('Y'),
							$company,
							$className,
							$namespace,
							implode(PHP_EOL . $indent, $statements)
						),
						file_get_contents(
							ExtensionManagementUtility::extPath('ed_migrate', 'Resources/Private/Templates/Migration/MigrationTemplate.tmpl')
						)
					)
				);
				if ($res !== FALSE) {
					$output->writeln($this->cliSuccessWrap('Migration ' . $namespace . '\\' . $className . ' created successfully.', TRUE));
				} else {
					$output->writeln($this->cliErrorWrap('Migration ' . $namespace . '\\' . $className . ' has not been created successfully.', TRUE));
				}
			}
		} else {
			$output->writeln($this->cliPendingWrap('No changes found. Migration not created.'));
		}
	}
}