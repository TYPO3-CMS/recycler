<?php
namespace TYPO3\CMS\Recycler\Domain\Model;

/*
 * This file is part of the TYPO3 CMS project.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 * The TYPO3 project - inspiring people to share!
 */

use TYPO3\CMS\Core\DataHandling\DataHandler;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Recycler\Utility\RecyclerUtility;

/**
 * Model class for the 'recycler' extension.
 *
 * @author Julian Kleinhans <typo3@kj187.de>
 */
class DeletedRecords {

	/**
	 * Array with all deleted rows
	 *
	 * @var array
	 */
	protected $deletedRows = array();

	/**
	 * String with the global limit
	 *
	 * @var string
	 */
	protected $limit = '';

	/**
	 * Array with all available FE tables
	 *
	 * @var array
	 */
	protected $table = array();

	/**
	 * Object from helper class
	 *
	 * @var RecyclerUtility
	 */
	protected $recyclerHelper;

	/**
	 * Array with all label fields drom different tables
	 *
	 * @var array
	 */
	public $label;

	/**
	 * Array with all title fields drom different tables
	 *
	 * @var array
	 */
	public $title;

	/************************************************************
	 * GET DATA FUNCTIONS
	 *
	 *
	 ************************************************************/
	/**
	 * Load all deleted rows from $table
	 * If table is not set, it iterates the TCA tables
	 *
	 * @param int $id UID from selected page
	 * @param string $table Tablename
	 * @param int $depth How many levels recursive
	 * @param string $limit MySQL LIMIT
	 * @param string $filter Filter text
	 * @return DeletedRecords
	 */
	public function loadData($id, $table, $depth, $limit = '', $filter = '') {
		// set the limit
		$this->limit = trim($limit);
		if ($table) {
			if (in_array($table, RecyclerUtility::getModifyableTables())) {
				$this->table[] = $table;
				$this->setData($id, $table, $depth, $GLOBALS['TCA'][$table]['ctrl'], $filter);
			}
		} else {
			foreach ($GLOBALS['TCA'] as $tableKey => $tableValue) {
				// only go into this table if the limit allows it
				if ($this->limit !== '') {
					$parts = GeneralUtility::trimExplode(',', $this->limit);
					// abort loop if LIMIT 0,0
					if ((int)$parts[0] === 0 && (int)$parts[1] === 0) {
						break;
					}
				}
				$this->table[] = $tableKey;
				$this->setData($id, $tableKey, $depth, $tableValue['ctrl'], $filter);
			}
		}
		return $this;
	}

	/**
	 * Find the total count of deleted records
	 *
	 * @param int $id UID from record
	 * @param string $table Tablename from record
	 * @param int $depth How many levels recursive
	 * @param string $filter Filter text
	 * @return int
	 */
	public function getTotalCount($id, $table, $depth, $filter) {
		$deletedRecords = $this->loadData($id, $table, $depth, '', $filter)->getDeletedRows();
		$countTotal = 0;
		foreach ($this->table as $tableName) {
			$countTotal += count($deletedRecords[$tableName]);
		}
		return $countTotal;
	}

