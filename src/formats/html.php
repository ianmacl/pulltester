<?php
class PullTesterFormatHtml
{
	private static $expectedPhpCsWarnings = 163;

	public static function format($pullRequest, $testResults)
	{
		$html = array();

		$html[] = '<!doctype html>';
		$html[] = '<html>';
		$html[] = '<head><meta http-equiv="content-type" content="text/html; charset=UTF-8">';
		$html[] = '<title>' . $pullRequest->number . ' Test results' . '</title>';
		$html[] = '<link href="assets/css/bootstrap.min.css" rel="stylesheet" type="text/css" />';
		$html[] = '<link href="assets/css/bootstrap-responsive.min.css" rel="stylesheet" type="text/css" />';
		$html[] = '<link href="assets/img/favicon.ico" rel="icon" type="image/x-icon" />';
		$html[] = '</head>';

		$html[] = '<!--                      N O T E                   -->';
		$html[] = '<!-- The contents of this page are auto generated -->';
		$html[] = '<!--   To change it - please modify the PHP code    -->';

		$html[] = '<body>';

		$html[] = '<a href="../index.html" class="btn btn-small"><i class="icon-left-chevron"></i> Back to Overview</a>';

		$html[] = '<h1>Details for <small><a href="https://github.com/joomla/joomla-platform/pull/' . $pullRequest->number . '">'
			. 'Pull Request #' . $pullRequest->number . '</a></small></h1>';

		$html[] = '<div class="avatar"><img class="thumbnail" alt="avatar" src="' . $pullRequest->user->avatar_url . '" /><br />' . $pullRequest->user->login . '</div>';
		$html[] = '<p class="title">' . htmlspecialchars($pullRequest->title) . '</p>';
		$html[] = '<div class="clearfix"></div>';

		if ('staging' != $pullRequest->base->ref)
		{
			$html[] = '<div class="alert alert-error">Pull request is not against staging !</div>';
		}

		if ($testResults->error)
		{
			//-- Usually this means 'not mergeable'
			$html[] = '<div class="alert alert-error">' . $testResults->error . '</div>';
		}
		else
		{
			//-- PhpUnit results
			$html[] = '<div class="page-header"><h3>Unit Tests</h3></div>';

			if ($testResults->phpunit->error)
			{
				$html[] = '<div class="alert alert-error">' . $testResults->phpunit->error . '</div>';
			}
			else
			{
				$c = ($testResults->phpunit->failures || $testResults->phpunit->errors)
					? 'img-warn' : 'img-success';

				$s = sprintf('There were %1d failures and %2d errors.'
					, count($testResults->phpunit->failures), count($testResults->phpunit->errors));

				$s .= ' <a href="../logs/' . $pullRequest->number . 'junit.xml">Unit Test Log (XML)</a>';

				$html[] = '<p class="img24 ' . $c . '">' . $s . '</p>';

				if ($testResults->phpunit->errors)
				{
					$html[] = '<h3>Errors</h3>';

					$html[] = '<ol>';

					foreach ($testResults->phpunit->errors as $error)
					{
						if (!$error) //regex produces empty errors :(
							continue;

						$html[] = '<li><pre class="debug">' . htmlentities($error) . '</pre></li>';
					}

					$html[] = '</ol>';
				}

				if ($testResults->phpunit->failures)
				{
					$html[] = '<h3>Failures</h3>';

					$html[] = '<ol>';

					foreach ($testResults->phpunit->failures as $fail)
					{
						if (!$fail) //regex produces empty errors :(
							continue;

						$html[] = '<li><pre class="debug">' . htmlentities($fail) . '</pre></li>';
					}

					$html[] = '</ol>';
				}
			}

			foreach ($testResults->phpunit->debugMessages as $message)
			{
				$html[] = '<pre class="debug">' . $message . '</pre>';
			}

			//-- PhpCS results
			$html[] = '<div class="page-header"><h3>Checkstyle</h3></div>';

			if ($testResults->phpcs->error)
			{
				$html[] = '<div class="alert alert-error">' . $testResults->phpcs->error . '</div>';

				foreach ($testResults->phpcs->debugMessages as $message)
				{
					$html[] = '<pre class="debug">' . $message . '</pre>';
				}
			}
			else
			{
				$s = sprintf('There were %1d warnings and %2d errors.'
					, count($testResults->phpcs->warnings), count($testResults->phpcs->errors));

				$s .= ' <a href="../logs/' . $pullRequest->number . 'checkstyle.xml">Checkstyle Log (XML)</a>';

				$c = ($testResults->phpcs->errors) ? 'img-warn' : 'img-success';

				$html[] = '<p class="img24 ' . $c . '">' . $s . '</p>';

				if ($testResults->phpcs->warnings)
				{
					$html[] = '<div class="page-header"><h3>Warnings</h3></div>';
					$html[] = sprintf(
						'<p>Currently there are <span class="badge badge-warning">%d</span> expected warnings - If your number differs, please check the log.</p>'
						, self::$expectedPhpCsWarnings
					);

					//--- @todo display warnings...
				}

				if ($testResults->phpcs->errors)
				{
					$html[] = '<div class="page-header"><h3>Errors</h3></div>';
					
					$html[] = '<div class="well">';

					$html[] = '<ul class="phpcs">';

					foreach ($testResults->phpcs->errors as $error)
					{
						$html[] = '<li><span class="code">' . $error->file . ':' . $error->line . '</span><br />';
						$html[] = '<em>' . $error->message . '</em></li>';
					}

					$html[] = '</ul>';
					
					$html[] = '</div>';
				}
			}

			foreach ($testResults->phpcs->debugMessages as $message)
			{
				$html[] = '<pre class="debug">' . $message . '</pre>';
			}
		}

		$html[] = '<a href="../index.html">&lArr; Index &lArr;</a>';

		$html[] = '<div class="footer">Generated on ' . date('d-M-Y H:i P T e');

		$html[] = '<div class="myLinx"><em>BTW</em>: If you want to run this tests on your own machine - The source code is <a href="https://github.com/ianmacl/pulltester/tree/elkuku">available on GitHub</a>'
			. ', based on <a href="https://github.com/ianmacl/pulltester">Ian MacLennan\'s PullTester</a> with signifiant enhancements by <a href="https://github.com/elkuku/pulltester">Nikolai Plath</a>.</div>';
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
		$statusClass = array(0 => 'success', 1 => 'info', 2 => 'warning', 3 => 'important');

		$repoName = 'Joomla! Platform';
		$repoURL = 'https://github.com/joomla/joomla-platform/pulls';

		$html[] = '<!doctype html>';
		$html[] = '<html>';
		$html[] = '<head><meta http-equiv="content-type" content="text/html; charset=UTF-8">';
		$html[] = '<title>Test results overview</title>';
		$html[] = '<link href="assets/css/bootstrap.min.css" rel="stylesheet" type="text/css" />';
		$html[] = '<link href="assets/css/bootstrap-responsive.min.css" rel="stylesheet" type="text/css" />';
		$html[] = '<link href="assets/img/favicon.ico" rel="icon" type="image/x-icon" />';
		$html[] = '</head>';


		$html[] = '<!--                      N O T E                   -->';
		$html[] = '<!-- The contents of this page are auto generated -->';
		$html[] = '<!--   To change it - please modify the PHP code    -->';
		$html[] = '<body>';
		$html[] = '<h1>Test results overview</h1>';
		$html[] = '<h2>' . sprintf('Open Pull Requests for the %s', '<small><a href="' . $repoURL . '">' . $repoName . ' Repository</a></small>') . '</h2>';
		$html[] = sprintf('There are <span class="badge badge-info">%s</span> open pull requests...', '<b>' . count($indexData) . '</b>');

		$html[] = '<table class="table table-striped">';
		

		if (isset($indexData[0]))
		{
			$row = '';
			
			$row .= '<thead>';

			$row .= '<tr>';

			foreach ($indexData[0] as $key => $pumuckl) //note to myself: indexData is an object - don't try array_keys..
			{
				$row .= '<th>' . $key . '</th>';
			}

			$row .= '<th>Status</th>';
			$row .= '</tr>';
			
			$row .= '</thead>';

			$html[] = $row;
		}
		
		$html[] = '<tbody>';

		foreach ($indexData as $entry)
		{
			$row = '';

			$row .= '<tr>';

			$mergeable = true;
			$overall = 0;

			foreach ($entry as $key => $value)
			{
				$replace = '%s';

				if (!$mergeable)
				{
					$replace = '-';
					$overall = 3;
				}
				else
				{
					if ('mergeable' != $key
						&& '' == $value
					)
					{
						$replace = '<b class="error">? ? ?</b>';
						$overall = 2;
					}
					else
					{
						switch ($key)
						{
							case 'pull_id':
								$replace = '<a href="pulls/%1$d.html">Pull %1$d</a>';
								break;

							case 'mergeable':
								$replace = ($value) ? '&radic;' : '<b class="error">** NO **</b>';
								$mergeable = ($value) ? true : false;
								break;

							case 'CS_warnings':
								$replace = (0 == $value) ? '&radic;' : '<b class="warn">%d</b>';
								// 							$overall = 1;
								break;

							case 'CS_errors':
							case 'UT_failures':
							case 'UT_errors':
								$replace = (0 == $value) ? '&radic;' : '<b class="error">%d</b>';
								$overall = (0 == $value) ? $overall : 1;
								break;
						}
					}
				}

				$row .= '<td>' . sprintf($replace, $value) . '</td>';
			}

			$row .= '<td class="nowrap">';

			if ($mergeable)
			{
				$row .= '<a class="label label-' . $statusClass[$overall] . '" href="logs/' . $entry->pull_id . 'checkstyle.xml">CS Log</a>';
				$row .= ' &bull; ';
				$row .= '<a class="label label-' . $statusClass[$overall] . '" href="logs/' . $entry->pull_id . 'junit.xml">UT Log</a>';
			}
			else
			{
				$row .= '<p  class="label label-' . $statusClass[$overall] . '">&nbsp;</p>';
			}

			$row .= '</td>';

			$row .= '</tr>';

			$html[] = $row;
		}
		
		$html[] = '</tbody>';
		
		$html[] = '<tfoot>';
		
		$html[] = '<td colspan="8"></td>';
		
		$html[] = '</tfoot>';

		$html[] = '</table>';

		if (file_exists(PATH_OUTPUT . '/index.html'))
		{
			//-- This will calculate the total time I spent on this thingy (approx..)
			$test = file_get_contents(PATH_OUTPUT . '/index.html');

			if (preg_match('#<span class="totalruntime">([0-9.]+)#', $test, $matches))
				$totalTime += $matches[1];
		}

		$html[] = '<div class="footer">'
			. 'Generated on ' . date('d-M-Y H:i P T e')
			. '<br /><small><small>C\'mon, I spent <span class="totalruntime">' . $totalTime . '</span> seconds generating this pages (excluding tests).... </small></small>'
			. '<span class="havefun">have Fun</span> =;)<br />'
			. '<em>BTW</em>: If you want to run this tests on your own machine - The source code is <a href="https://github.com/ianmacl/pulltester/tree/elkuku">available on GitHub</a>,'
			. ', based on <a href="https://github.com/ianmacl/pulltester">Ian MacLennan\'s PullTester</a> with signifiant enhancements by <a href="https://github.com/elkuku/pulltester">Nikolai Plath</a>.'
			. '</div>';

		$html[] = '<div class="system-specs">';
		$html[] = shell_exec('pear version') . ' &bull;';
		$html[] = shell_exec('phpunit --version') . ' &bull;';
		$html[] = shell_exec('phpcs --version') . ' &bull;';
		$html[] = shell_exec('mysql --version') . ' &bull;';
		$html[] = shell_exec('psql --version') . ' &bull;';
		$html[] = '</div>';

		$html[] = '</body>';
		$html[] = '</html>';

		$html[] = '';

		return implode("\n", $html);
	}

	protected static function getHead($title)
	{
		$html = array();

		$html[] = '<!doctype html>';
		$html[] = '<html>';
		$html[] = '<head><meta http-equiv="content-type" content="text/html; charset=UTF-8">';
		$html[] = '<title>' . $title . '</title>';
		$html[] = '<link href="assets/css/bootstrap.min.css" rel="stylesheet" type="text/css" />';
		$html[] = '<link href="assets/css/bootstrap-responsive.min.css" rel="stylesheet" type="text/css" />';
		$html[] = '<link href="assets/img/favicon.ico" rel="icon" type="image/x-icon" />';
		$html[] = '</head>';

		return implode("\n", $html);
	}

}//class
