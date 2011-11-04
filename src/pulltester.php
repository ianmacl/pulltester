#!/usr/bin/php
<?php
/**
 * @package     Joomla.Documentation
 * @subpackage  Application
 *
 * @copyright   Copyright (C) 2005 - 2011 Open Source Matters, Inc. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE
 */

define('_JEXEC', 1);

/**
 * Turn on strict error reporting during development
 */
ini_set('display_errors', '1');
ini_set('error_reporting', E_ALL | E_STRICT);

/**
 * Bootstrap the Joomla! Platform.
 */
require_once dirname(dirname(__FILE__)) . '/platform/libraries/import.php';

define('JPATH_BASE', dirname(__FILE__));
define('JPATH_SITE', JPATH_BASE);
define('PATH_CHECKOUTS', dirname(dirname(__FILE__)).'/checkouts');

define('PATH_OUTPUT', '/home/elkuku/eclipsespace/indigogit3/pulltester-gh-pages');

jimport('joomla.application.cli');
jimport('joomla.database');
jimport('joomla.database.table');
jimport('joomla.client.github');

JError::$legacy = false;

/**
 *
 */
class PullTester extends JCli
{
	protected $github = null;

	protected $table = null;

	protected $report = null;

	protected $reportHtml = '';

	protected $verbose = false;

	protected $phpUnitDebug = '';

	protected $startTime = 0;

	/**
	 * Execute the application.
	 *
	 * @return  void
	 *
	 * @since   11.1
	 */
	public function execute()
	{
		$this->startTime = microtime(true);

		$this->verbose = $this->input->get('v', 0);

		$selectedPull = $this->input->get('pull', 0, 'INT');

		$this->output('----------------------');
		$this->output('-- Ian\'s PullTester --');
		$this->output('----------------------');

		JTable::addIncludePath(JPATH_BASE.'/tables');

		$this->github = new JGithub(array('username' => $this->config->get('github_username'), 'password' => $this->config->get('github_password')));

		$this->output('Fetching pull requests...', false);

		$pulls = $this->github->pulls->getAll($this->config->get('github_project'), $this->config->get('github_repo'), 'open', 0, 100);

		$this->output('OK');

		$this->output('Creating the base repo...', false);
		$this->createRepo();
		$this->output('OK');

		foreach ($pulls AS $pull)
		{
			if($selectedPull && $selectedPull != $pull->number)
			{
				$this->output('Skipping pull '.$pull->number);
				continue;
			}

			$this->output('Processing pull '.$pull->number.'...');

			$this->report = '';
			$this->reportHtml = '';
			$this->processPull($pull);

			$this->output('OK');

			$t = microtime(true);

			$this->output(($t - $this->startTime).' secs');
			$this->output('------------------------');
		}

		$this->totalTime = microtime(true) - $this->startTime;

		$this->output('Finished in '.$this->totalTime.' secs =;)');

		$this->generateStatsTable();

		$this->close();
	}

	protected function getIndexData()
	{
		$db = JFactory::getDbo();

		$query = $db->getQuery(true);

		$query->from('pulls AS p');
		$query->select('p.pull_id, p.mergeable');
		$query->select('cs.warnings AS CS_warnings, cs.errors AS CS_errors');
		$query->select('pu.failures AS Unit_failures, pu.errors AS Unit_errors');

		$query->leftJoin('phpCsResults AS cs on p.id=cs.pulls_id');
		$query->leftJoin('phpunitResults AS pu on p.id=pu.pulls_id');

		$db->setQuery($query);

		$data = $db->loadObjectList();

		return $data;
	}

	protected function processPull($pull)
	{
		$results = new stdClass;

		$db = JFactory::getDbo();

		$number = $pull->number;

		try {
			$pullRequest = $this->github->pulls->get($this->config->get('github_project'), $this->config->get('github_repo'), $number);
		} catch (Exception $e) {
			echo 'Error Getting Pull Request - JSON Error: '.$e->getMessage."\n";
			return;
		}

		$pullData = $this->loadPull($pullRequest);

		$changed = false;

		// if we haven't processed this pull request before or if new commits have been made against our repo or the head repo
		if (!$pullData || $this->table->head != $pullRequest->head->sha || $this->table->base != $pullRequest->base->sha) {

			// Step 1: See if the pull request will merge
			// right now we do this strictly based on what github tells us

			if ($pullRequest->mergeable)
			{
				// Step 2: We try and git the repo and perform the build
				$this->build($pullRequest);
				$changed = true;
			}
			else
			{
				$this->report .= 'This pull request could not be tested since the changes could not be cleanly merged.';
				$this->reportHtml .= '<p class="img24 img-fail">This pull request could not be tested since the changes could not be cleanly merged.</p>'."\n";

				// if it was mergeable before and it isn't mergeable anymore, report that
				if ($this->table->mergeable)
				{
					$this->report .= '...but it was mergeable before and it isn\'t mergeable anymore...';
					$this->reportHtml .= '<p class="img24 img-fail">...but it was mergeable before and it isn\'t mergeable anymore...</p>'."\n";

				}

				$changed = true;
			}
		}

		if ($changed)
		{
			if ($pullRequest->mergeable)
			{
				$this->processResults($pullRequest);
			}

			$this->publishResults($pullRequest);
		}
	}

