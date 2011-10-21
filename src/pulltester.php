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
		foreach ($pulls AS $pull) {
			$this->processPull($pull);
		}

		$this->close();
	}

	protected function processPull($pull)
	{
		$db = JFactory::getDbo();

		$number = $pull->number;

		try {
			$pullRequest = $this->github->pulls->get($this->config->get('github_project'), $this->config->get('github_repo'), $number);
		} catch (Exception $e) {
			echo 'Error Getting Pull Request - JSON Error: '.$e->getMessage."\n";
			return;
		}

		$db->setQuery('SELECT id, head FROM pulls WHERE pull_id = '.$number);
		$head = $db->loadObject();

		if (!is_object($head)) {
			$head = new stdClass;
			$head->head = '';
			$head->id = 0;
		}

		if ($head->head != $pullRequest->head->sha) {
			$url = $pullRequest->head->repo->clone_url;
			chdir(PATH_CHECKOUTS);
			echo $url;
			exec('rm -rf pull'.$number);
			exec('git clone '.$url.' pull'.$number);
			chdir(PATH_CHECKOUTS.'/pull'.$number);
			exec('git checkout '.$pullRequest->head->ref);

			exec('ant phpunit');
			exec('ant phpunit');
			$results = $this->parseTestResults($number);
			$table = JTable::getInstance('Pulls', 'Table');
			$table->load($head->id);
			$table->pull_id = $number;
			$table->head = $pullRequest->head->sha;
			$table->tests = $results->tests;
			$table->assertions = $results->assertions;
			$table->failures = $results->failures;
			$table->errors = $results->errors;
			$table->test_time = $results->time;
			$table->store();

			//$this->publishResults($table, $pullRequest);
		} else {
			echo 'Skipped Build';
		}
	}

	protected function publishResults($table, $pullRequest)
	{
		$project = $this->config->get('github_project');
		$repo = $this->config->get('github_repo');
		$url = 'https://api.github.com/repos/'.$project.'/'.$repo.'/issues/'.$table->pull_id.'/comments';
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_POST, true);
		curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
		curl_setopt($ch, CURLOPT_USERPWD, $this->config->get('github_user').':'.$this->config->get('github_password'));
		$request = new stdClass;
		$request->body = '';

		if ($pullRequest->base->ref != 'staging') {
			$request->body = '**WARNING! Pull request is not against staging!**'."\n\n";
		}

		$request->body .= '# Test Results'."\n";
		$request->body .= 'Total Tests: '.$table->tests."\n";
		$request->body .= 'Assertions: '.$table->assertions."\n";
		$request->body .= 'Failures: '.$table->failures."\n";
		$request->body .= 'Errors: '.$table->errors."\n";
		$request->body .= 'Test Time: '.$table->test_time."\n";

		curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
		curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($request));
		curl_exec($ch);
	}

	protected function parseTestResults($number)
	{
		$reader = new XMLReader();
		$reader->open(PATH_CHECKOUTS.'/pull'.$number.'/build/logs/junit.xml');
		while ($reader->read() && $reader->name !== 'testsuite');
		$results = new stdClass;
		$results->tests = $reader->getAttribute('tests');
		$results->assertions = $reader->getAttribute('assertions');
		$results->failures = $reader->getAttribute('failures');
		$results->errors = $reader->getAttribute('errors');
		$results->time = $reader->getAttribute('time');
		$reader->close();
		return $results;
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
