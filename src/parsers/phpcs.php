<?php
class PullTesterParserPhpCS
{
	public static function parse(JTable $pullTable)
	{
		$result = new testResult;

		if ( ! file_exists(PATH_CHECKOUTS . '/pulls/build/logs/checkstyle.xml'))
		{
			$result->error = 'Checkstyle analysis not found.';

			return;
		}

		copy(PATH_CHECKOUTS.'/pulls/build/logs/checkstyle.xml'
		, PATH_OUTPUT.'/test/'.$pullTable->pull_id.'checkstyle.xml');

		$numWarnings = 0;
		$numErrors = 0;

		$warnings = array();
		$errors = array();

		$reader = new XMLReader();
		$reader->open(PATH_CHECKOUTS.'/pulls/build/logs/checkstyle.xml');

		while ($reader->read())
		{
			if ($reader->name == 'file')
			{
				$fName = $reader->getAttribute('name');

				//-- @todo: strip the *right* path...
				$fName = str_replace(PATH_CHECKOUTS . '/pulls/', '', $fName);
			}

			if ($reader->name == 'error')
			{
				if ($reader->getAttribute('severity') == 'warning')
				{
					$e = new stdClass;
					$e->file = $fName;
					$e->line = (int)$reader->getAttribute('line');
					$e->message = $reader->getAttribute('message');

					$result->warnings[] = $e;
				}

				if ($reader->getAttribute('severity') == 'error')
				{
					$e = new stdClass;
					$e->file = $fName;
					$e->line = (int)$reader->getAttribute('line');
					$e->message = $reader->getAttribute('message');

					$result->errors[] = $e;
				}
			}
		}

		$reader->close();

		$phpCsTable = JTable::getInstance('Checkstyle', 'Table');
		$phpCsTable->errors = count($result->errors);
		$phpCsTable->warnings = count($result->warnings);
		$phpCsTable->pulls_id = $pullTable->id;

		$phpCsTable->store();

		return $result;
	}
}
