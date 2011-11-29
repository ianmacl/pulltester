<?php
class JConfig
{
	public $github_project = 'joomla';
	public $github_repo = 'joomla-platform';

	/** Path to a web space to publish the results to */
	public $targetPath = '';

	/** Path to the Joomla! Platform Coding Standards (override) */
	public $codeStandardsPath = '/home/elkuku/libs/joomla/build/phpcs/Joomla';

	public $dbtype		= 'mysql';
	public $host		= '127.0.0.1';
	public $user		= 'root';
	public $password	= '';
	public $db			= 'pulltester';
	public $dbprefix	= '';
	public $ftp_host	= '127.0.0.1';
	public $ftp_port	= '21';
	public $ftp_user	= '';
	public $ftp_pass	= '';
	public $ftp_root	= '';
	public $ftp_enable	= 0;
	public $tmp_path	= '/home/elkuku/eclipsespace/indigogit3/joomla-platform/tmp';
	public $log_path	= '/home/elkuku/eclipsespace/indigogit3/joomla-platform/logs';
	public $mailer		= 'mail';
	public $mailfrom	= 'admin@localhost.home';
	public $fromname	= '';
	public $sendmail	= '/usr/sbin/sendmail';
	public $smtpauth	= '0';
	public $smtpsecure = 'none';
	public $smtpport	= '25';
	public $smtpuser	= '';
	public $smtppass	= '';
	public $smtphost	= 'localhost';
	public $debug		= 0;
	public $caching		= '0';
	public $cachetime	= '900';
	public $language	= 'en-GB';
	public $secret		= null;
	public $editor		= 'none';
	public $offset		= 0;
	public $lifetime	= 15;
}