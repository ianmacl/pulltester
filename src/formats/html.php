<?php
class PullTesterFormatHtml
{
	public static function format($pullRequest, $testResults)
	{
		$html = array();

		$html[] = '<!doctype html>';
		$html[] = '<html>';
		$html[] = '<head><meta http-equiv="content-type" content="text/html; charset=UTF-8">';
		$html[] = '<title>'.$pullRequest->number.' Test results'.'</title>';
		$html[] = '<link href="../assets/css/style.css" rel="stylesheet" type="text/css" />';
		$html[] = '<link href="../favicon.ico" rel="icon" type="image/x-icon" />';
		$html[] = '</head>';

		$html[] = '<body>';

		$html[] = '<a href="../index.html">&lArr; Index &lArr;</a>';

		$html[] = '<h1>Details for <a href="https://github.com/joomla/joomla-platform/pull/'.$pullRequest->number.'">'
		.'Pull Request #'.$pullRequest->number.'</a></h1>';

		$html[] = '<div class="avatar"><img src="'.$pullRequest->user->avatar_url.'" /><br />'.$pullRequest->user->login.'</div>';
		$html[] = '<p class="title">'.htmlspecialchars($pullRequest->title).'</p>';
		$html[] = '<div class="clr"></div>';

		if('staging' != $pullRequest->base->ref)
		{
			$html[] = '<p class="img24 img-fail">Pull request is not against staging !</p>';
		}

		if($testResults->error)
		{
			//-- Usually this means 'not mergeable'
			$html[] = '<p class="img24 img-fail">'.$testResults->error.'</p>';
		}
		else
		{
			//-- PhpUnit results
			$html[] = '<h2>Unit Tests</h2>';

			if($testResults->phpunit->error)
			{
				$html[] = '<h3 class="img24 img-fail">'.$testResults->phpunit->error.'</h3>';
			}
			else
			{
				$c =($testResults->phpunit->failures || $testResults->phpunit->errors)
				? 'img-warn' : 'img-success';

				$s = sprintf('There were %1d failures and %2d errors.'
				, count($testResults->phpunit->failures), count($testResults->phpunit->errors));

				$s .= ' <a href="../logs/'.$pullRequest->number.'junit.xml">Unit Test Log (XML)</a>';

				$html[] = '<p class="img24 '.$c.'">'.$s.'</p>';

				if($testResults->phpunit->errors)
				{
					$html[] = '<h3>Errors</h3>';

					$html[] = '<ol>';

					foreach($testResults->phpunit->errors as $error)
					{
						if( ! $error)//regex produces empty errors :(
						continue;

						$html[] = '<li><pre class="debug">'.htmlentities($error).'</pre></li>';
					}

					$html[] = '</ol>';
				}

				if($testResults->phpunit->failures)
				{
					$html[] = '<h3>Failures</h3>';

					$html[] = '<ol>';

					foreach($testResults->phpunit->failures as $fail)
					{
						if( ! $fail)//regex produces empty errors :(
						continue;

						$html[] = '<li><pre class="debug">'.htmlentities($fail).'</pre></li>';
					}

					$html[] = '</ol>';
				}
			}

			foreach ($testResults->phpunit->debugMessages as $message)
			{
				$html[] =  '<pre class="debug">'.$message.'</pre>';
			}

			//-- PhpCS results
			$html[] = '<h2>Checkstyle</h2>';

			if($testResults->phpcs->error)
			{
				$html[] = '<h3 class="img24 img-fail">'.$testResults->phpcs->error.'</h3>';

				foreach ($testResults->phpcs->debugMessages as $message)
				{
					$html[] =  '<pre class="debug">'.$message.'</pre>';
				}
			}
			else
			{
				$s = sprintf('There were %1d warnings and %2d errors.'
				, count($testResults->phpcs->warnings), count($testResults->phpcs->errors));

				$s .= ' <a href="../logs/'.$pullRequest->number.'checkstyle.xml">Checkstyle Log (XML)</a>';

				$c =($testResults->phpcs->errors) ? 'img-warn' : 'img-success';

				$html[] = '<p class="img24 '.$c.'">'.$s.'</p>';

				//--- @todo display warnings...

				if($testResults->phpcs->errors)
				{
					$html[] = '<h3>Errors</h3>';

					$html[] = '<ul class="phpcs">';

					foreach ($testResults->phpcs->errors as $error)
					{
						$html[] = '<li><tt>'.$error->file.':'.$error->line.'</tt><br />';
						$html[] = '<em>'.$error->message.'</em></li>';
					}

					$html[] = '</ul>';
				}
			}

			foreach ($testResults->phpcs->debugMessages as $message)
			{
				$html[] =  '<pre class="debug">'.$message.'</pre>';
			}
		}

		$html[] = '<a href="../index.html">&lArr; Index &lArr;</a>';

		$html[] = '<div class="footer">Generated on '.date('d-M-Y H:i P T e');

		$html[] = '<div class="myLinx"><em>BTW</em>: If you want to run this tests on your own machine - The source code is <a href="https://github.com/elkuku/pulltester/tree/testing1">available on GitHub</a>'
		.', based on <a href="https://github.com/ianmacl/pulltester">Ian McLennan\'s PullTester</a> =;)</div>';
		$html[] = '</div>';
		$html[] = '</body>';
		$html[] = '</html>';

		$html[] = '';

		return implode("\n", $html);
	}

