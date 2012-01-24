<?php
class PullTesterParserPhpCS
{
	public static function parse($debug, JTable $pullTable)
	{
		$result = new TestResult;
		$path = PATH_CHECKOUTS . '/pulls/build/logs/checkstyle.xml';

		if ( ! file_exists($path) || filesize($path) < 1)
		{
			$result->error = 'Checkstyle analysis not found.';
			$result->debugMessages[] = PullTesterHelper::stripLocalPaths($debug);

			return $result;
		}

		$contents = PullTesterHelper::stripLocalPaths(JFile::read(PATH_CHECKOUTS.'/pulls/build/logs/checkstyle.xml'));

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

		$table = JTable::getInstance('Checkstyle', 'Table');
		$table->errors = count($result->errors);
		$table->warnings = count($result->warnings);
		$table->pulls_id = $pullTable->id;

		$table->store();

		return $result;
	}
}
