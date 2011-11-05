<?php

jimport('joomla.database.table');

class TableCheckstyle extends JTable
{
	/**
	 * Constructor
	 *
	 * @param  database  $db  A database connector object
	 *
	 * @return  JTableCategory
	 *
	 * @since   11.1
	 */
	public function __construct(&$db)
	{
		parent::__construct('phpCsResults', 'id', $db);
	}

	/**
	 * Truncate the table.
	 *
	 * @return void
	 */
	public function truncate()
	{
		$this->_db->setQuery('TRUNCATE TABLE '.$this->_tbl);

		$this->_db->query();

		return;
	}
}