	/**
	 * Set all deleted rows
	 *
	 * @param int $id UID from record
	 * @param string $table Tablename from record
	 * @param int $depth How many levels recursive
	 * @param array $tcaCtrl TCA CTRL array
	 * @param string $filter Filter text
	 * @return void
	 */
	protected function setData($id, $table, $depth, $tcaCtrl, $filter) {
		$id = (int)$id;
		if (!array_key_exists('delete', $tcaCtrl)) {
			return;
		}
		$db = $this->getDatabaseConnection();
		// find the 'deleted' field for this table
		$deletedField = RecyclerUtility::getDeletedField($table);
		// create the filter WHERE-clause
		$filterWhere = '';
		if (trim($filter) != '') {
			$filterWhere = ' AND (' . (\TYPO3\CMS\Core\Utility\MathUtility::canBeInterpretedAsInteger($filter) ? 'uid = ' . $filter . ' OR pid = ' . $filter . ' OR ' : '') . $tcaCtrl['label'] . ' LIKE "%' . $this->escapeValueForLike($filter, $table) . '%"' . ')';
		}

		// get the limit
		if (!empty($this->limit)) {
			// count the number of deleted records for this pid
			$deletedCount = $db->exec_SELECTcountRows('uid', $table, $deletedField . '<>0 AND pid = ' . $id . $filterWhere);
			// split the limit
			$parts = GeneralUtility::trimExplode(',', $this->limit);
			$offset = $parts[0];
			$rowCount = $parts[1];
			// subtract the number of deleted records from the limit's offset
			$result = $offset - $deletedCount;
			// if the result is >= 0
			if ($result >= 0) {
				// store the new offset in the limit and go into the next depth
				$offset = $result;
				$this->limit = implode(',', array($offset, $rowCount));
				// do NOT query this depth; limit also does not need to be set, we set it anyways
				$allowQuery = FALSE;
				$allowDepth = TRUE;
				$limit = '';
			} else {
				// the offset for the temporary limit has to remain like the original offset
				// in case the original offset was just crossed by the amount of deleted records
				if ($offset !== 0) {
					$tempOffset = $offset;
				} else {
					$tempOffset = 0;
				}
				// set the offset in the limit to 0
				$newOffset = 0;
				// convert to negative result to the positive equivalent
				$absResult = abs($result);
				// if the result now is > limit's row count
				if ($absResult > $rowCount) {
					// use the limit's row count as the temporary limit
					$limit = implode(',', array($tempOffset, $rowCount));
					// set the limit's row count to 0
					$this->limit = implode(',', array($newOffset, 0));
					// do not go into new depth
					$allowDepth = FALSE;
				} else {
					// if the result now is <= limit's row count
					// use the result as the temporary limit
					$limit = implode(',', array($tempOffset, $absResult));
					// subtract the result from the row count
					$newCount = $rowCount - $absResult;
					// store the new result in the limit's row count
					$this->limit = implode(',', array($newOffset, $newCount));
					// if the new row count is > 0
					if ($newCount > 0) {
						// go into new depth
						$allowDepth = TRUE;
					} else {
						// if the new row count is <= 0 (only =0 makes sense though)
						// do not go into new depth
						$allowDepth = FALSE;
					}
				}
				// allow query for this depth
				$allowQuery = TRUE;
			}
		} else {
			$limit = '';
			$allowDepth = TRUE;
			$allowQuery = TRUE;
		}
		// query for actual deleted records
		if ($allowQuery) {
			$recordsToCheck = \TYPO3\CMS\Backend\Utility\BackendUtility::getRecordsByField($table, $deletedField, '1', ' AND pid = ' . $id . $filterWhere, '', '', $limit, FALSE);
			if ($recordsToCheck) {
				$this->checkRecordAccess($table, $recordsToCheck);
			}
		}
		// go into depth
		if ($allowDepth && $depth >= 1) {
			// check recursively for elements beneath this page
			$resPages = $db->exec_SELECTquery('uid', 'pages', 'pid=' . $id, '', 'sorting');
			if ($resPages) {
				while ($rowPages = $db->sql_fetch_assoc($resPages)) {
					$this->setData($rowPages['uid'], $table, $depth - 1, $tcaCtrl, $filter);
					// some records might have been added, check if we still have the limit for further queries
					if (!empty($this->limit)) {
						$parts = GeneralUtility::trimExplode(',', $this->limit);
						// abort loop if LIMIT 0,0
						if ((int)$parts[0] === 0 && (int)$parts[1] === 0) {
							break;
						}
					}
				}
				$db->sql_free_result($resPages);
			}
		}
		$this->label[$table] = $tcaCtrl['label'];
		$this->title[$table] = $tcaCtrl['title'];
	}

