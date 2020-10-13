<?php

namespace EssentialDots\EdMigrate\Command;

use EssentialDots\EdMigrate\Brancher\RelationBrancher;
use EssentialDots\EdMigrate\Database\SqlHandler;
use EssentialDots\EdMigrate\Expression\SymfonyLanguageExpression;
use EssentialDots\EdMigrate\Migration\ChunkableContentMigrationInterface;
use EssentialDots\EdMigrate\Migration\MigrationInterface;
use EssentialDots\EdMigrate\Migration\PageRecursiveMigrationInterface;
use EssentialDots\EdMigrate\Migration\RevertibleMigrationInterface;
use EssentialDots\EdMigrate\Service\DatabaseService;
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
 * Class PartialMigrationCommand
 *
 * @package EssentialDots\EdMigrate\Command
 */
class PartialMigrationCommand extends AbstractCommand {

	/**
	 * @return void
	 */
	protected function configure() {
		parent::configure();
		$this
			->setDescription('Creates a migration based on the database differences')
			->addArgument('migration', InputArgument::REQUIRED, 'Migration')
			->addArgument('action', InputArgument::OPTIONAL, 'Action', 'up')
			->addArgument('ids', InputArgument::OPTIONAL, 'IDs', 1)
			->addArgument('recursive', InputArgument::OPTIONAL, 'Recursive', 0);
	}

	/**
	 * @param InputInterface $input
	 * @param OutputInterface $output
	 * @return int
	 */
	protected function execute(InputInterface $input, OutputInterface $output) {
		$migration = $input->getArgument('migration');
		$action = $input->getArgument('action');
		$ids = $input->getArgument('ids');
		$recursive = $input->getArgument('recursive');

		$start = microtime(TRUE);

		/** @var MigrationInterface $migrationObj */
		$migrationObj = $this->objectManager->get($migration, $output);
		if ($migrationObj instanceof PageRecursiveMigrationInterface) {
			$migrationService = MigrationService::getInstance();

			foreach (array_reverse(GeneralUtility::intExplode(',', $ids)) as $pageId) {
				$node = $this->getNodeRepository()->findBy('pages', 'uid = ' . $pageId, 1);
				$migrationService->addRootNode($node);
			}

			if ($recursive) {
				$pagesEnableFields = DatabaseService::deleteClause('pages');
				$migrationService->addBrancher(
					new RelationBrancher(
						'pages',
						'pages',
						new SymfonyLanguageExpression('"pid = " ~ uid ~ "' . $pagesEnableFields . '"')
					)
				);
			}

			$migrationService->addTransformation(
				new LogTransformation()
			);

			switch ($action) {
				case 'up':
					$migrationObj->up();
					break;
				case 'down':
					if ($migrationObj instanceof RevertibleMigrationInterface) {
						/** @var RevertibleMigrationInterface $migrationObj */
						$migrationObj->down();
					} else {
						throw new \RuntimeException('Migration ' . $migration . ' is not an instance of RevertibleMigrationInterface and cannot be rolled back!', 4);
					}
					break;
				default:
					throw new \RuntimeException('Action ' . $action . ' not supported!', 5);
			}

			$migrationService->run();

			$this->getPersistenceSession()->persistChangedEntities();

			$timeElapsedSecs = microtime(TRUE) - $start;

			$output->writeln('Finished in ' . $timeElapsedSecs . 's');
		} elseif ($migrationObj instanceof ChunkableContentMigrationInterface) {
			$migrationService = MigrationService::getInstance();

			foreach (array_reverse(GeneralUtility::intExplode(',', $ids)) as $id) {
				$node = $this->getNodeRepository()->findBy($migrationObj->getTableName(), 'uid = ' . $id, 1);
				$migrationService->addRootNode($node);
			}

			$migrationService->addTransformation(
				new LogTransformation()
			);

			switch ($action) {
				case 'up':
					$migrationObj->up();
					break;
				case 'down':
					if ($migrationObj instanceof RevertibleMigrationInterface) {
						/** @var RevertibleMigrationInterface $migrationObj */
						$migrationObj->down();
					} else {
						throw new \RuntimeException('Migration ' . $migration . ' is not an instance of RevertibleMigrationInterface and cannot be rolled back!', 4);
					}
					break;
				default:
					throw new \RuntimeException('Action ' . $action . ' not supported!', 5);
			}

			$migrationService->run();

			$this->getPersistenceSession()->persistChangedEntities();

			$timeElapsedSecs = microtime(TRUE) - $start;

			$output->writeln('Finished in ' . $timeElapsedSecs . 's');
		} else {
			throw new \RuntimeException('Migration ' . $migration . ' is not an instance of PageRecursiveMigrationInterface!', 6);
		}

		return 0;
	}
}