	public function loadPull($pull)
	{
		$this->table = JTable::getInstance('Pulls', 'Table');
		if (!$this->table->loadByNumber($pull->number))
		{
			$this->table->reset();
			$this->table->id = 0;
			$this->table->pull_id = $pull->number;
			$this->table->head = $pull->head->sha;
			$this->table->base = $pull->base->sha;
			$this->table->mergeable = $pull->mergeable;
			$this->table->store();
			return false;
		}

		return true;
	}

	protected function createRepo()
	{
		if (!file_exists(PATH_CHECKOUTS))
		{
			mkdir(PATH_CHECKOUTS);
		}

		if (file_exists(PATH_CHECKOUTS . '/pulls'))
		{
			//-- Update existing repository
			// chdir(PATH_CHECKOUTS.'/pulls');
			// exec('git checkout staging');
			// exec('git fetch origin');
			// exec('git merge origin/staging');
		}
		else
		{
			//-- Clone repository
			chdir(PATH_CHECKOUTS);
			exec('git clone git@github.com:joomla/joomla-platform.git pulls');
		}
	}

	protected function processResults($results)
	{
		$this->parsePhpUnit();
		$this->parsePhpCs();
	}

	protected function build($pull)
	{
		chdir(PATH_CHECKOUTS . '/pulls');

		// We add the users repo to our remote list if it isn't already there
		if (!file_exists(PATH_CHECKOUTS . '/pulls/.git/refs/remotes/' . $pull->user->login))
		{
			exec('git remote add ' . $pull->user->login . ' ' . $pull->head->repo->git_url);
		}

		exec('git checkout staging 2>/dev/null');

		//-- Just in case, if, for any oscure reason, the branch we are trying to create already exists...
		//-- git wont switch to it and will remain on the 'staging' branch so...
		//-- let's first try to delete it =;)
		exec('git branch -D pull'.$pull->number.' 2>/dev/null');

		exec('git checkout -b pull'.$pull->number);

		$this->output('Fetch repo: '.$pull->user->login);

		exec('git fetch ' . $pull->user->login);

		exec('git merge ' . $pull->user->login . '/' . $pull->head->ref);

		// 		exec('ant clean');
		exec('rm build/logs/junit.xml 2>/dev/null');

		$this->output('Running PHPUnit...', false);

		ob_start();
		// 		exec('ant phpunit');
		// 		exec('ant phpunit');

		echo shell_exec('phpunit 2>&1');

		$this->phpUnitDebug = ob_get_clean();

		$this->output('OK');

		$this->output('Running the CodeSniffer...', false);
		// 		exec('ant phpcs');
		exec('mkdir build/logs 2>/dev/null');
		exec('touch build/logs/checkstyle.xml');

		echo shell_exec('phpcs'
		// .' -p'
		.' --report=checkstyle'
		.' --report-file='.PATH_CHECKOUTS.'/pulls/build/logs/checkstyle.xml'
		.' --standard='
		.'/home/elkuku/libs/joomla/'
		// .$basedir
		.'build/phpcs/Joomla'
		.' libraries/joomla'
		.' 2>&1');

		$this->output('OK');

		exec('git checkout staging 2>/dev/null');
		exec('git branch -D pull' . $pull->number);
	}

	protected function publishResults($pullRequest)
	{

		$project = $this->config->get('github_project');
		$repo = $this->config->get('github_repo');
		//$url = 'https://api.github.com/repos/'.$project.'/'.$repo.'/issues/'.$pullRequest->number.'/comments';
		//$ch = curl_init();
		//curl_setopt($ch, CURLOPT_URL, $url);
		//curl_setopt($ch, CURLOPT_POST, true);
		//curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
		//curl_setopt($ch, CURLOPT_USERPWD, $this->config->get('github_user').':'.$this->config->get('github_password'));

		//$request = new stdClass;
		//$request->body = '';

		if ($pullRequest->base->ref != 'staging') {
			$this->report .= "\n\n" . '**WARNING! Pull request is not against staging!**';
			$this->reportHtml .= '<h2 class="img24 img-fail">Pull request is not against staging!</h2>'."\n";
		}

		//curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
		//curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($request));
		file_put_contents(PATH_CHECKOUTS . '/pull' . $pullRequest->number . '.txt', $this->report);

		$html = '';
		$html .= $this->getHead($pullRequest->number.' Test results');

		$html .= '<body>'."\n";

		$html .= '<a href="index.html">&lArr; Index</a>'."\n";

		$html .= '<h1>Results for <a href="https://github.com/joomla/joomla-platform/pull/'.$pullRequest->number.'">'
		.'pull request #'.$pullRequest->number.'</a></h1>'."\n";

		$html .= $this->reportHtml;

		$html .= '<div class="footer">Generated on '.date('d-M-Y H:i:s P T e').'</div>';
		$html .= '</body></html>';

		file_put_contents(PATH_OUTPUT . '/' . $pullRequest->number . '.html', $html);

		//echo $this->report;
	}

