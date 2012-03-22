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
 * -v    Be verbose.
 *
 * @package     Joomla.Documentation
 * @subpackage  Application
 *
 * @copyright   Copyright (C) 2005 - 2012 Open Source Matters, Inc. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE
 */

'cli' == PHP_SAPI || die('This script must be executed from the command line.');

version_compare(PHP_VERSION, '5.3', '>=') || die('This script requires PHP >= 5.3');

define('_JEXEC', 1);

error_reporting(E_ALL);

/**
 * Bootstrap the Joomla! Platform.
 */
require getenv('JOOMLA_PLATFORM_PATH').'/libraries/import.legacy.php';

define('JPATH_BASE', __DIR__);
define('JPATH_SITE', __DIR__);

jimport('joomla.filesystem.file');
jimport('joomla.filesystem.folder');

require 'helper.php';
require 'parsers/phpunit.php';
require 'parsers/phpcs.php';
require 'formats/markdown.php';
require 'formats/html.php';

require 'dbdrivers/sqlite.php';

JError::$legacy = false;

/**
 * Ian's PullTester
 */
class PullTester extends JApplicationCli
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
		define('PATH_CHECKOUTS', dirname(dirname(__FILE__)).'/checkouts');
		define('PATH_DBS', dirname(dirname(__FILE__)).'/dbs');
		define('PATH_OUTPUT', $this->config->get('targetPath'));

		$this->verbose = $this->input->get('v', 0);

		$this->say('|-------------------------|');
		$this->say('|     Ian\'s PullTester    |');
		$this->say('|-------------------------|');

		$this->reset();

		$this->setup();
		$this->setUpTestDBs();


		$selectedPulls = $this->input->get('pull', '', 'HTML');
		$selectedPulls =($selectedPulls) ? explode(',', $selectedPulls) : array();

		$this->say('Creating/Updating the base repo...', false);
		$this->createUpdateRepo();
		$this->say('ok');

		$this->say('Fetching pull requests...', false);

		$pulls = $this->github->pulls->getList(
			  $this->config->get('github_project')
			, $this->config->get('github_repo')
			, 'open', 0, 100
		);

		$this->say('Processing '.count($pulls).' pulls...');

		JTable::getInstance('Pulls', 'Table')->update($pulls);

		$cnt = 1;
		$lapTime = microtime(true);

		foreach($pulls as $pull)
		{
			if($selectedPulls
			&& ! in_array($pull->number, $selectedPulls))
			{
				$this->say('Skipping pull '.$pull->number);
				$cnt ++;

				continue;
			}

			$this->line();
			$this->testResults = new stdClass;
			$this->testResults->error = '';

			$this->say('Processing pull '.$pull->number.'...', false);
			$this->say(sprintf('(%d/%d)...', $cnt, count($pulls)), false);

			$forceUpdate =($selectedPulls) ? true : false;
			$this->processPull($pull, $forceUpdate);

			$t = microtime(true) - $lapTime;

			$this->say('Finished in '.$t.' secs');

			$cnt ++;
			$lapTime += $t;
		}//foreach

		$this->line();
		$this->line();

		$totalTime = microtime(true) - $this->startTime;

		$this->say('Generating stats...', false);
		$content = PullTesterFormatHtml::formatIndex($this->getIndexData(), $totalTime);
		file_put_contents(PATH_OUTPUT.'/index.html', $content);
		$this->say('ok');

		$this->line();

		$this->say(sprintf('Total time: %s secs.', $totalTime));

		$this->close();
	}

	public function close($code = 0)
	{
		$this->line();

		$this->say('Shutting down...', false);

		// Shut down postgres server
		exec('pg_ctl -D '.PATH_DBS.'/postgres/ stop', $output, $ret);

		foreach ($output as $o) $this->say($o);

		if($ret) $this->say('Ret: '.$ret);

		$this->line();

		$this->say('Finished =;)');

		$this->line();
		$this->line();

		if($this->verbose)
		exec("kdialog --msgbox 'The PullTester has finished his job'");

		return parent::close($code);
	}

	protected function setup()
	{
		$this->say('Setup...', false);

		$this->startTime = microtime(true);
		$this->options = new stdClass;

		$config = new JRegistry(array(
		'username' => $this->config->get('github_username')
		, 'password' => $this->config->get('github_password'))
		);

		$this->github = new JGithub($config);

		JTable::addIncludePath(JPATH_BASE.'/tables');

		if('sqlite' == $this->config->get('dbtype'))
		{
			$path = $this->config->get('db');

			if( ! file_exists($path))
			{
				$this->say('Database not found ! creating...', false);

				$this->setUpDB();
			}
		}
		$this->table = JTable::getInstance('Pulls', 'Table');

		$this->say('ok');

		$this->line();
		$this->say('*** Checkout dir :'.PATH_CHECKOUTS);
		$this->say('*** Target dir   :'.PATH_OUTPUT);
		$this->line();

		$this->say('Creating base directories...', false);

		if( ! file_exists(PATH_CHECKOUTS))
		mkdir(PATH_CHECKOUTS);

		if( ! file_exists(PATH_OUTPUT))
		throw new Exception('Invalid output directory: '.PATH_OUTPUT);

		if( ! file_exists(PATH_OUTPUT.'/logs/'))
		mkdir(PATH_OUTPUT.'/logs/');

		if( ! file_exists(PATH_OUTPUT.'/pulls/'))
		mkdir(PATH_OUTPUT.'/pulls/');

		$this->say('ok');


		return $this;
	}

	protected function setUpTestDBs()
	{
		$this->say('Checking test databases...', false);

		$this->say('postgres...', false);

		if( ! JFolder::exists(PATH_DBS.'/postgres'))
		{
			// Create PostreSQL db - @todo create the db
//			throw new Exception('Please create the postgres db and fill it - working on automatisation =;)');
		}

		// Start the PostgreSQL server
		exec('pg_ctl -l '.PATH_DBS.'/postgres.log -D '.PATH_DBS.'/postgres/ start & 2>&1', $output, $ret);

		foreach ($output as $o) $this->say($o);

		if ($ret) $this->say('Ret: '.$ret);

		$this->say('ok');
	}

	protected function setUpDB()
	{
		JFolder::create(JPATH_BASE.'/db');

		$path = $this->config->get('db');

		$db = new PDO('sqlite:'.$path);

		$sql = JFile::read(JPATH_BASE.'/sql/pulltester.sqlite.sql');

		$queries = explode(';', $sql);

		foreach ($queries as $query)
		{
			if( ! trim($query))
			continue;

			if( ! $db->query(trim($query.'')))
			{
				$a = $db->errorInfo();
				$b = $db->errorCode();
				throw new Exception($db->errorInfo(), $db->errorCode());
			}

		}//foreach

		return $this;
	}//function

	protected function processPull($pull, $forceUpdate = false)
	{
		$results = new stdClass;

		$number = $pull->number;

		try
		{
			$pullRequest = $this->github->pulls->get(
				$this->config->get('github_project')
				, $this->config->get('github_repo')
				, $number
			);
		}
		catch (Exception $e)
		{
			echo 'Error Getting Pull Request - JSON Error: '.$e->getMessage."\n";
			return;
		}

		$pullData = $this->loadPull($pullRequest);

		$changed = false;

		// if we haven't processed this pull request before or if new commits have been made
		// against our repo or the head repo
		if ($forceUpdate
		|| ! $pullData
		|| $this->table->head != $pullRequest->head->sha
		|| $this->table->base != $pullRequest->base->sha)
		{
			if($forceUpdate)
			$this->say('update forced...', false);

			// Update the table
			$this->table->head = $pullRequest->head->sha;
			$this->table->base = $pullRequest->base->sha;
			$this->table->mergeable =($pullRequest->mergeable) ? 1 : 0;

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
		if($this->table->loadByNumber($pull->number))
		{
			return true;
		}

		$this->table->reset();

		$this->table->id = null;
		$this->table->pull_id = $pull->number;
		$this->table->head = $pull->head->sha;
		$this->table->base = $pull->base->sha;
		$this->table->mergeable = $pull->mergeable;
		$this->table->title = $pull->title;
		$this->table->user = $pull->user->login;
		$this->table->avatar_url = $pull->user->avatar_url;
		$this->table->data = 'X';

		$this->table->store();

		return false;
	}

	protected function createUpdateRepo()
	{
		if (file_exists(PATH_CHECKOUTS . '/pulls'))
		{
			if ($this->input->get('update'))
			{
				//-- Update existing repository
				chdir(PATH_CHECKOUTS.'/pulls');
				exec('git checkout master &>/dev/null');
				exec('git fetch origin');
				exec('git merge origin/master');
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

		exec('git checkout master &>/dev/null');

		//-- Just in case, if, for any oscure reason, the branch we are trying to create already exists...
		//-- git wont switch to it and will remain on the 'master' branch so...
		//-- let's first try to delete it =;)
		exec('git branch -D pull'.$pull->number.' &>/dev/null');

		exec('git checkout -b pull'.$pull->number);

		$this->say('Fetch repo: '.$pull->user->login);

		exec('git fetch ' . $pull->user->login);

		$this->say('Merge repo: '.$pull->user->login . '/' . $pull->head->ref);
		exec('git merge ' . $pull->user->login . '/' . $pull->head->ref);

		// 		exec('ant clean');
		exec('mkdir build/logs 2>/dev/null');
		exec('rm build/logs/junit.xml 2>/dev/null');
		exec('touch build/logs/checkstyle.xml');

		$this->say('Running PHPUnit...', false);

		ob_start();

		echo shell_exec('phpunit 2>&1');

		$this->phpUnitDebug = ob_get_clean();

		$this->say('OK');

		$this->say('Running CodeSniffer...', false);

		$standard = $this->config->get('codeStandardsPath');
		if( ! $standard) $standard = 'build/phpcs/Joomla';

		ob_start();

		echo shell_exec('phpcs'
		.' --report=checkstyle'
		.' --report-file='.PATH_CHECKOUTS.'/pulls/build/logs/checkstyle.xml'
		.' --standard='.$standard
		.' libraries/joomla libraries/platform.php libraries/loader.php libraries/import.php'
		.' 2>&1');

		$this->phpCsDebug = ob_get_clean();
		$this->say('OK');

		//-- Fishy things happen all along the way...
		//-- Let's use the -f (force) option..
		exec('git checkout -f master &>/dev/null');

		exec('git branch -D pull' . $pull->number);
	}

	protected function publishResults($pullRequest)
	{

		$report = PullTesterFormatMarkdown::format($pullRequest, $this->testResults);
		JFile::write(PATH_CHECKOUTS . '/pull' . $pullRequest->number . '.test.txt', $report);

		$report = PullTesterFormatHtml::format($pullRequest, $this->testResults);
		JFile::write(PATH_OUTPUT . '/pulls/' . $pullRequest->number . '.html', $report);

		//echo $this->report;
	}

	protected function reset()
	{
		$reset = $this->input->get('reset');

		if( ! $reset)
		return;

		$this->say('Resetting...', false);

		$hard =('hard' == $reset);

		if($hard) $this->say('HARD...', false);

		if('sqlite' == $this->config->get('dbtype'))
		{
			// SQLite database: delete the file
			$this->say('deleting the database file...', false);

			JFolder::delete(JPATH_BASE.'/db');

			$this->setUpDB();
		}
		else
		{
			$this->say('truncating tables...', false);

			JTable::getInstance('Checkstyle', 'Table')->truncate();
			JTable::getInstance('Phpunit', 'Table')->truncate();
			JTable::getInstance('Pulls', 'Table')->truncate();
		}

		$this->say('deleting files...', false);

		//-- Remove the checkout files
		if($hard)
		{
			$this->say('ALL FILES...', false);

			if(JFolder::exists(PATH_CHECKOUTS))
			JFolder::delete(PATH_CHECKOUTS);

			JFolder::create(PATH_CHECKOUTS);
		}
		else
		{
			JFile::delete(JFolder::files(PATH_CHECKOUTS, '.', false, true));
		}

		//-- Delete all HTML files
		if(JFolder::exists(PATH_OUTPUT.'/pulls'))
		JFile::delete(JFolder::files(PATH_OUTPUT.'/pulls', '.', false, true));

		if(JFolder::exists(PATH_OUTPUT.'/logs'))
		JFile::delete(JFolder::files(PATH_OUTPUT.'/logs', '.', false, true));

		$this->say('OK');

		return true;
	}

	protected function getIndexData()
	{
		$db = JFactory::getDbo();

		$query = $db->getQuery(true);

		$query->from('pulls AS p');
		$query->leftJoin('phpCsResults AS cs on p.id=cs.pulls_id');
		$query->leftJoin('phpunitResults AS pu on p.id=pu.pulls_id');

		$query->select('p.pull_id, p.user, p.mergeable');
		$query->select('cs.warnings AS CS_warnings, cs.errors AS CS_errors');
		$query->select('pu.failures AS UT_failures, pu.errors AS UT_errors');

		$query->order('p.pull_id DESC');

		$db->setQuery($query);

		return $db->loadObjectList();
	}

	/**
	 * Method to load a PHP configuration class file based on convention and return the instantiated data object.
	 * You will extend this method in child classes to provide configuration data from whatever data source is relevant
	 * for your specific application.
	 *
	 * @param string $file
	 * @param string $class
	 *
	 * @return  mixed  Either an array or object to be loaded into the configuration object.
	 *
	 * @since   11.1
	 */
	protected function fetchConfigurationData($file = '', $class = 'JConfig')
	{
		require_once $this->input->get('config', 'config.php');

		if( ! class_exists('JConfig'))
		{
			return false;
		}

		return new JConfig;
	}

	protected function say($text = '', $nl = true)
	{
		if( ! $this->verbose)
		{
			return;
		}

		$this->out($text, $nl);
	}//function

	protected function line()
	{
		$this->say(str_repeat('.', 70));
	}
}//class

/**
 * Test result class.
 *
 * @package     PullTester
 * @subpackage  Helper classes
 * @since       1.0
 */
class TestResult
{
	public $warnings = array();
	public $errors = array();
	public $failures = array();

	public $messages = array();

	public $error = '';
	public $debugMessages = array();

	public function addMessage($message)
	{
		$this->messages[] = $message;

		return $this;
	}//function
}//class

try
{
	// Execute the application.
	JApplicationCli::getInstance('PullTester')->execute();

	exit(0);
}
catch (Exception $e)
{
	// An exception has been caught, just echo the message.
	fwrite(STDOUT, $e->getMessage() . "\n");

	exit($e->getCode());
}//try
