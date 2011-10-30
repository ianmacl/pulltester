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
		$this->github = new JGithub(array('username' => $this->config->get('github_username'), 'password' => $this->config->get('github_password')));
		$pulls = $this->github->pulls->getAll($this->config->get('github_project'), $this->config->get('github_repo'), 'open', 0, 100);

		$this->createRepo();

		foreach ($pulls AS $pull) {
			$this->report = '';
			$this->processPull($pull);
		}

		$this->close();
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
			$mergeable = $pullRequest->mergeable;

			if ($mergeable)
			{
				// Step 2: We try and git the repo and perform the build
				$this->build($pullRequest);
				$changed = true;
			}
			else
			{
				// if it was mergeable before and it isn't mergeable anymore, report that
				if ($this->table->mergeable)
				{
					$changed = true;

					$this->report .= 'This pull request could not be tested since the changes could not be cleanly merged.';
				}
			}
		}

		if ($changed)
		{
			if ($mergeable)
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
			$this->table->mergeable = true;
			$this->table->store();
			return false;
		}

		return true;
	}

	protected function createRepo()
	{
		if (!file_exists(PATH_CHECKOUTS . '/pulls'))
		{
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

		exec('git checkout staging');
		exec('git checkout -b pull' . $pull->number);
		exec('git fetch ' . $pull->user->login);

		exec('git merge ' . $pull->user->login . '/' . $pull->head->ref);

		exec('ant clean');
		exec('ant phpunit');
		exec('ant phpunit');

		exec('ant phpcs');

		exec('git checkout staging');
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
		}

		//curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
		//curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($request));
		file_put_contents(PATH_CHECKOUTS . '/pull' . $pullRequest->number . '.txt', $this->report);
		echo $this->report;
	}

	protected function parsePhpUnit()
	{
		if (file_exists(PATH_CHECKOUTS . '/pulls/build/logs/junit.xml'))
		{
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
					$errors[] = preg_replace('#\/[A-Za-z\/]*pulls##', '', $reader->readString());
				}

				if ($reader->name == 'failure')
				{
					$failures[] = preg_replace('#\/[A-Za-z\/]*pulls##', '', $reader->readString());
				}
			}

			$reader->close();

			$this->report .= 'Unit testing complete.  There were ' . $phpUnitTable->failures . ' failures and ' . $phpUnitTable->errors .
						' errors from ' . $phpUnitTable->tests . ' tests and ' . $phpUnitTable->assertions . ' assertions.' . "\n";
		}
		else
		{
			$this->report .= 'Test log missing. Tests failed to execute.' . "\n";
		}
	}

	protected function parsePhpCs()
	{
		if (file_exists(PATH_CHECKOUTS . '/pulls/build/logs/checkstyle.xml'))
		{
			$numWarnings = 0;
			$numErrors = 0;

			$warnings = array();
			$errors = array();

			$phpCsTable = JTable::getInstance('Checkstyle', 'Table');

			$reader = new XMLReader();
			$reader->open(PATH_CHECKOUTS.'/pulls/build/logs/checkstyle.xml');
			while ($reader->read())
			{
				if ($reader->name == 'error')
				{
					if ($reader->getAttribute('severity') == 'warning')
					{
						$numWarnings++;
					}

					if ($reader->getAttribute('severity') == 'error')
					{
						$numErrors++;
					}
				}
			}

			$phpCsTable->errors = $numErrors;
			$phpCsTable->warnings = $numWarnings;

			$phpCsTable->pulls_id = $this->table->id;
			$phpCsTable->store();
			$reader->close();

			$this->report .= 'Checkstyle analysis reported ' . $numWarnings . ' warnings and ' . $numErrors . ' errors.' . "\n";
		}
		else
		{
			$this->report .= 'Checkstyle analysis not found.' . "\n";
		}

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
}

// Execute the application.
JCli::getInstance('PullTester')->execute();