	/**
	 * Checks whether the current backend user has access to the given records.
	 *
	 * @param string $table Name of the table
	 * @param array $rows Record row
	 * @return void
	 */
	protected function checkRecordAccess($table, array $rows) {
		foreach ($rows as $row) {
			if (RecyclerUtility::checkAccess($table, $row)) {
				$this->setDeletedRows($table, $row);
			}
		}
	}

	/**
	 * Escapes a value to be used for like in a database query.
	 * There is a special handling for the characters '%' and '_'.
	 *
	 * @param string $value The value to be escaped for like conditions
	 * @param string $tableName The name of the table the query should be used for
	 * @return string The escaped value to be used for like conditions
	 */
	protected function escapeValueForLike($value, $tableName) {
		$db = $this->getDatabaseConnection();
		return $db->escapeStrForLike($db->quoteStr($value, $tableName), $tableName);
	}

	/************************************************************
	 * DELETE FUNCTIONS
	 ************************************************************/
	/**
	 * Delete element from any table
	 *
	 * @param array $recordsArray Representation of the records
	 * @return bool
	 */
	public function deleteData($recordsArray) {
		if (is_array($recordsArray)) {
			/** @var $tce DataHandler **/
			$tce = GeneralUtility::makeInstance(DataHandler::class);
			$tce->start('', '');
			$tce->disableDeleteClause();
			foreach ($recordsArray as $record) {
				list($table, $uid) = explode(':', $record);
				$tce->deleteEl($table, (int)$uid, TRUE, TRUE);
			}
			return TRUE;
		}
		return FALSE;
	}

	/************************************************************
	 * UNDELETE FUNCTIONS
	 ************************************************************/
	/**
	 * Undelete records
	 * If $recursive is TRUE all records below the page uid would be undelete too
	 *
	 * @param array $recordsArray Representation of the records
	 * @param bool $recursive Whether to recursively undelete
	 * @return bool
	 */
	public function undeleteData($recordsArray, $recursive = FALSE) {
		$result = FALSE;
		$depth = 999;
		if (is_array($recordsArray)) {
			$this->deletedRows = array();
			$cmd = array();
			foreach ($recordsArray as $record) {
				list($table, $uid) = explode(':', $record);
				$cmd[$table][$uid]['undelete'] = 1;
				if ($table === 'pages' && $recursive) {
					$this->loadData($uid, '', $depth, '');
					$childRecords = $this->getDeletedRows();
					if (count($childRecords) > 0) {
						foreach ($childRecords as $childTable => $childRows) {
							foreach ($childRows as $childRow) {
								$cmd[$childTable][$childRow['uid']]['undelete'] = 1;
							}
						}
					}
				}
			}
			if ($cmd) {
				$tce = GeneralUtility::makeInstance(DataHandler::class);
				$tce->start(array(), $cmd);
				$tce->process_cmdmap();
				$result = TRUE;
			}
		}
		return $result;
	}

	/************************************************************
	 * SETTER FUNCTIONS
	 ************************************************************/
	/**
	 * Set deleted rows
	 *
	 * @param string $table Tablename
	 * @param array $row Deleted record row
	 * @return void
	 */
	public function setDeletedRows($table, array $row) {
		$this->deletedRows[$table][] = $row;
	}

	/************************************************************
	 * GETTER FUNCTIONS
	 ************************************************************/
	/**
	 * Get deleted Rows
	 *
	 * @return array Array with all deleted rows from TCA
	 */
	public function getDeletedRows() {
		return $this->deletedRows;
	}

	/**
	 * Get table
	 *
	 * @return array Array with table from TCA
	 */
	public function getTable() {
		return $this->table;
	}

	/**
	 * Returns an instance of DatabaseConnection
	 *
	 * @return \TYPO3\CMS\Core\Database\DatabaseConnection
	 */
	protected function getDatabaseConnection() {
		return $GLOBALS['TYPO3_DB'];
	}
}
