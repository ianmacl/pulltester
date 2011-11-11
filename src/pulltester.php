#!/usr/bin/php
<?php
/**
 * PullTester.
 *
 * Available options:
 * --update         Update the base repo.
 * --reset [hard]   Cleans tables and tatabase tables. "hard" also deletes the base repo.
 * --pull <number>  Process only a specific pull.
 *
 * -v               Be verbose.
 *
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
jimport('joomla.client.github2');

require 'helper.php';
require_once 'parsers/phpunit.php';
require_once 'parsers/phpcs.php';
require_once 'formats/markdown.php';
require_once 'formats/html.php';

JError::$legacy = false;

/**
 *
 */
class PullTester extends JCli
{
	protected $github = null;

	protected $table = null;

	protected $verbose = false;

	protected $phpUnitDebug = '';
	protected $phpCsDebug = '';

	/**
	 * @var testResult
	 */
	protected $testResults = null;

	protected $startTime = 0;

	protected $options = null;

	/**
	 * Execute the application.
	 *
	 * @return  void
	 *
	 * @since   11.1
	 */
	public function execute()
	{
		JTable::addIncludePath(JPATH_BASE.'/tables');

		$this->options = new stdClass;

		$this->startTime = microtime(true);

		$reset = $this->input->get('reset');

		$this->verbose = $this->input->get('v', 0);

		$selectedPull = $this->input->get('pull', 0, 'INT');

		$this->output('----------------------');
		$this->output('-- Ian\'s PullTester --');
		$this->output('----------------------');

		if($reset)
		{
			$this->output('Resetting...', false);
			$this->reset('hard' == $reset);
			$this->output('OK');
		}

		$this->output('Creating/Updating the base repo...', false);
		$this->createRepo();
		$this->output('OK');

		$this->output('Fetching pull requests...', false);
		$this->github = new JGithub(array('username' => $this->config->get('github_username'), 'password' => $this->config->get('github_password')));
		$pulls = $this->github->pulls->getAll($this->config->get('github_project'), $this->config->get('github_repo'), 'open', 0, 100);
		$this->output('OK');

		JTable::getInstance('Pulls', 'Table')->update($pulls);

		$cnt = 1;

		foreach ($pulls as $pull)
		{
			if($selectedPull
			&& $selectedPull != $pull->number)
			{
				$this->output('Skipping pull '.$pull->number);
				$cnt ++;

				continue;
			}

			$this->testResults = new stdClass;
			$this->testResults->error = '';

			$this->output('Processing pull '.$pull->number.'...', false);
			$this->output(sprintf('(%d/%d)...', $cnt, count($pulls)), false);

			$forceUpdate =($selectedPull) ? true : false;
			$this->processPull($pull, $forceUpdate);

			$t = microtime(true);

			$this->output('Finished in '.($t - $this->startTime).' secs');
			$this->output('------------------------');

			$cnt ++;
		}

		$this->totalTime = microtime(true) - $this->startTime;

		$this->output('Generating stats...', false);
		$content = PullTesterFormatHtml::formatIndex($this->getIndexData(), $this->totalTime);
		file_put_contents(PATH_OUTPUT.'/index.html', $content);
		$this->output('OK');

		$this->output('Total time: '.$this->totalTime.' secs =;)');

		$this->close();
	}

