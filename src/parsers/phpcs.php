<?php
class PullTesterParserPhpCS
{
	public static function parse($debug, JTable $pullTable)
	{
		$result = new testResult;
		$path = PATH_CHECKOUTS . '/pulls/build/logs/checkstyle.xml';

		if ( ! file_exists($path) || filesize($path) < 1)
		{
			$result->error = 'Checkstyle analysis not found.';
			$result->debugMessages[] = PullTesterHelper::stripLocalPaths($debug);

			return $result;
		}

		$contents = JFile::read(PATH_CHECKOUTS.'/pulls/build/logs/checkstyle.xml');
		$contents = PullTesterHelper::stripLocalPaths($contents);

		JFile::write(PATH_OUTPUT.'/logs/'.$pullTable->pull_id.'checkstyle.xml', $contents);

		$reader = new XMLReader;
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