	protected function generateStatsTable()
	{
		$data = $this->getIndexData();

		$html = $this->getHead('Test results overview');

		$html .= '<body>';

		$html .= '<h1>Test results overview</h1>';

		$html .= '<table>';

		if(isset($data[0]))
		{
			$html .= '<tr>';

			foreach ($data[0] as $key => $v)
			{
				$html .= '<th>'.$key.'</th>';
			}
			$html .= '</tr>';
		}

		foreach ($data as $entry)
		{
			$html .= '<tr>';

			$mergeable = true;

			foreach ($entry as $key => $value)
			{
				$replace = '%s';

				if( ! $mergeable)
				{
					$replace = '-';
				}
				elseif('' == $value)
				{
					$replace = '<b style="color: red;">-?-</b>';
				}
				else
				{
					switch ($key)
					{
						case 'pull_id':
							$replace = '<a href="%1$s.html">Pull %1$s</a>';
							break;

						case 'CS_warnings':
							$replace =(0 == $value) ? '&radic;' : '<b style="color: orange;">%d</b>';
							break;

						case 'CS_errors':
						case 'Unit_failures':
						case 'Unit_errors':
							$replace =(0 == $value) ? '&radic;' : '<b style="color: red;">%d</b>';
							break;

						case 'mergeable':
							$mergeable =($value) ? true : false;
							$value =($value) ? '&radic;' : '<b style="color: red">** NO **</b>';
							break;
					}//switch
				}

				$html .= '<td>'.sprintf($replace, $value).'</td>';
			}//foreach

			$html .= '</tr>'."\n";
		}//foreach

		$html .= '</table>';

		$html .= '<div class="footer">'
		. '</small><small><pre>C\'mon, I spent '.$this->totalTime.' seconds generating this pages.... have Fun =;)</pre></small></small>'
		.'Generated on '.date('d-M-Y H:i:s P T e')
		.'</div>';

		$html .= '</body></html>';

		file_put_contents(PATH_OUTPUT.'/index.html', $html);
	}

	protected function parsePhpUnit()
	{
		$this->reportHtml .= '<h2>Unit Tests</h2>'."\n";

		if ( ! file_exists(PATH_CHECKOUTS . '/pulls/build/logs/junit.xml'))
		{
			$this->report .= 'Test log missing. Tests failed to execute.' . "\n";
			$this->reportHtml .= '<h3 class="img24 img-fail">Test log missing. Tests failed to execute.</h3>'."\n";
			$d = $this->phpUnitDebug;
			$d = str_replace(PATH_CHECKOUTS . '/pulls/', '', $d);
			$this->reportHtml .= '<pre class="debug">'.$d.'</pre>';

			return;
		}

		$phpUnitTable = JTable::getInstance('Phpunit', 'Table');

		$reader = new XMLReader();
		$reader->open(PATH_CHECKOUTS.'/pulls/build/logs/junit.xml');
		while ($reader->read() && $reader->name !== 'testsuite');

		$phpUnitTable->tests = $reader->getAttribute('tests');
		$phpUnitTable->assertions = $reader->getAttribute('assertions');
		$phpUnitTable->failures = $reader->getAttribute('failures');
		$phpUnitTable->errors = $reader->getAttribute('errors');
		$phpUnitTable->time = $reader->getAttribute('time');
		$phpUnitTable->pulls_id = $this->table->id;
		$phpUnitTable->store();

		$errors = array();
		$failures = array();

		while ($reader->read())
		{
			if ($reader->name == 'error')
			{
				$errors[] = preg_replace('#\/[A-Za-z\/]*pulls#', '', $reader->readString());
			}

			if ($reader->name == 'failure')
			{
				$failures[] = preg_replace('#\/[A-Za-z\/]*pulls#', '', $reader->readString());
			}
		}

		$reader->close();

		$s = sprintf('Unit testing complete. There were %1d failures and %2d errors from %3d tests and %4d assertions.'
		, $phpUnitTable->failures, $phpUnitTable->errors, $phpUnitTable->tests, $phpUnitTable->assertions);

		$this->report .= $s;

		$c =($phpUnitTable->failures || $phpUnitTable->errors) ? 'img-warn' : 'img-success';
		$this->reportHtml .= '<p class="img24 '.$c.'">'.$s.'</p>'."\n";

		if($errors)
		{
			$this->reportHtml .= '<h3>Errors</h3>';

			$this->reportHtml .= '<ol>';

			foreach($errors as $error)
			{
				if( ! $error)//regex produces emty errors :(
				continue;

				$this->reportHtml .= '<li><pre class="debug">'.htmlentities($error).'</pre></li>';
			}

			$this->reportHtml .= '</ol>';
		}

		if($failures)
		{
			$this->reportHtml .= '<h3>Failures</h3>';

			$this->reportHtml .= '<ol>';

			foreach($failures as $fail)
			{
				if( ! $fail)//regex produces emty errors :(
				continue;

				$this->reportHtml .= '<li><pre class="debug">'.htmlentities($fail).'</pre></li>';
			}

			$this->reportHtml .= '</ol>';
		}
	}

