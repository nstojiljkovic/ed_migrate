<?php

namespace EssentialDots\EdMigrate\Transformation;

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

use EssentialDots\EdMigrate\Domain\Model\AbstractEntity;
use EssentialDots\EdMigrate\Expression\ExpressionInterface;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\DataHandling\DataHandler;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\Restriction\DeletedRestriction;
use TYPO3\CMS\Core\Database\Connection;

/**
 * Class SyncPageTranslationsTransformation
 *
 * @package EssentialDots\EdMigrate\Transformation
 */
class SyncPageTranslationsTransformation implements TransformationInterface {

	/**
	 * @var string
	 */
	protected $languageUids;

	/**
	 * @var ExpressionInterface
	 */
	protected $whereExpression;

	/**
	 * @param string $languageUids
	 * @param ExpressionInterface $whereExpression
	 */
	public function __construct($languageUids, ExpressionInterface $whereExpression = NULL) {
		$this->languageUids = $languageUids;
		$this->whereExpression = $whereExpression;
	}

	/**
	 * @param AbstractEntity $node
	 * @return bool
	 */
	public function run(AbstractEntity $node) {
		if ($node->_getTableName() === 'pages') {
			if ($this->whereExpression === NULL || $this->whereExpression->evaluate($node)) {
				$this->syncTranslationsAction($node->getUid(), $this->languageUids);
			}
		}

		return TRUE;
	}

	/**
	 * @param int $pageUid
	 * @param string $languageUids
	 * @return void
	 */
	public function syncTranslationsAction($pageUid = NULL, $languageUids = '') {

		$languageUidsArr = GeneralUtility::intExplode(',', $languageUids, TRUE);
		if (count($languageUidsArr)) {
			/** @var \TYPO3\CMS\Core\DataHandling\DataHandler $dataHandler */
			$dataHandler = GeneralUtility::makeInstance(DataHandler::class);
			$els = $this->getElementsOnPage($pageUid);
			$connectionForTable = $this->getConnectionPool()->getConnectionForTable('tt_content');

			//
			// 0. start transaction
			//
//			$connectionForTable->beginTransaction();

			try {
				//
				// 1. delete obsolete translations
				//
				$obsoleteEls = $this->findObsoleteTranslations($els);
				if (count($obsoleteEls)) {
					$cmd = $this->getDeleteElementsCommand($obsoleteEls);
					$dataHandler->start(NULL, $cmd);
					$dataHandler->process_cmdmap();
					$els = $this->getElementsOnPage($pageUid);
				}

				//
				// 2. translate every element with sys_language_uid = 0 if a translation doesn't exist already
				//
				foreach ($languageUidsArr as $languageUid) {
					while (($cmd = $this->getTranslateAllElementsCommand($els, $languageUid)) && count($cmd)) {
						$dataHandler->start(NULL, $cmd);
						$dataHandler->process_cmdmap();
						$els = $this->getElementsOnPage($pageUid);
					}
				}

				$graph = $this->getContentGraphForElements($els);
				// $graph = $this->sortGraph($graph);

				//
				// 3. update translation positions and sortings
				//
				foreach ($languageUidsArr as $languageUid) {
					$queue = array(array($graph['0'], 0));
					$currentDepth = 0;
					$data = array();
					while ((list($queueItem, $queueItemDepth) = array_shift($queue))) {
						if ($queueItem) {
							foreach ($queueItem as &$columnEls) {
								$prevEl = NULL;
								$prevTransEl = NULL;
								foreach ($columnEls as &$el) {
									$transEl = $this->findTranslation($els, $el['uid'], $languageUid);
									if (is_null($transEl)) {
										continue;
									}
									$parentEl = NULL;
									if ($el['tx_flux_parent']) {
										$parentEl = $this->findTranslation($els, $el['tx_flux_parent'], $languageUid);
									}
									if (is_null($parentEl)) {
										$data['tt_content'][$transEl['uid']]['tx_flux_parent'] = NULL;
									} else {
										$data['tt_content'][$transEl['uid']]['tx_flux_parent'] = $parentEl['uid'];
									}
									// $data['tt_content'][$transEl['uid']]['colPos'] = $el['colPos'];
									// $data['tt_content'][$transEl['uid']]['tx_flux_column'] = $el['tx_flux_column'];
									// $data['tt_content'][$transEl['uid']]['sorting'] = $el['sorting'];
								}
								foreach ($columnEls as $el) {
									if (is_array($el['children'])) {
										$queue[] = array($el['children'], $currentDepth + 1);
									}
								}
							}
						}
					}

					if (count($data)) {
						$dataHandler->start($data, NULL);
						$dataHandler->process_datamap();
					}
					$ids = array_map(
						function ($el) {
							return (int)$el['uid'];
						},
						array_filter(
							$els,
							function (&$el) {
								return (int)$el['sys_language_uid'] === 0;
							}
						)
					);
					if (count($ids) > 0) {
						$sql = <<<SQL
						UPDATE tt_content AS t1
							LEFT JOIN tt_content AS t2
							ON (t1.l18n_parent = t2.uid)
						SET
							t1.colPos = t2.colPos,
							t1.tx_flux_column = t2.tx_flux_column,
							t1.sorting = t2.sorting
						WHERE t2.uid IN (?)
SQL;
						$connectionForTable->executeQuery($sql, array($ids), array(Connection::PARAM_STR_ARRAY));

					}
				}
				//
				// 4. commit
//				$connectionForTable->commit();

			} catch (\Exception $e) {
//				$connectionForTable->rollBack();
				throw $e;
			}
		}

	}

