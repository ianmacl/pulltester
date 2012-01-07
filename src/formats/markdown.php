<?php
class PullTesterFormatMarkdown
{
	private static $detailsUrl = 'http://elkuku.github.com/pulltester/%d';

	public static function format($pullRequest, $testResults)
	{
		$markdown = array();

		if('staging' != $pullRequest->base->ref)
		{
			$markdown[] = '**WARNING! Pull request is not against staging!**';
		}
		elseif($testResults->error)
		{
			//-- Usually this means 'not meargeable'
			$markdown[] = $testResults->error;
		}
		else
		{
			//-- PhpUnit results
			if( ! isset($testResults->phpunit->error))
			{
				$markdown[] = 'Something really fishy happened while executing the unit tests - please FIXME !';
			}
			elseif($testResults->phpunit->error)
			{
				$markdown[] = $testResults->phpunit->error;
			}
			else
			{
				$markdown[] = sprintf('Unit testing complete. There were %1d failures and %2d errors.'
				, count($testResults->phpunit->failures), count($testResults->phpunit->errors));
			}

			if($testResults->phpcs->error)
			{
				$html[] = '<h3 class="img24 img-fail">'.$testResults->phpcs->error.'</h3>';
			}
			else
			{
				//-- PhpCS results
				$markdown[] = sprintf('Checkstyle analysis reported %1d warnings and %2d errors.'
				, count($testResults->phpcs->warnings), count($testResults->phpcs->errors));
			}

			//-- Details link..
			$markdown[] = '';
			$markdown[] = 'See the [Details Page]('.sprintf(self::$detailsUrl, $pullRequest->number).') for more information.';
		}

		return implode("\n", $markdown);
	}
}
