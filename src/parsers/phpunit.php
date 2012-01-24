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
		}

		$table = JTable::getInstance('Phpunit', 'Table');

		$reader = new XMLReader;
		$reader->open(PATH_CHECKOUTS.'/pulls/build/logs/junit.xml');

		while ($reader->read() && $reader->name !== 'testsuite');

		$table->tests = $reader->getAttribute('tests');
		$table->assertions = $reader->getAttribute('assertions');
		$table->failures = $reader->getAttribute('failures');
		$table->errors = $reader->getAttribute('errors');
		$table->time = $reader->getAttribute('time');
		$table->pulls_id = $pullTable->id;

		$table->store();

		while ($reader->read())
		{
			if ($reader->name == 'error')
			{
				$s = $reader->readString();

				if($s)
				{
					$result->errors[] = PullTesterHelper::stripLocalPaths($s);
				}
			}

			if ($reader->name == 'failure')
			{
				$s = $reader->readString();

				if($s)
				{
					$result->failures[] = PullTesterHelper::stripLocalPaths($s);
				}
			}
		}//while

		$reader->close();

		return $result;
	}//function
}//class
