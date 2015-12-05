<?php
namespace EssentialDots\EdMigrate\Command;

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

use EssentialDots\EdMigrate\Brancher\RelationBrancher;
use EssentialDots\EdMigrate\Database\SqlHandler;
use EssentialDots\EdMigrate\Expression\SymfonyLanguageExpression;
use EssentialDots\EdMigrate\Migration\MigrationInterface;
use EssentialDots\EdMigrate\Migration\PageRecursiveMigrationInterface;
use EssentialDots\EdMigrate\Migration\PageRecursiveRevertibleMigrationInterface;
use EssentialDots\EdMigrate\Migration\RevertibleMigrationInterface;
use EssentialDots\EdMigrate\Service\MigrationService;
use EssentialDots\EdMigrate\Transformation\LogTransformation;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Class EdMigrationCommandController
 *
 * @package EssentialDots\EdMigrate\Command
 */
class EdMigrationCommandController extends \TYPO3\CMS\Extbase\Mvc\Controller\CommandController {

	/**
	 * @var \TYPO3\CMS\Extbase\Object\ObjectManagerInterface
	 * @inject
	 */
	protected $objectManager;

	/**
	 * @var \EssentialDots\EdMigrate\Domain\Repository\LogRepository
	 * @inject
	 */
	protected $logRepository;

	/**
	 * @var \TYPO3\CMS\Extbase\Persistence\PersistenceManagerInterface
	 * @inject
	 */
	protected $persistenceManager;

	/**
	 * @var \EssentialDots\EdMigrate\Domain\Repository\NodeRepository
	 * @inject
	 */
	protected $nodeRepository;

	/**
	 * @var \EssentialDots\EdMigrate\Persistence\PersistenceSession
	 * @inject
	 */
	protected $persistenceSession;

	/**
	 * Prints a list of database differences between SQL files across TYPO3
	 * extensions and actual database state
	 *
	 * @param bool $addRemovalQueries
	 * @cli
	 * @return void
	 */
	public function databaseDiffCommand($addRemovalQueries = FALSE) {
		$statements = SqlHandler::getInstance()->getStructureUpdateSql($addRemovalQueries);
		$this->output(implode(PHP_EOL, $statements) . PHP_EOL);
	}