	protected function parsePhpCs()
	{
		$this->reportHtml .= '<h2>Checkstyle</h2>'."\n";

		if ( ! file_exists(PATH_CHECKOUTS . '/pulls/build/logs/checkstyle.xml'))
		{
			$this->report .= 'Checkstyle analysis not found.' . "\n";
			$this->reportHtml .= '<h3 class="img24 img-fail>Checkstyle analysis not found.</h3>'."\n";

			return;
		}

		$numWarnings = 0;
		$numErrors = 0;

		$warnings = array();
		$errors = array();

		$phpCsTable = JTable::getInstance('Checkstyle', 'Table');

		$reader = new XMLReader();
		$reader->open(PATH_CHECKOUTS.'/pulls/build/logs/checkstyle.xml');

		$details = '';

		$detailsHtml = '';

		$maxErrors = 10;

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
					$numWarnings++;
				}

				if ($reader->getAttribute('severity') == 'error')
				{
					$numErrors++;

					$detailsHtml .= '<li><tt>'.$fName.':'.$reader->getAttribute('line').'</tt><br />';
					$detailsHtml .= '<em>'.$reader->getAttribute('message').'</em></li>'."\n";

					if($numErrors <= $maxErrors)
					{
						$details .= $fName.':'.$reader->getAttribute('line')."\n";
						$details .= $reader->getAttribute('message')."\n";
					}
				}
			}
		}

		$phpCsTable->errors = $numErrors;
		$phpCsTable->warnings = $numWarnings;

		$phpCsTable->pulls_id = $this->table->id;
		$phpCsTable->store();
		$reader->close();

		$s = 'Checkstyle analysis reported ' . $numWarnings . ' warnings and ' . $numErrors . ' errors.' . "\n";
		$this->report .= $s;

		$c =($numErrors) ? 'img-warn' : 'img-success';
		$this->reportHtml .= '<p class="img24 '.$c.'">'.$s.'</p>'."\n";

		if($numErrors)
		{
			$this->report .= '**Checkstyle error details**'."\n".$details."\n";
			$this->reportHtml .= '<h3>Checkstyle error details</h3>'."\n".'<ul class="phpcs">'.$detailsHtml.'</ul>'."\n";
		}

		if($numErrors > $maxErrors)
		{
			$this->report .= '('.($numErrors - $maxErrors).' more errors)'."\n";
		}
	}

	protected function getHead($title)
	{
		$htmlHead = '';
		$htmlHead .= '<!doctype html><html><head><meta http-equiv="content-type" content="text/html; charset=UTF-8">';
		$htmlHead .= '<title>'.$title.'</title>';
		$htmlHead .= '<link href="assets/css/style.css" rel="stylesheet" type="text/css" />';
		$htmlHead .= '</head>'."\n";

		return $htmlHead;
	}

	/**
	 * Method to load a PHP configuration class file based on convention and return the instantiated data object.  You
	 * will extend this method in child classes to provide configuration data from whatever data source is relevant
	 * for your specific application.
	 *
	 * @return  mixed  Either an array or object to be loaded into the configuration object.
	 *
	 * @since   11.1
	 */
	protected function fetchConfigurationData($config = 'test')
	{
		$configFile = $this->input->get('config', 'config.php');
		require_once($configFile);
		if (!class_exists('JConfig')) {
			return false;
		}
		$config = new JConfig;

		return $config;
	}

	protected function output($text = '', $nl = true)
	{
		if( ! $this->verbose)
		return;

		$this->out($text, $nl);
	}
}

// Execute the application.
JCli::getInstance('PullTester')->execute();
