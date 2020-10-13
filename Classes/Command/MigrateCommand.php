<?php

namespace EssentialDots\EdMigrate\Command;

use EssentialDots\EdMigrate\Database\SqlHandler;
use EssentialDots\EdMigrate\Migration\ChunkableContentMigrationInterface;
use EssentialDots\EdMigrate\Migration\MigrationInterface;
use EssentialDots\EdMigrate\Migration\PageRecursiveMigrationInterface;
use EssentialDots\EdMigrate\Service\MigrationService;
use EssentialDots\EdMigrate\Transformation\LogTransformation;
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
 * Class MigrateCommand
 *
 * @package EssentialDots\EdMigrate\Command
 */
class MigrateCommand extends AbstractCommand {

	/**
	 * @return void
	 */
	protected function configure() {
		parent::configure();
		$this
			->setDescription('Creates a migration based on the database differences')
			->addArgument('namespace', InputArgument::REQUIRED, 'Migration namespace')
			->addArgument('target', InputArgument::OPTIONAL, 'Target', '')
			->addArgument('pagesPerRun', InputArgument::OPTIONAL, 'Pages per run', 5)
			->addArgument('limitContentPerRun', InputArgument::OPTIONAL, 'Content per run limit', 100)
			->addArgument('numberOfParallelThreads', InputArgument::OPTIONAL, 'Number of parallel threads', 8);
	}

	/**
	 * @param InputInterface $input
	 * @param OutputInterface $output
	 * @return int
	 * @throws \Exception
	 */
	protected function execute(InputInterface $input, OutputInterface $output) {
		$namespace = $input->getArgument('namespace');
		$target = $input->getArgument('target');
		$pagesPerRun = $input->getArgument('pagesPerRun');
		$limitContentPerRun = $input->getArgument('limitContentPerRun');
		$numberOfParallelThreads = $input->getArgument('numberOfParallelThreads');

		$logEntries = $this->getLogEntries($namespace);
		if ($target) {
			$foundTarget = FALSE;
			foreach ($this->getMigrations($namespace) as $version => $_) {
				$logEntry = $logEntries[$version];
				if (!$logEntry) {
					if ((string)$version === (string)$target) {
						$foundTarget = TRUE;
						break;
					}
				}
			}
			if (!$foundTarget) {
				throw new \RuntimeException('Target migration ' . $target . ' not found or it is already executed!', 1);
			}
		}
		foreach ($this->getMigrations($namespace) as $version => $migrationConf) {
			$logEntry = $logEntries[$version];
			if (!$logEntry) {
				/** @var MigrationInterface $migration */
				$migration = $this->objectManager->get($migrationConf[1], $output);
				if ($migration instanceof MigrationInterface) {
					/** @var \EssentialDots\EdMigrate\Domain\Model\Log $log */
					$log = $this->objectManager->get('EssentialDots\\EdMigrate\\Domain\\Model\\Log');
					$log->setNamespace($namespace);
					$log->setVersion($version);
					$log->setStartTime(new \DateTime());
					$this->getLogRepository()->add($log);
					$this->getPersistenceManager()->persistAll();
					$output->writeln($this->cliSuccessWrap('Migrating ' . $migrationConf[0] . '...', TRUE));
					if ($migration instanceof PageRecursiveMigrationInterface) {
						$this->recursiveMigrationRun($output, $migration, 'up', $pagesPerRun, $limitContentPerRun, $numberOfParallelThreads);
					} elseif ($migration instanceof ChunkableContentMigrationInterface) {
						$this->recursiveMigrationRun($output, $migration, 'up', $pagesPerRun, $limitContentPerRun, $numberOfParallelThreads, $migration->getTableName());
					} else {
						MigrationService::getInstance()->addTransformation(
							new LogTransformation()
						);
						$migration->up();
						MigrationService::getInstance()->run();
						$this->getPersistenceSession()->persistChangedEntities();
						MigrationService::getInstance()->reset();
					}
					$log->setEndTime(new \DateTime());
					$this->getLogRepository()->update($log);
					$this->getPersistenceManager()->persistAll();
					$output->writeln($this->cliSuccessWrap(PHP_EOL . 'Success!', TRUE));
					if ($target && (string)$version === (string)$target) {
						break;
					}
				} else {
					throw new \RuntimeException('Class ' . $migrationConf[1] . ' is not an instance of MigrationInterface', 1);
				}
			}
		}
		$output->writeln('Done.');

		return 0;
	}
}