	/**
	 * Creates a migration based on the database differences
	 *
	 * @param string $namespace
	 * @param string $migrationName
	 * @param bool $addRemovalQueries
	 * @cli
	 * @return void
	 */
	public function createCommand($namespace, $migrationName, $addRemovalQueries = FALSE) {
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
					'$this->getDatabase()->sql_query(<<<SQL' . PHP_EOL . $indent . $indent[0],
					PHP_EOL . 'SQL' . PHP_EOL . $indent . ');' . PHP_EOL . $indent . '$this->output->output(\'.\');' . PHP_EOL);
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
					$this->outputLine($this->cliSuccessWrap('Migration ' . $namespace . '\\' . $className . ' created successfully.', TRUE));
				} else {
					$this->outputLine($this->cliErrorWrap('Migration ' . $namespace . '\\' . $className . ' has not been created successfully.', TRUE));
				}
			}

		} else {
			$this->outputLine($this->cliPendingWrap('No changes found. Migration not created.'));
		}
	}

	/**
	 * Prints a list of all migrations, along with their current status
	 *
	 * @param string $namespace
	 * @cli
	 * @return void
	 */
	public function statusCommand($namespace) {
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
		$this->cliEchoTable($statusData);
	}

	/**
	 * Runs all of the available migrations
	 *
	 * @param string $namespace
	 * @param string $target
	 * @param int $pagesPerRun
	 * @param int $limitContentPerRun
	 * @param int $numberOfParallelThreads
	 * @cli
	 * @return void
	 * @throws \TYPO3\CMS\Extbase\Persistence\Exception\IllegalObjectTypeException
	 */
	public function migrateCommand($namespace, $target = '', $pagesPerRun = 5, $limitContentPerRun = 100, $numberOfParallelThreads = 8) {
		$logEntries = $this->getLogEntries($namespace);
		foreach ($this->getMigrations($namespace) as $version => $migrationConf) {
			$logEntry = $logEntries[$version];
			if (!$logEntry) {
				/** @var MigrationInterface $migration */
				$migration = $this->objectManager->get($migrationConf[1], $this->output);
				if ($migration instanceof MigrationInterface) {
					/** @var \EssentialDots\EdMigrate\Domain\Model\Log $log */
					$log = $this->objectManager->get('EssentialDots\\EdMigrate\\Domain\\Model\\Log');
					$log->setNamespace($namespace);
					$log->setVersion($version);
					$log->setStartTime(new \DateTime());
					$this->logRepository->add($log);
					$this->persistenceManager->persistAll();
					$this->outputLine($this->cliSuccessWrap('Migrating ' . $migrationConf[0] . '...', TRUE));
					if ($migration instanceof PageRecursiveMigrationInterface) {
						$this->recursiveMigrationRun($migration, 'up', $pagesPerRun, $limitContentPerRun, $numberOfParallelThreads);
					} else {
						MigrationService::getInstance()->addTransformation(
							new LogTransformation()
						);
						$migration->up();
						MigrationService::getInstance()->run();
						$this->persistenceSession->persistChangedEntities();
						MigrationService::getInstance()->reset();
					}
					$log->setEndTime(new \DateTime());
					$this->logRepository->update($log);
					$this->persistenceManager->persistAll();
					$this->outputLine($this->cliSuccessWrap(PHP_EOL . 'Success!', TRUE));
					if ($target && (string) $version === (string) $target) {
						break;
					}
				} else {
					throw new \RuntimeException('Class ' . $migrationConf[1] . ' is not an instance of MigrationInterface', 1);
				}
			}
		}
		$this->outputLine('Done.');
	}

	/**
	 * Undo one previous migration
	 *
	 * @param string $namespace
	 * @param int $pagesPerRun
	 * @param int $limitContentPerRun
	 * @param int $numberOfParallelThreads
	 * @cli
	 * @return void
	 * @throws \TYPO3\CMS\Extbase\Persistence\Exception\IllegalObjectTypeException
	 */
	public function rollbackCommand($namespace, $pagesPerRun = 5, $limitContentPerRun = 100, $numberOfParallelThreads = 8) {
		$logEntries = $this->getLogEntries($namespace);
		$migrationConfs = $this->getMigrations($namespace);
		krsort($migrationConfs);
		foreach ($migrationConfs as $version => $migrationConf) {
			$logEntry = $logEntries[$version];
			if ($logEntry) {
				/** @var MigrationInterface $migration */
				$migration = $this->objectManager->get($migrationConf[1], $this->output);
				if ($migration instanceof MigrationInterface) {
					if ($migration instanceof RevertibleMigrationInterface) {
						/** @var RevertibleMigrationInterface $migration */
						$this->outputLine($this->cliSuccessWrap('Rolling back ' . $migrationConf[0] . '...', TRUE));
						if ($migration instanceof PageRecursiveRevertibleMigrationInterface) {
							$this->recursiveMigrationRun($migration, 'down', $pagesPerRun, $limitContentPerRun, $numberOfParallelThreads);
						} else {
							MigrationService::getInstance()->addTransformation(
								new LogTransformation()
							);
							$migration->down();
							MigrationService::getInstance()->run();
							$this->persistenceSession->persistChangedEntities();
							MigrationService::getInstance()->reset();
						}
						$this->logRepository->remove($logEntry);
						$this->persistenceManager->persistAll();
						$this->outputLine($this->cliSuccessWrap(PHP_EOL . 'Success!', TRUE));
						break;
					} else {
						throw new \RuntimeException('Last migration ' . $migrationConf[0] . ' is not an instance of RevertibleMigrationInterface and cannot be rolled back!', 2);
					}
				} else {
					throw new \RuntimeException('Class ' . $migrationConf[1] . ' is not an instance of MigrationInterface', 3);
				}
			}
		}
		$this->outputLine('Done.');
	}

	/**
	 * Partial run of a migration
	 *
	 * @param string $migration
	 * @param string $action
	 * @param string $pageIds
	 * @param int $recursive
	 * @cli
	 * @return void
	 */
	public function partialMigrationCommand($migration, $action = 'up', $pageIds = '1', $recursive = 0) {
		$start = microtime(TRUE);

		/** @var MigrationInterface $migrationObj */
		$migrationObj = $this->objectManager->get($migration, $this->output);
		if ($migrationObj instanceof PageRecursiveMigrationInterface) {
			$migrationService = MigrationService::getInstance();

			foreach (array_reverse(GeneralUtility::intExplode(',', $pageIds)) as $pageId) {
				$page = $this->nodeRepository->findBy('pages', 'uid = ' . $pageId, 1);
				$migrationService->addRootNode($page);
			}

			if ($recursive) {
				$pagesEnableFields = BackendUtility::deleteClause('pages');
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

			$this->persistenceSession->persistChangedEntities();

			$timeElapsedSecs = microtime(TRUE) - $start;

			$this->output->outputLine('Finished in ' . $timeElapsedSecs . 's');
		} else {
			throw new \RuntimeException('Migration ' . $migration . ' is not an instance of PageRecursiveMigrationInterface!', 6);
		}
	}

	/**
	 * @param $migration
	 * @param string $action
	 * @param int $pagesPerRun
	 * @param int $limitContentPerRun
	 * @param int $numberOfParallelThreads
	 * @return void
	 */
	protected function recursiveMigrationRun($migration, $action = 'up', $pagesPerRun = 5, $limitContentPerRun = 100, $numberOfParallelThreads = 8) {
		$rows = $this->getDatabase()->exec_SELECTgetRows(
			'pages.uid, pages.pid, COUNT(tt_content.uid) as c',
			'pages LEFT JOIN tt_content ON (pages.uid = tt_content.pid AND tt_content.l18n_parent = 0 ' . BackendUtility::deleteClause('tt_content') . ')',
			'1=1' . BackendUtility::deleteClause('pages'),
			'pages.uid, pages.pid'
		);
		$pages = array();
		$cnts = array();
		foreach ($rows as $row) {
			if (!isset($pages[$row['pid']])) {
				$pages[$row['pid']] = array();
			}
			$pages[$row['pid']][] = $row['uid'];
			$cnts[$row['uid']] = $row['c'];
		}
		unset($rows);

		// DFS iterative
		$stack = array(0);
		$visited = array();
		$plannedExecutions = array();
		$currentExecution = array();
		$currentExecutionContentCount = 0;
		while (count($stack)) {
			$v = array_pop($stack);
			if (!isset($visited[$v]) || !$visited[$v]) {
				$visited[$v] = TRUE;
				if ($v > 0) {
					if (count($currentExecution) === (int) $pagesPerRun || $currentExecutionContentCount + (int) $cnts[$v] >= (int) $limitContentPerRun) {
						$plannedExecutions[] = $currentExecution;
						$currentExecution = array();
						$currentExecutionContentCount = 0;
					}
					$currentExecutionContentCount += (int) $cnts[$v];
					$currentExecution[] = $v;
				}
				if (isset($pages[$v]) && is_array($pages[$v])) {
					foreach ($pages[$v] as $vN) {
						array_push($stack, $vN);
					}
				}
			}
		}

		if (count($currentExecution)) {
			$plannedExecutions[] = $currentExecution;
		}

		$commands = array();
		$totalCommands = 0;
		foreach ($plannedExecutions as $plannedExecution) {
			$command = 'typo3/cli_dispatch.phpsh extbase edmigration:partialmigration ' .
				'--migration ' . escapeshellarg(get_class($migration)) .
				' --action ' . $action .
				' --pageIds ' . implode(',', $plannedExecution);
			$commands[] = $command;
			$totalCommands += 1;
		}

		if (function_exists('pcntl_fork')) {
			$childPids = array();
			$i = 0;
			foreach ($commands as $command) {
				if (count($childPids) === $numberOfParallelThreads) {
					// wait for one thread to finish
					$hasFree = FALSE;
					while (!$hasFree) {
						sleep(1);
						foreach ($childPids as $j => $pid) {
							if (pcntl_waitpid($pid, $status, WNOHANG) !== 0) {
								unset($childPids[$j]);
								$hasFree = TRUE;
								break;
							}
						}
					}
				}

				$pid = pcntl_fork();
				if ($pid === -1) {
					throw new \RuntimeException('Could not fork', 7);
				}

				if ($pid) {
					$childPids[] = $pid;
					$i++;
				} else {
					pcntl_signal(SIGTERM, function ($signal) {
						if ($signal === SIGTERM) {
							exit(0);
						}
					});

					$i++;
					$this->output->outputLine('Running command [' . $i . '/' . $totalCommands . ']: ' . $command);
					$command = PATH_site . $command;
					$returnVar = NULL;
					passthru($command, $returnVar);
					if ($returnVar !== 0) {
						exit($returnVar);
					}
					exit(0);
				}
			}

			foreach ($childPids as $pid) {
				pcntl_waitpid($pid, $status);
			}
			$databaseConnection = $this->getDatabase();
			if (method_exists($databaseConnection, '__sleep')) {
				$databaseConnection->__sleep();
				$this->getDatabase()->connectDB();
			} elseif (!$this->getDatabase()->isConnected()) {
				$this->getDatabase()->connectDB();
			}
		} else {
			$i = 0;
			foreach ($commands as $command) {
				$i++;
				$this->output->outputLine('Running command [' . $i . '/' . $totalCommands . ']: ' . $command);
				$command = PATH_site . $command;
				$returnVar = NULL;
				passthru($command, $returnVar);
				if ($returnVar !== 0) {
					exit($returnVar);
				}
			}
		}
	}

	/**
	 * @param string $namespace
	 * @return \EssentialDots\EdMigrate\Domain\Model\Log[]
	 */
	protected function getLogEntries($namespace) {
		/** @var \EssentialDots\EdMigrate\Domain\Model\Log[] $logEntries */
		$logEntries = array();
		foreach ($this->logRepository->findByNamespace($namespace) as $log) {
			/** @var \EssentialDots\EdMigrate\Domain\Model\Log $log */
			$logEntries[$log->getVersion()] = $log;
		}

		return $logEntries;
	}

	/**
	 * @param string $migrationsNamespace
	 * @return array
	 */
	protected function getMigrations($migrationsNamespace) {
		if ('\\' == $migrationsNamespace[0]) {
			$migrationsNamespace = substr($migrationsNamespace, 1);
		}
		$namespaceArr = explode('\\', $migrationsNamespace);
		// shift company name
		array_shift($namespaceArr);
		$extensionKey = GeneralUtility::camelCaseToLowerCaseUnderscored(array_shift($namespaceArr));
		$migrations = array();
		if (ExtensionManagementUtility::isLoaded($extensionKey)) {
			array_unshift($namespaceArr, 'Classes');
			$folder = ExtensionManagementUtility::extPath($extensionKey, implode(DIRECTORY_SEPARATOR, $namespaceArr));
			if (@file_exists($folder) && !@is_file($folder)) {
				$dh = opendir($folder);
				while (FALSE !== ($filename = readdir($dh))) {
					if (!in_array($filename, array('.', '..')) && @is_file($folder . DIRECTORY_SEPARATOR . $filename)) {
						$matches = NULL;
						if (preg_match('/Migration(\d{14})(.*?)\.php/ms', $filename, $matches) === 1) {
							$migrations[$matches[1]] = array(
								$matches[2],
								$migrationsNamespace . '\\Migration' . $matches[1] . $matches[2]
							);
						}
					}
				}
			}
		}

		ksort($migrations);
		return $migrations;
	}

	/**
	 * @param $data
	 * @param string $headerColor
	 * @param string $headerBgColor
	 */
	protected function cliEchoTable($data, $headerColor = '1;37', $headerBgColor = '') {

		// Find longest string in each column
		$columns = [];
		foreach ($data as $row) {
			foreach ($row as $cellKey => $cell) {
				if (is_array($cell)) {
					// colorized
					$length = strlen($cell[1]);
				} else {
					$length = strlen($cell);
				}
				if (empty($columns[$cellKey]) || $columns[$cellKey] < $length) {
					$columns[$cellKey] = $length;
				}
			}
		}

		// Output table, padding columns
		$table = '';
		$firstRow = TRUE;
		foreach ($data as &$row) {
			if ($firstRow) {
				if ($headerColor) {
					$table .= "\033[" . $headerColor . 'm';
				}
				if ($headerBgColor) {
					$table .= "\033[" . $headerBgColor . 'm';
				}
			}
			foreach ($row as $cellKey => $cell) {
				if (is_array($cell)) {
					// colorized
					$str = $cell[0];
					$length = strlen($cell[1]);
				} else {
					$str = $cell;
					$length = strlen($cell);
				}
				$table .= $str . str_repeat(' ', $columns[$cellKey] - $length) . '   ';
			}
			if ($firstRow) {
				$firstRow = FALSE;
				if ($headerColor || $headerBgColor) {
					$table .= "\033[0m";
				}
			}
			$table .= PHP_EOL;
		}

		$this->output->output($table);
	}

	/**
	 * @param $str
	 * @param bool|FALSE $asStr
	 * @return array|string
	 */
	protected function cliSuccessWrap($str, $asStr = FALSE) {
		$result = "\033[" . '0;32m' . $str . "\033[0m";
		return $asStr ? $result : array($result, $str);
	}

	/**
	 * @param $str
	 * @param bool|FALSE $asStr
	 * @return array|string
	 */
	protected function cliErrorWrap($str, $asStr = FALSE) {
		$result = "\033[" . '0;31m' . $str . "\033[0m";
		return $asStr ? $result : array($result, $str);
	}

	/**
	 * @param $str
	 * @param bool|FALSE $asStr
	 * @return array|string
	 */
	protected function cliPendingWrap($str, $asStr = FALSE) {
		$result = "\033[" . '0;33m' . $str . "\033[0m";
		return $asStr ? $result : array($result, $str);
	}

	/**
	 * @return \TYPO3\CMS\Core\Database\DatabaseConnection
	 */
	protected function getDatabase() {
		return $GLOBALS['TYPO3_DB'];
	}
}