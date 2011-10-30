<?php

jimport('joomla.database.table');

class TablePulls extends JTable
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
		parent::__construct('pulls', 'id', $db);
	}

	/**
	 * Method to load an pull request by the number.
	 *
	 * @param   integer  $number  The pull request number.
	 *
	 * @return  integer
	 */
	public function loadByNumber($number)
	{
		// Get the asset id for the asset.
		$this->_db->setQuery(
			'SELECT ' . $this->_db->quoteName('id') .
			' FROM ' . $this->_db->quoteName('pulls') .
			' WHERE ' . $this->_db->quoteName('pull_id') . ' = ' . (int)$number
		);

		$id = (int) $this->_db->loadResult();
		if (empty($id))
		{
			return false;
		}
		// Check for a database error.
		if ($error = $this->_db->getErrorMsg())
		{
			$this->setError($error);
			return false;
		}
		return $this->load($id);
	}		
}
