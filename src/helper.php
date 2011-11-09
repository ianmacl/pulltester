<?php
class PullTesterHelper
{
	public static function stripLocalPaths($dirtyPath)
	{
		$clean = $dirtyPath;

		$clean = str_replace(PATH_CHECKOUTS . '/pulls', '...', $clean);
		$clean = str_replace(PATH_CHECKOUTS, '...', $clean);
		$clean = str_replace(JPATH_BASE, '...', $clean);
		$clean = str_replace('/opt/lampp', '...', $clean);

		return $clean;
	}
}
