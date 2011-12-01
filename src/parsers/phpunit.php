<?php
class PullTesterParserPhpUnit
{
	public static function parse($debug, JTable $pullTable)
	{
		$result = new TestResult;

		if ( ! file_exists(PATH_CHECKOUTS . '/pulls/build/logs/junit.xml'))
		{
			$result->error = 'Unit Tests log missing. Tests failed to execute.';
			$result->debugMessages[] = PullTesterHelper::stripLocalPaths($debug);

			return $result;
		}

		$contents = JFile::read(PATH_CHECKOUTS.'/pulls/build/logs/junit.xml');

		if($contents)
		{
			$contents = PullTesterHelper::stripLocalPaths($contents);

			JFile::write(PATH_OUTPUT.'/logs/'.$pullTable->pull_id.'junit.xml', $contents);
		}

		//....HEHO - Infinite loop in JError... ;-((((
		// $xml = JFactory::getXML(PATH_CHECKOUTS.'/pulls/build/logs/junit.xml');
		// do it by hand..

		// Disable libxml errors and allow to fetch error information as needed
		libxml_use_internal_errors(true);

		$xml = simplexml_load_file(PATH_CHECKOUTS.'/pulls/build/logs/junit.xml');

		if (empty($xml))
		{
			// There was an error
			$result->error = 'Unit Tests log corrupt.';

			$result->debugMessages[] = PullTesterHelper::stripLocalPaths($debug);

			foreach (libxml_get_errors() as $error)
			{
				$result->debugMessages[] = PullTesterHelper::stripLocalPaths($error->message);
			}

			return $result;
		}
		else
		{
			//-- @TODO use simple_xml to parse xml files..
			// var_dump($xml);
		}

		$phpUnitTable = JTable::getInstance('Phpunit', 'Table');

		$reader = new XMLReader;
		$reader->open(PATH_CHECKOUTS.'/pulls/build/logs/junit.xml');

		while ($reader->read() && $reader->name !== 'testsuite');

		$phpUnitTable->tests = $reader->getAttribute('tests');
		$phpUnitTable->assertions = $reader->getAttribute('assertions');
		$phpUnitTable->failures = $reader->getAttribute('failures');
		$phpUnitTable->errors = $reader->getAttribute('errors');
		$phpUnitTable->time = $reader->getAttribute('time');
		$phpUnitTable->pulls_id = $pullTable->id;

		$phpUnitTable->store();

		while ($reader->read())
		{
			if ($reader->name == 'error')
			{
				$s = $reader->readString();

				if($s)
				{
					$s = preg_replace('#\/[A-Za-z\/]*pulls#', '', PullTesterHelper::stripLocalPaths($s));
					$result->errors[] = $s;
				}
			}

			if ($reader->name == 'failure')
			{
				$s = $reader->readString();

				if($s)
				{
					$s = preg_replace('#\/[A-Za-z\/]*pulls#', '', PullTesterHelper::stripLocalPaths($s));
					$result->failures[] = $s;
				}
			}
		}

		$reader->close();

		return $result;
	}
}
