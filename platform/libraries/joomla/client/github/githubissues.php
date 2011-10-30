<?php
/**
 * @package     Joomla.Platform
 * @subpackage  Client
 *
 * @copyright   Copyright (C) 2005 - 2011 Open Source Matters, Inc. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE
 */

defined('JPATH_PLATFORM') or die;

/**
 * HTTP client class.
 *
 * @package     Joomla.Platform
 * @subpackage  Client
 * @since       11.1
 */
class JGithubIssues
{
	/**
	 * Github Connector
	 *
	 * @var    JGithub
	 * @since  11.3
	 */
	protected $connector = null;

	/**
	 * Constructor.
	 *
	 * @param   array  $options  Array of configuration options for the client.
	 *
	 * @return  void
	 *
	 * @since   11.1
	 */
	public function __construct($connector, $options = array())
	{
		$this->connector = $connector;
	}


	public function addLabels($user, $repo, $id, $labels)
	{
		$url = '/repos/' . $user . '/' .$repo . '/issues/' . (int) $id . '/labels';
		return $this->connector->sendRequest($url, 'post', $labels)->body;
	}

	public function getLabels($user, $repo, $id, $page = 0, $per_page = 0)
	{
		$url = '/repos/' . $user . '/' .$repo . '/issues/' . (int) $id . '/labels';
		return $this->connector->sendRequest($this->paginate($url, $page, $per_page))->body;
	}

	public function getStarred($page = 0, $per_page = 0)
	{
		$url = '/gists/starred';
		return $this->connector->sendRequest($this->paginate($url, $page, $per_page))->body;
	}

	public function get($gist_id)
	{
		return $this->connector->sendRequest('/gists/'.(int)$gist_id)->body;
	}

	public function create($files, $public = false, $description = null)
	{
		$gist = new stdClass;
		$gist->public = $public;
		$gist->files = $files;

		if (!empty($description)) {
			$gist->description = $description;
		}

		return $this->connector->sendRequest('/gists', 'post', $gist)->body;
	}

	public function edit($gist_id, $files, $description = null)
	{
		$gist = new stdClass;
		$gist->files = $files;

		if (!empty($description)) {
			$gist->description = $description;
		}

		return $this->connector->sendRequest('/gists/'.(int)$gist_id, 'patch', $gist)->body;
	}

	public function star($gist_id)
	{
		return $this->connector->sendRequest('/gists/'.(int)$gist_id.'/star', 'put')->body;
	}

	public function unstar($gist_id)
	{
		return $this->connector->sendRequest('/gists/'.(int)$gist_id.'/star', 'delete')->body;
	}

	public function isStarred($gist_id)
	{
		$response = $this->connector->sendRequest('/gists/'.(int)$gist_id.'/star');

		if ($response->code == '204') {
			return true;
		} else {		// the code should be 404
			return false;
		}
	}

	public function createComment($user, $repo, $issue_id, $comment)
	{
		return $this->connector->sendRequest('/repos/' . $user . '/' . $repo . '/issues/' . (int)$issue_id . '/comments', 'post', array('body' => $comment))->body;
	}

	public function editComment($comment_id, $comment)
	{
		return $this->connector->sendRequest('/gists/comments/'.(int)$comment_id, 'patch', array('body' => $comment))->body;
	}

	public function deleteComment($comment_id)
	{
		return $this->connector->sendRequest('/gists/comments/'.(int)$comment_id, 'delete')->body;
	}
}
