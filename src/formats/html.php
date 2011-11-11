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

		$html[] = '<h1>Results for <a href="https://github.com/joomla/joomla-platform/pull/'.$pullRequest->number.'">'
		.'pull request #'.$pullRequest->number.'</a></h1>';

		if('staging' != $pullRequest->base->ref)
		{
			$html[] = '<h2 class="img24 img-fail">Pull request is not against staging!</h2>';
		}
		elseif($testResults->error)
		{
			//-- Usually this means 'not meargeable'
			$html[] = '<p class="img24 img-fail">'.$testResults->error.'</p>';
		}
		else
		{
			//-- PhpUnit results
			$html[] = '<h2>Unit Tests</h2>';

			if($testResults->phpunit->error)
			{
				$html[] = '<h3 class="img24 img-fail">'.$testResults->phpunit->error.'</h3>';

				foreach ($testResults->phpunit->debugMessages as $message)
				{
					$html[] =  '<pre class="debug">'.$message.'</pre>';
				}
			}
			else
			{
				$c =($testResults->phpunit->failures || $testResults->phpunit->errors) ? 'img-warn' : 'img-success';

				$s = sprintf('Unit testing complete. There were %1d failures and %2d errors.'
				, count($testResults->phpunit->failures), count($testResults->phpunit->errors));

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

			//-- PhpCS results
			$html[] = '<h2>Checkstyle</h2>';

			if($testResults->phpcs->error)
			{
				$html[] = '<h3 class="img24 img-fail">'.$testResults->phpcs->error.'</h3>';

// 				foreach ($testResults->phpcs->debugMessages as $message)
// 				{
// 					$html[] =  '<pre class="debug">'.$message.'</pre>';
// 				}
			}
			else
			{
				$s = sprintf('Checkstyle analysis reported %1d warnings and %2d errors.'
				, count($testResults->phpcs->warnings), count($testResults->phpcs->errors));

				$c =($testResults->phpcs->errors) ? 'img-warn' : 'img-success';

				$html[] = '<p class="img24 '.$c.'">'.$s.'</p>';

				//--- @todo display warnings...

				if($testResults->phpcs->errors)
				{
					$html[] = '<h3>Checkstyle error details</h3>';

					$html[] = '<ul class="phpcs">';

					foreach ($testResults->phpcs->errors as $error) {
						$html[] = '<li><tt>'.$error->file.':'.$error->line.'</tt><br />';
						$html[] = '<em>'.$error->message.'</em></li>';
						;
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

		$html[] = '<div class="footer">Generated on '.date('d-M-Y H:i P T e').'</div>';
		$html[] = '</body>';
		$html[] = '</html>';

		$html[] = '';

		return implode("\n", $html);
	}

	public static function formatIndex($indexData, $totalTime)
	{
		$html = array();
		$statusColors = array(0 => 'ccff99', 1 => 'ffc',2 => 'ff7f7f',3 => 'ff0033');

		$html[] = '<!doctype html>';
		$html[] = '<html>';
		$html[] = '<head><meta http-equiv="content-type" content="text/html; charset=UTF-8">';
		$html[] = '<title>Test results overview</title>';
		$html[] = '<link href="assets/css/style.css" rel="stylesheet" type="text/css" />';
		$html[] = '<link href="favicon.ico" rel="icon" type="image/x-icon" />';
		$html[] = '</head>';

		$html[] = '<body>';
		$html[] = '<h1>Test results overview</h1>';
		$html[] = sprintf('We have %s open pull requests...', '<b>'.count($indexData).'</b>');

		$html[] = '<table>';

		if(isset($indexData[0]))
		{
			$row = '';

			$row .= '<tr>';

			foreach ($indexData[0] as $key => $v)
			{
				$row .= '<th>'.$key.'</th>';
			}

			$row .= '<th>Status</th>';
			$row .= '</tr>';

			$html[] = $row;
		}

		foreach ($indexData as $entry)
		{
			$row = '';

			$row .= '<tr>';

			$mergeable = true;
			$overall = 0;

			foreach ($entry as $key => $value)
			{
				$replace = '%s';

				if( ! $mergeable)
				{
					$replace = '-';
					$overall = 3;
				}
				elseif('' == $value)
				{
					$replace = '<b class="error">? ? ?</b>';
					$overall = 2;
				}
				else
				{
					switch ($key)
					{
						case 'pull_id':
							$replace = '<a href="pulls/%1$s.html">Pull %1$s</a>';
							break;

						case 'CS_warnings':
							$replace =(0 == $value) ? '&radic;' : '<b class="warn">%d</b>';
							// 							$overall = 1;
							break;

						case 'CS_errors':
						case 'Unit_failures':
						case 'Unit_errors':
							$replace =(0 == $value) ? '&radic;' : '<b class="error">%d</b>';
							$overall =(0 == $value) ? $overall : 1;
							break;

						case 'mergeable':
							$replace =($value) ? '&radic;' : '<b class="error">** NO **</b>';
							$mergeable =($value) ? true : false;
							break;
					}//switch
				}

				$row .= '<td>'.sprintf($replace, $value).'</td>';
			}//foreach

			$row .= '<td style="background-color: #'.$statusColors[$overall].'">&nbsp;</td>';

			$row .= '</tr>';

			$html[] = $row;
		}//foreach

		$html[] = '</table>';

		if(file_exists(PATH_OUTPUT.'/index.html'))
		{
			$test = file_get_contents(PATH_OUTPUT.'/index.html');

			preg_match('#<span class="totalruntime">([0-9.]+)#', $test, $matches);

			if($matches)
			{
				$totalTime += $matches[1];
			}
		}

		$html[] = '<div class="footer">'
		. '<small><small>C\'mon, I spent <span class="totalruntime">'.$totalTime.'</span> seconds generating this pages (excluding tests).... </small></small><big>have Fun</big> =;)<br />'
		.'Generated on '.date('d-M-Y H:i P T e')
		.'</div>';

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
		$html[] = '<title>'.$title.'</title>';
		$html[] = '<link href="assets/css/style.css" rel="stylesheet" type="text/css" />';
		$html[] = '<link href="favicon.ico" rel="icon" type="image/x-icon" />';
		$html[] = '</head>';

		return implode("\n", $html);
	}
}