	/**
	 * @param array $obj
	 * @param array $el
	 * @param string $path
	 * @return void
	 */
	protected function addDeep(&$obj, &$el, $path) {
		$pathArr = explode('.', $path);
		$curr = &$obj;
		while (NULL !== ($seg = array_shift($pathArr))) {
			if (count($pathArr)) {
				if (!isset($curr[$seg])) {
					$curr[$seg] = array();
				}
				$curr = &$curr[$seg];
			} else {
				if (isset($curr[$seg])) {
					$curr[$seg][] = $el;
					// @codingStandardsIgnoreStart
					usort($curr[$seg], function ($a, $b) {
						if ($a['sorting'] > $b['sorting']) {
							return 1;
						}
						return $a['sorting'] < $b['sorting'] ? -1 : 0;
					});
					// @codingStandardsIgnoreEnd
				} else {
					$curr[$seg] = array($el);
				}
				break;
			}
		}
	}

	/**
	 * @param array $graph
	 * @param string $path
	 * @param int|string $uid
	 * @return null|string
	 */
	protected function findEl(&$graph, $path, $uid) {
		// @todo: implement dfs or bfs traversal
		if (is_array($graph)) {
			foreach ($graph as $column => &$els) {
				foreach ($els as $k => &$el) {
					$elPath = $path . '.' . $column . '.' . $k;
					if ($el['uid'] === $uid) {
						return $elPath;
					}
					if (is_array($el['children'])) {
						$tmpPath = $this->findEl($el['children'], $elPath . '.children', $uid);
						if ($tmpPath) {
							return $tmpPath;
						}
					}
				}
			}
		}

		return NULL;
	}

	/**
	 * @param array $graph
	 * @param array $el
	 * @return bool
	 */
	protected function insertElementInGraph(&$graph, &$el) {
		$language = (string)intval($el['sys_language_uid']);
		$fluxParent = $el['tx_flux_parent'];
		$fluxColumn = $el['tx_flux_column'];
		$colPos = $el['colPos'];
		$origUid = $language === '0' ? $el['uid'] : $el['l18n_parent'];
		if (!isset($graph[$language])) {
			$graph[$language] = array();
		}
		$result = FALSE;
		if ($fluxParent && $fluxColumn) {
			// find parent
			if (($fluxParentPath = $this->findEl($graph[$language], $language, $fluxParent))) {
				$this->addDeep($graph, $el, $fluxParentPath . '.children.' . $fluxColumn);
				$result = TRUE;
			}
		} else {
			$result = TRUE;
			// add to page directly
			$this->addDeep($graph, $el, $language . '.' . $colPos);
		}

		return $result;
	}

	/**
	 * @param int $pageUid
	 * @return array
	 */
	protected function getElementsOnPage($pageUid) {

		$queryBuilder = $this->getConnectionPool()->getQueryBuilderForTable('tt_content');
		$queryBuilder->getRestrictions()
			->removeAll()
			->add(GeneralUtility::makeInstance(DeletedRestriction::class));
		$queryStatement = $queryBuilder->select(
			'uid',
			'pid',
			'tx_flux_column',
			'tx_flux_parent',
			'tx_flux_children',
			'sys_language_uid',
			'l18n_parent',
			'sorting',
			'colPos',
			'deleted'
		)
			->from('tt_content')
			->where(
				$queryBuilder->expr()->eq(
					'pid',
					$queryBuilder->createNamedParameter((int)$pageUid, \PDO::PARAM_INT)
				)
			)
			->execute();
		return $queryStatement->fetchAll();
	}

