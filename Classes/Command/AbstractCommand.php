<?php
namespace EssentialDots\EdMigrate\Command;

use EssentialDots\EdMigrate\Domain\Repository\LogRepository;
use EssentialDots\EdMigrate\Domain\Repository\NodeRepository;
use EssentialDots\EdMigrate\Persistence\PersistenceSession;
use EssentialDots\EdMigrate\Service\DatabaseService;
use ReflectionException;
use ReflectionObject;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Output\OutputInterface;
use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Object\ObjectManager;
use TYPO3\CMS\Extbase\Persistence\PersistenceManagerInterface;

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
 * Class AbstractCommand
 *
 * @package EssentialDots\EdMigrate\Command
 */
class AbstractCommand extends Command {
	/**
	 * @var ObjectManager
	 */
	protected $objectManager;

	/**
	 * AbstractCommand constructor.
	 */
	public function __construct($name = NULL) {
		parent::__construct($name);
		$this->objectManager = GeneralUtility::makeInstance(ObjectManager::class);
	}


	/**
	 * @return LogRepository
	 */
	protected function getLogRepository() {
		return $this->objectManager->get(LogRepository::class);
	}

	/**
	 * @return NodeRepository
	 */
	protected function getNodeRepository() {
		return $this->objectManager->get(NodeRepository::class);
	}



	/**
	 * @return PersistenceSession
	 */
	protected function getPersistenceSession() {
		return $this->objectManager->get(PersistenceSession::class);
	}

	/**
	 * @return PersistenceManagerInterface
	 */
	protected function getPersistenceManager() {
		return $this->objectManager->get(PersistenceManagerInterface::class);
	}

	/**
	 * @param string $namespace
	 * @return \EssentialDots\EdMigrate\Domain\Model\Log[]
	 */
	protected function getLogEntries($namespace) {
		/** @var \EssentialDots\EdMigrate\Domain\Model\Log[] $logEntries */
		$logEntries = array();
		foreach ($this->getLogRepository()->findByNamespace($namespace) as $log) {
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
	protected function cliEchoTable(OutputInterface $output, $data, $headerColor = '1;37', $headerBgColor = '') {
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

		$output->write($table);
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
	 * @param OutputInterface $output
	 * @param $migration
	 * @param string $action
	 * @param int $pagesPerRun
	 * @param int $limitContentPerRun
	 * @param int $numberOfParallelThreads
	 * @param string $table
	 * @throws \Exception
	 * @return void
	 */
	protected function recursiveMigrationRun(OutputInterface $output, $migration, $action = 'up', $pagesPerRun = 5, $limitContentPerRun = 100, $numberOfParallelThreads = 8, $table = 'pages') {
		if ($table === 'pages') {
			$rows = DatabaseService::getDatabase()->exec_SELECTgetRows(
				'pages.uid, pages.pid, COUNT(tt_content.uid) as c',
				'pages LEFT JOIN tt_content ON (pages.uid = tt_content.pid AND tt_content.l18n_parent = 0 ' . DatabaseService::deleteClause('tt_content') . ')',
				'1=1' . DatabaseService::deleteClause('pages'),
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
		} else {
			$rows = DatabaseService::getDatabase()->exec_SELECTgetRows(
				'uid',
				$table,
				'1=1' . DatabaseService::deleteClause($table)
			);
			$plannedExecutions = array_chunk(array_map(function ($r) {
				return $r['uid'];
			}, $rows), $limitContentPerRun);
		}

		foreach ($plannedExecutions as $plannedExecution) {
			$command = 'php -d memory_limit=-1 ' . $_SERVER['SCRIPT_NAME'] . ' edmigration:partialmigration' .
				' ' . escapeshellarg(get_class($migration)) .
				' ' . $action .
				' ' . implode(',', $plannedExecution);
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
					$output->writeln('Running command [' . $i . '/' . $totalCommands . ']: ' . $command);
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

			try {
				/** @var \TYPO3\CMS\Core\Database\ConnectionPool $connectionPool */
				$connectionPool = GeneralUtility::makeInstance(\TYPO3\CMS\Core\Database\ConnectionPool::class);
				foreach ($connectionPool->getConnectionNames() as $connectionName) {
					$connection = $connectionPool->getConnectionByName($connectionName);
					if (!$connection->isConnected() || !$connection->ping()) {
						$connection->close();
						try {
							$refObject = new ReflectionObject($connection);
							$refProperty = $refObject->getProperty('customConnectSetupExecuted');
							$refProperty->setAccessible(TRUE);
							$refProperty->setValue($connection, FALSE);
							$refProperty->setAccessible(FALSE);
						} catch (ReflectionException $exception) {
							// ignore exception
							// $this->outputLine($exception->getMessage());
						}
						$connection->connect();
					}
				}

				$databaseConnection = DatabaseService::getDatabase();
				if (method_exists($databaseConnection, '__sleep')) {
					$databaseConnection->__sleep();
					DatabaseService::getDatabase()->connectDB();
				} elseif (!DatabaseService::getDatabase()->isConnected()) {
					DatabaseService::getDatabase()->connectDB();
				}
			} catch (\Exception $e) {
				echo $e->getTraceAsString();
				throw $e;
			}
		} else {
			$i = 0;
			foreach ($commands as $command) {
				$i++;
				$output->writeln('Running command [' . $i . '/' . $totalCommands . ']: ' . $command);
				$returnVar = NULL;
				passthru($command, $returnVar);
				if ($returnVar !== 0) {
					exit($returnVar);
				}
			}
		}
	}
}