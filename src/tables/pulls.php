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

	public function update($pulls)
	{
		//-- @todo this seems ugly :P

		$activePulls = array();

		foreach ($pulls as $pull)
		{
			$activePulls[] = $pull->number;
		}

		$query = $this->_db->getQuery(true);

		$query->from($this->_tbl);
		$query->select('pull_id');

		$this->_db->setQuery($query);

		$entries = $this->_db->loadColumn();

		$query->clear();

		$query->delete($this->_tbl);

		foreach ($entries as $entry)
		{
			if(in_array($entry, $activePulls))
			{
				continue;
			}

			//-- Delete the pull
			$query->clear('where');

			$query->where('pull_id='.$entry);

			$this->_db->setQuery($query);
			$this->_db->query();

			//-- Let's also delete the html file -- @todo: move
			if(file_exists(PATH_OUTPUT.'/'.$entry.'.html'))
			{
				unlink(PATH_OUTPUT.'/'.$entry.'.html');
			}
		}
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