	/**
	 * @param array $els
	 * @return array
	 */
	protected function findObsoleteTranslations(&$els) {
		$result = array();
		foreach ($els as $el) {
			if ((int)$el['sys_language_uid'] > 0) {
				$foundOriginal = FALSE;
				foreach ($els as $tmpEl) {
					if ($tmpEl['uid'] === $el['l18n_parent']) {
						$foundOriginal = TRUE;
						break;
					}
				}
				if (!$foundOriginal) {
					$result[] = $el;
				}
			}
		}

		return $result;
	}

	/**
	 * @param array $els
	 * @return array
	 */
	protected function getDeleteElementsCommand(&$els) {
		$cmd = array();
		foreach ($els as $el) {
			$cmd['tt_content'][$el['uid']]['delete'] = TRUE;
		}
		return $cmd;
	}

	/**
	 * @param array $origEls
	 * @param array $allEls
	 * @param int $languageUid
	 * @return array
	 */
	protected function getTranslateElementsCommand($origEls, $allEls, $languageUid) {
		$cmd = array();

		foreach ($origEls as $el) {
			if ((int)$el['sys_language_uid'] === 0) {
				$transEl = $this->findTranslation($allEls, $el['uid'], $languageUid);
				if (is_null($transEl)) {
					$cmd['tt_content'][$el['uid']]['localize'] = $languageUid;
				}
//				$foundTranslation = FALSE;
//				foreach ($allEls as $tmpEl) {
//					if ($tmpEl['l18n_parent'] === $el['uid'] && (int) $tmpEl['sys_language_uid'] === $languageUid) {
//						$foundTranslation = TRUE;
//						break;
//					}
//				}
//				if (!$foundTranslation) {
//					$cmd['tt_content'][$el['uid']]['localize'] = $languageUid;
//				}
			}
		}
		return $cmd;
	}

	/**
	 * @param array $els
	 * @param int $origUid
	 * @param int $languageUid
	 * @return array|null
	 */
	protected function findTranslation($els, $origUid, $languageUid) {
		foreach ($els as &$el) {
			if ($el['l18n_parent'] === $origUid && (int)$el['sys_language_uid'] === $languageUid) {
				return $el;
			}
		}
		return NULL;
	}

	/**
	 * @param array $els
	 * @param int $languageUid
	 * @return array
	 */
	protected function getTranslateAllElementsCommand($els, $languageUid) {
		$cmd = array();

		$graph = $this->getContentGraphForElements($els);
		// BFS
		$queue = array($graph['0']);
		while (($queueItem = array_shift($queue))) {
			foreach ($queueItem as $columnEls) {
				$tmpCmd = $this->getTranslateElementsCommand($columnEls, $els, $languageUid);
				if (count($tmpCmd)) {
					$cmd = array_merge($cmd, $tmpCmd);
					break;
				}
				foreach ($columnEls as $el) {
					if (is_array($el['children'])) {
						$queue[] = $el['children'];
					}
				}
			}
		}
		return $cmd;
	}

	/**
	 * @param array $els
	 * @return array
	 */
	protected function getContentGraphForElements($els = array()) {
		// build graph of content elements per language
		$graph = array();
		$elsRemaining = $els;
		$process = TRUE;
		while ($process) {
			$process = FALSE;
			$tmpEls = array();
			foreach ($elsRemaining as $el) {
				if ($this->insertElementInGraph($graph, $el)) {
					$process = TRUE;
				} else {
					// queue for later processing
					$tmpEls[] = $el;
				}
			}
			$elsRemaining = $tmpEls;
		}
//		if (count($elsRemaining)) {
//			// we didn't process all of the elements
//			// these should be deleted!?
//		}
		return $graph;
	}

	/**
	 * @param array $graph
	 * @return array
	 */
	protected function &sortColumns(&$columns) {
		foreach ($columns as $column => &$els) {
			$newEls = $els;
			$key = 0;
			foreach ($els as &$el) {
				$key++;
				//$key = $el['sorting'];
				$newEls[$key] = $el;
				if (is_array($el['children'])) {
					$newEls[$key]['children'] = $this->sortColumns($el['children']);
				}
			}
			ksort($newEls);
			$columns[$column] = $newEls;
		}

		ksort($columns);
		return $columns;
	}

	/**
	 * @param array $graph
	 * @return array
	 */
	protected function &sortGraph(&$graph) {
		foreach ($graph as $lang => $els) {
			$graph[$lang] = $this->sortColumns($els);
		}
		return $graph;
	}

	/**
	 * @return ConnectionPool
	 */
	protected function getConnectionPool() {
		return GeneralUtility::makeInstance(ConnectionPool::class);
	}
}