	/**
	 * Generate an index.html file.
	 *
	 * @param array $indexData
	 * @param float $totalTime
	 *
	 * @return string
	 */
	public static function formatIndex($indexData, $totalTime)
	{
		$html = array();
		$statusColors = array(0 => 'ccff99', 1 => 'ffc',2 => 'ff7f7f',3 => 'ff0033');

		$repoName = 'Joomla! Platform';
		$repoURL = 'https://github.com/joomla/joomla-platform/pulls';

		$html[] = '<!doctype html>';
		$html[] = '<html>';
		$html[] = '<head><meta http-equiv="content-type" content="text/html; charset=UTF-8">';
		$html[] = '<title>Test results overview</title>';
		$html[] = '<link href="assets/css/style.css" rel="stylesheet" type="text/css" />';
		$html[] = '<link href="favicon.ico" rel="icon" type="image/x-icon" />';
		$html[] = '</head>';

		$html[] = '<body>';
		$html[] = '<h1>Test results overview</h1>';
		$html[] = '<h2>'.sprintf('Open Pull Requests for the %s', '<a href="'.$repoURL.'">'.$repoName.' Repository</a>').'</h2>';
		$html[] = sprintf('There are %s open pull requests...', '<b>'.count($indexData).'</b>');

		$html[] = '<table>';

		if(isset($indexData[0]))
		{
			$row = '';

			$row .= '<tr>';

			foreach($indexData[0] as $key => $pumuckl)//note to myself: indexData is an object - don't try array_keys..
			{
				$row .= '<th>'.$key.'</th>';
			}

			$row .= '<th>Status</th>';
			$row .= '</tr>';

			$html[] = $row;
		}

		foreach($indexData as $entry)
		{
			$row = '';

			$row .= '<tr>';

			$mergeable = true;
			$overall = 0;

			foreach($entry as $key => $value)
			{
				$replace = '%s';

				if( ! $mergeable)
				{
					$replace = '-';
					$overall = 3;
				}
				else
				{
					if('mergeable' != $key
					&& '' == $value)
					{
						$replace = '<b class="error">? ? ?</b>';
						$overall = 2;
					}
					else
					{
						switch($key)
						{
							case 'pull_id':
								$replace = '<a href="pulls/%1$d">Pull %1$d</a>';
								break;

							case 'mergeable':
								$replace =($value) ? '&radic;' : '<b class="error">** NO **</b>';
								$mergeable =($value) ? true : false;
								break;

							case 'CS_warnings':
								$replace =(0 == $value) ? '&radic;' : '<b class="warn">%d</b>';
								// 							$overall = 1;
								break;

							case 'CS_errors':
							case 'UT_failures':
							case 'UT_errors':
								$replace =(0 == $value) ? '&radic;' : '<b class="error">%d</b>';
								$overall =(0 == $value) ? $overall : 1;
								break;
						}//switch
					}
				}

				$row .= '<td>'.sprintf($replace, $value).'</td>';
			}//foreach

			$row .= '<td nowrap=nowrap" style="background-color: #'.$statusColors[$overall].'">';

			if($mergeable)
			{
				$row .= '<a href="logs/'.$entry->pull_id.'checkstyle.xml">CS Log</a>';
				$row .= ' &bull; ';
				$row .= '<a href="logs/'.$entry->pull_id.'junit.xml">UT Log</a>';
			}
			else
			{
				$row .= '&nbsp;';
			}

			$row .= '</td>';

			$row .= '</tr>';

			$html[] = $row;
		}//foreach

		$html[] = '</table>';

		if(file_exists(PATH_OUTPUT.'/index.html'))
		{
			//-- This will calculate the total time I spent on this thingy (approx..)
			$test = file_get_contents(PATH_OUTPUT.'/index.html');

			if(preg_match('#<span class="totalruntime">([0-9.]+)#', $test, $matches))
			$totalTime += $matches[1];
		}

		$html[] = '<div class="legal-note">';
		$html[] = '<strong>Please note</strong> that this project is not affiliated with or endorsed by the <a href="http://joomla.org">Joomla! Project</a>. It is not supported or warranted by the <a href="http://joomla.org">Joomla! Project</a> or <a href="http://opensourcematters.org/">Open Source Matters</a>.';
		$html[] = '</div>';

		$html[] = '<div class="footer">'
		.'Generated on '.date('d-M-Y H:i P T e')
		.'<br /><small><small>C\'mon, I spent <span class="totalruntime">'.$totalTime.'</span> seconds generating this pages (excluding tests).... </small></small>'
		.'<big>have Fun</big> =;)<br />'
		.'<em>BTW</em>: If you want to run this tests on your own machine - The source code is <a href="https://github.com/elkuku/pulltester/tree/testing1">available on GitHub</a>,'
		.' based on <a href="https://github.com/ianmacl/pulltester">Ian McLennan\'s PullTester</a>s'
		.'</div>';

		$html[] = '<div class="system-specs">';
		$html[] = shell_exec('pear version').' &bull;';
		$html[] = shell_exec('phpunit --version').' &bull;';
		$html[] = shell_exec('phpcs --version').' &bull;';
		$html[] = shell_exec('/opt/lampp/bin/mysql --version').' &bull;';
		$html[] = shell_exec('psql --version').' &bull;';
		$html[] = '</div>';

		$html[] = '</body>';
		$html[] = '</html>';

		$html[] = '';

		return implode("\n", $html);
	}//function

	protected static function getHead($title)
	{
		$html = array();

		$html[] = '<!doctype html>';
		$html[] = '<html>';
		$html[] = '<head><meta http-equiv="content-type" content="text/html; charset=UTF-8">';
		$html[] = '<title>'.$title.'</title>';
		$html[] = '<link href="assets/css/style.css" rel="stylesheet" type="text/css" />';
		$html[] = '<link href="favicon.ico" rel="icon" type="image/x-icon" />';
		$html[] = '</head>';

		return implode("\n", $html);
	}//function

}//class