	protected function processPull($pull, $forceUpdate = false)
	{
		$results = new stdClass;

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
		if ($forceUpdate
		|| ! $pullData
		|| $this->table->head != $pullRequest->head->sha
		|| $this->table->base != $pullRequest->base->sha)
		{
			if($forceUpdate)
			$this->output('update forced...', false);

			// Update the table
			$this->table->head = $pullRequest->head->sha;
			$this->table->base = $pullRequest->base->sha;
			$this->table->mergeable = $pullRequest->mergeable;

			$this->table->store();

			$changed = true;

			// Step 1: See if the pull request will merge
			// right now we do this strictly based on what github tells us

			if ($pullRequest->mergeable)
			{
				// Step 2: We try and git the repo and perform the build
				$this->build($pullRequest);
			}
			else
			{
				$this->testResults->error = 'This pull request could not be tested since the changes could not be cleanly merged.';

				// if it was mergeable before and it isn't mergeable anymore, report that
				if ($this->table->mergeable)
				{
					$this->testResults->error .= '...but it was mergeable before and it isn\'t mergeable anymore...';
				}
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

		if( ! file_exists(PATH_OUTPUT))
		throw new Exception('Invalid output directory: '.PATH_OUTPUT);

		if( ! file_exists(PATH_OUTPUT.'/test/'))
		{
			mkdir(PATH_OUTPUT.'/test/');
		}

		if( ! file_exists(PATH_OUTPUT.'/pulls/'))
		{
			mkdir(PATH_OUTPUT.'/pulls/');
		}

		if (file_exists(PATH_CHECKOUTS . '/pulls'))
		{
			if ($this->input->get('update'))
			{
				//-- Update existing repository
				chdir(PATH_CHECKOUTS.'/pulls');
				exec('git checkout staging');
				exec('git fetch origin');
				exec('git merge origin/staging');
			}
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
		$this->testResults->phpunit = PullTesterParserPhpUnit::parse($this->phpUnitDebug, $this->table);
		$this->testResults->phpcs = PullTesterParserPhpCS::parse($this->phpCsDebug, $this->table);
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

		$this->output('Merge repo: '.$pull->user->login . '/' . $pull->head->ref);
		exec('git merge ' . $pull->user->login . '/' . $pull->head->ref);

		// 		exec('ant clean');
		exec('mkdir build/logs 2>/dev/null');
		exec('rm build/logs/junit.xml 2>/dev/null');
		exec('touch build/logs/checkstyle.xml');

		$this->output('Running PHPUnit...', false);

		ob_start();
		// exec('ant phpunit');
		// exec('ant phpunit');

		echo shell_exec('phpunit 2>&1');

		$this->phpUnitDebug = ob_get_clean();

		$this->output('OK');

		$this->output('Running the CodeSniffer...', false);
		// exec('ant phpcs');
		ob_start();
		echo shell_exec('phpcs'
		.' --report=checkstyle'
		.' --report-file='.PATH_CHECKOUTS.'/pulls/build/logs/checkstyle.xml'
		.' --standard='
		.'/home/elkuku/libs/joomla/'
		// .$basedir
		.'build/phpcs/Joomla'
		.' libraries/joomla'
		.' 2>&1');
		$this->phpCsDebug = ob_get_clean();
		$this->output('OK');

		//-- Fishy things happen all along the way...
		//-- Let's use the -f (force) option..
		exec('git checkout -f staging');

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

		//curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
		//curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($request));

		$report = PullTesterFormatMarkdown::format($pullRequest, $this->testResults);
		file_put_contents(PATH_CHECKOUTS . '/pull' . $pullRequest->number . '.test.txt', $report);

		$report = PullTesterFormatHtml::format($pullRequest, $this->testResults);
		file_put_contents(PATH_OUTPUT . '/pulls/' . $pullRequest->number . '.html', $report);

		//echo $this->report;
	}

	protected function reset($hard = false)
	{
		jimport('joomla.filesystem.file');

		$this->output('truncating tables...', false);

		JTable::getInstance('Checkstyle', 'Table')->truncate();
		JTable::getInstance('Phpunit', 'Table')->truncate();
		JTable::getInstance('Pulls', 'Table')->truncate();

		$this->output('deleting files...', false);

		//-- Remove the checkout files
		if($hard)
		{
			$this->output('ALL FILES...', false);

			if(JFolder::exists(PATH_CHECKOUTS))
			{
				JFolder::delete(PATH_CHECKOUTS);
			}
		}
		else
		{
			JFile::delete(JFolder::files(PATH_CHECKOUTS, '.', false, true));
		}

		//-- Delete all HTML files
		if(JFolder::exists(PATH_OUTPUT.'/pulls'))
		{
			JFile::delete(JFolder::files(PATH_OUTPUT.'/pulls', '.', false, true));
		}

		if(JFolder::exists(PATH_OUTPUT.'/test'))
		{
			JFile::delete(JFolder::files(PATH_OUTPUT.'/test', '.', false, true));
		}
	}

	protected function getIndexData()
	{
		$db = JFactory::getDbo();

		$query = $db->getQuery(true);

		$query->from('pulls AS p');
		$query->leftJoin('phpCsResults AS cs on p.id=cs.pulls_id');
		$query->leftJoin('phpunitResults AS pu on p.id=pu.pulls_id');

		$query->select('p.pull_id, p.mergeable');
		$query->select('cs.warnings AS CS_warnings, cs.errors AS CS_errors');
		$query->select('pu.failures AS Unit_failures, pu.errors AS Unit_errors');

		$query->order('p.pull_id DESC');

		$db->setQuery($query);

		$data = $db->loadObjectList();

		return $data;
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
		require_once $configFile;
		if (!class_exists('JConfig'))
		{
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
}//class

class TestResult
{
	public $warnings = array();
	public $errors = array();
	public $failures = array();

	public $messages = array();

	public $error = '';
	public $debugMessages = array();
}

try
{
	// Execute the application.
	JCli::getInstance('PullTester')->execute();

	exit(0);
}
catch (Exception $e)
{
	// An exception has been caught, just echo the message.
	fwrite(STDOUT, $e->getMessage() . "\n");

	exit($e->getCode());
}//try
