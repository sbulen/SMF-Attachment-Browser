<?php
/**
 *	Main logic for the Attachment Browser mod for SMF.
 *
 *	Copyright 2022 Shawn Bulen
 *
 *	The Attachment Browser is free software: you can redistribute it and/or modify
 *	it under the terms of the GNU General Public License as published by
 *	the Free Software Foundation, either version 3 of the License, or
 *	(at your option) any later version.
 *	
 *	This software is distributed in the hope that it will be useful,
 *	but WITHOUT ANY WARRANTY; without even the implied warranty of
 *	MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *	GNU General Public License for more details.
 *
 *	You should have received a copy of the GNU General Public License
 *	along with this software.  If not, see <https://www.gnu.org/licenses/>.
 *
 */

// If we are outside SMF throw an error.
if (!defined('SMF')) {
    die('Hacking attempt...');
}

/**
 * browse_attachments action.
 *
 * Primary action called from the menu for Browse Attachments.
 * Sets subactions & list columns & figures out if which subaction to call.
 *
 * Action: attbr
 *
 * @return null
 *
 */
function browse_attachments()
{
	global $scripturl, $txt, $context, $sourcedir;

	// Get the data model info.
	require_once($sourcedir . '/AttachmentBrowserModel.php');

	// Load template, css, language.
	loadTemplate('AttachmentBrowser');
	loadLanguage('AttachmentBrowser');
	loadCSSFile('attachmentbrowser.css', array('force_current' => false, 'validate' => true, 'minimize' => true, 'order_pos' => 9500));

	$subActions = array(
		'all' => 'browse_all_attachments',
		'filter' => 'attachment_filter',
		'editattach' => 'edit_attachment_tags',
	);

	// Set up the columns...
	$context['columns'] = array(
		'subject' => array(
			'label' => $txt['subject'],
			'class' => 'absubject lefttext',
			'sort' => array(
				'down' => 'm.subject DESC',
				'up' => 'm.subject ASC'
			),
		),
		'poster_name' => array(
			'label' => $txt['poster_name'],
			'class' => 'abposter lefttext',
			'sort' => array(
				'down' => 'mem.real_name DESC',
				'up' => 'mem.real_name ASC'
			),
		),
		'filename' => array(
			'label' => $txt['filename'],
			'class' => 'abfilename lefttext',
			'sort' => array(
				'down' => 'a.filename DESC',
				'up' => 'a.filename ASC'
			),
		),
		'fileext' => array(
			'label' => $txt['fileext'],
			'class' => 'abfileext lefttext',
			'sort' => array(
				'down' => 'a.fileext DESC',
				'up' => 'a.fileext ASC'
			),
		),
		'size' => array(
			'label' => $txt['filesize'],
			'class' => 'absize lefttext',
			'sort' => array(
				'down' => 'a.size DESC',
				'up' => 'a.size ASC'
			),
		),
		'downloads' => array(
			'label' => $txt['downloads'],
			'class' => 'abdownloads lefttext',
			'sort' => array(
				'down' => 'a.downloads DESC',
				'up' => 'a.downloads ASC'
			),
		),
		'tags' => array(
			'label' => $txt['tags'],
			'class' => 'abtags lefttext',
			'sort' => array(
				'down' => 'a.tags DESC',
				'up' => 'a.tags ASC'
			),
		),
	);

	// Build the button array.
	$context['attbr_buttons'] = array(
		'attbr_browse' => array('text' => 'attbr_browse', 'url' => $scripturl . '?action=attbr' . ';sa=all', 'active' => true),
		'attbr_filter' => array('text' => 'attbr_filter', 'url' => $scripturl . '?action=attbr' . ';sa=filter'),
	);

	// Oh yeah, the sub_template - default to the main one
	$context['sub_template'] = 'main';

	// Pick the correct sub-action.
	if (isset($_REQUEST['sa']) && isset($subActions[$_REQUEST['sa']]))
		$context['sub_action'] = $_REQUEST['sa'];
	else
		$context['sub_action'] = 'all';

	$_REQUEST['sa'] = $context['sub_action'];

	// Save off request info for display lists - it will be useful for resuming browsing later
	if (isset($_REQUEST['sa']) && in_array($_REQUEST['sa'], array('all', 'filter')))
		save_request_info();

	call_helper($subActions[$_REQUEST['sa']]);
}

/**
 * browse_all_attachments subaction.
 *
 * Action: attbr
 * Subaction: all
 *
 * Browses attachments, sorts as specified, etc.  User permissions are taken into account.
 *
 * @return null
 *
 */
function browse_all_attachments()
{
	global $txt, $scripturl, $modSettings, $context;

	// Start building the query...
	$query_parameters = array(
		'start' => 0,
		'max' => $modSettings['defaultMaxMembers'],
	);

	// Set defaults for sort and start.
	if (!isset($_REQUEST['sort']) || !isset($context['columns'][$_REQUEST['sort']]))
		$_REQUEST['sort'] = 'filename';

	if (isset($_REQUEST['start']) && is_numeric($_REQUEST['start']))
		$query_parameters['start'] = (int) $_REQUEST['start'];

	// Figure out if a sort was specified...
	// Use filename ASC as a default if not.
	if (isset($_REQUEST['sort']) && isset($context['columns'][$_REQUEST['sort']]))
	{
		if (isset($_REQUEST['desc']))
			$query_parameters['sort'] = $context['columns'][$_REQUEST['sort']]['sort']['down'];
		else
			$query_parameters['sort'] = $context['columns'][$_REQUEST['sort']]['sort']['up'];
	}
	else
		$query_parameters['sort'] = $context['columns']['filename']['sort']['up'];

	// Get the attachments from the database.
	$query_result = query_attachments($query_parameters);
	$context['attachments'] = $query_result['attachments'];

	// Sort out the column information.
	foreach ($context['columns'] as $col => $column_details)
	{
		$context['columns'][$col]['href'] = $scripturl . '?action=attbr;sa=all;sort=' . $col . ';start=' . $_REQUEST['start'];

		if ((!isset($_REQUEST['desc']) && $col == $_REQUEST['sort']) || ($col != $_REQUEST['sort'] && !empty($column_details['default_sort_rev'])))
			$context['columns'][$col]['href'] .= ';desc';

		$context['columns'][$col]['link'] = '<a href="' . $context['columns'][$col]['href'] . '" rel="ugc">' . $context['columns'][$col]['label'] . '</a>';
		$context['columns'][$col]['selected'] = $_REQUEST['sort'] == $col;
	}

	// This helps the little sort icon...
	$context['sort_direction'] = !isset($_REQUEST['desc']) ? 'up' : 'down';

	// Construct the page index.
	$context['page_index'] = constructPageIndex($scripturl . '?action=attbr;sa=all;sort=' . $_REQUEST['sort'] . (isset($_REQUEST['desc']) ? ';desc' : ''), $_REQUEST['start'], $query_result['num_attachments'], $modSettings['defaultMaxMembers']);


	// Linktree...
	$context['start'] = $_REQUEST['start'] + 1;
	$context['end'] = min($_REQUEST['start'] + $modSettings['defaultMaxMembers'], $query_result['num_attachments']);

	$context['page_title'] = sprintf($txt['viewing_attachs'], $context['start'], $context['end']);
	$context['linktree'][] = array(
		'url' => $scripturl . '?action=attbr;sa=all;sort=' . $_REQUEST['sort'] . ';start=' . $_REQUEST['start'],
		'name' => &$context['page_title'],
		'extra_after' => '(' . sprintf($txt['of_total_attachs'], $query_result['num_attachments']) . ')'
	);
}

/**
 * attachment_filter subaction.
 *
 * Action: attbr
 * Subaction: filter
 *
 * Shows the filters for Browse Attachments.  
 * Honors sorts, filters, etc.
 *
 * @return null
 *
 */
function attachment_filter()
{
	global $txt, $scripturl, $context, $modSettings, $smcFunc;

	$context['page_title'] = $txt['attbr_search'];

	// Filter requests have been made, display the results...
	if ((isset($_REQUEST['fileexts']) || isset($_REQUEST['poster']) || isset($_REQUEST['tags'])) && empty($_REQUEST['again']))
	{
		// Start building the query...
		$query_parameters = array(
			'start' => 0,
			'max' => $modSettings['defaultMaxMembers'],
		);

		// Set defaults for sort and start.
		if (!isset($_REQUEST['sort']) || !isset($context['columns'][$_REQUEST['sort']]))
			$_REQUEST['sort'] = 'filename';

		if (isset($_REQUEST['start']) && is_numeric($_REQUEST['start']))
			$query_parameters['start'] = (int) $_REQUEST['start'];

		// Figure out if a sort was specified...
		// Use filename ASC as a default if not.
		if (isset($_REQUEST['sort']) && isset($context['columns'][$_REQUEST['sort']]))
		{
			if (isset($_REQUEST['desc']))
				$query_parameters['sort'] = $context['columns'][$_REQUEST['sort']]['sort']['down'];
			else
				$query_parameters['sort'] = $context['columns'][$_REQUEST['sort']]['sort']['up'];
		}
		else
			$query_parameters['sort'] = $context['columns']['filename']['sort']['up'];

		// Handle search parameters.  Also build search string used in column headers,
		// page numbers & search again button.
		$search_string = '';

		// Searching for extensions?
		if (!empty($_REQUEST['fileexts']))
		{
			// If array, e.g., from post, use array
			if (is_array($_REQUEST['fileexts']))
			{
				$query_parameters['fileexts'] = array_keys($_REQUEST['fileexts']);
				$search_string .= ';fileexts=' . implode(',', array_keys($_REQUEST['fileexts']));
			}
			// If in URL, it will already be in imploded string form
			else
			{
				$query_parameters['fileexts'] = explode(',', $_REQUEST['fileexts']);
				$search_string .= ';fileexts=' . $_REQUEST['fileexts'];
			}
		}

		// Searching for poster?
		if (!empty($_REQUEST['poster']))
		{
			$query_parameters['poster'] = $_REQUEST['poster'];
			$search_string .= ';poster=' . $_REQUEST['poster'];
		}

		// Searching for tags?
		if (!empty($_REQUEST['tags']))
		{
			// If array, e.g., from post, use array
			if (is_array($_REQUEST['tags']))
			{
				$query_parameters['tags'] = array_keys($_REQUEST['tags']);
				$search_string .= ';tags=' . implode(',', array_keys($_REQUEST['tags']));
			}
			// If in URL, it will already be in imploded string form
			else
			{
				$query_parameters['tags'] = explode(',', $_REQUEST['tags']);
				$search_string .= ';tags=' . $_REQUEST['tags'];
			}
		}

		// For the search again button...
		$context['search_again_string'] = $search_string;

		// Build the column link / sort information.
		foreach ($context['columns'] as $col => $column_details)
		{
			$context['columns'][$col]['href'] = $scripturl . '?action=attbr;sa=filter;start=' . (int) $_REQUEST['start'] . ';sort=' . $col;

			if ((!isset($_REQUEST['desc']) && $col == $_REQUEST['sort']) || ($col != $_REQUEST['sort'] && !empty($column_details['default_sort_rev'])))
				$context['columns'][$col]['href'] .= ';desc';

			$context['columns'][$col]['href'] .= $search_string;

			$context['columns'][$col]['link'] = '<a href="' . $context['columns'][$col]['href'] . '" rel="ugc">' . $context['columns'][$col]['label'] . '</a>';
			$context['columns'][$col]['selected'] = $_REQUEST['sort'] == $col;
		}

		// This helps the little sort icon...
		$context['sort_direction'] = !isset($_REQUEST['desc']) ? 'up' : 'down';

		// Get the attachments from the database.
		$query_result = query_attachments($query_parameters);
		$context['attachments'] = $query_result['attachments'];

		// Construct the page index.
		$context['page_index'] = constructPageIndex($scripturl . '?action=attbr;sa=filter;sort=' . $_REQUEST['sort'] . (isset($_REQUEST['desc']) ? ';desc' : '') . $search_string, $_REQUEST['start'], $query_result['num_attachments'], $modSettings['defaultMaxMembers']);

		$context['sub_template'] = 'main';
	}
	else
	{
		$context['sub_template'] = 'filter';

		// Get the filter prompt info for display...
		$context['fileexts'] = get_unique_fileexts();
		$context['tag_info'] = get_tags_with_aliases();

		// Help pre-populate form if we're doing a "search again"
		if (!empty($_REQUEST['fileexts']))
			$context['old_search']['fileexts'] = explode(',', $_REQUEST['fileexts']);
		if (!empty($_REQUEST['poster']))
			$context['old_search']['poster'] = $_REQUEST['poster'];
		if (!empty($_REQUEST['tags']))
			$context['old_search']['tags'] = explode(',', $_REQUEST['tags']);

		// Auto complete for poster field...
		loadJavaScriptFile('suggest.js', array('defer' => false, 'minimize' => true), 'smf_suggest');
	}

	// Do the linktree...
	$context['linktree'][] = array(
		'url' => $scripturl . '?action=attbr;sa=filter',
		'name' => &$context['page_title']
	);

	// Highlight the correct button, too!
	unset($context['attbr_buttons']['attbr_browse']['active']);
	$context['attbr_buttons']['attbr_filter']['active'] = true;
}

/**
 * edit_attachment_tags subaction.
 *
 * Action: attbr
 * Subaction: editattach
 *
 * Shows the tags for an attachment & allows updates.  
 *
 * @return null
 *
 */
function edit_attachment_tags()
{
	global $txt, $scripturl, $context, $user_info;

	$context['page_title'] = $txt['attbr_editattachtags'];
	$context['sub_template'] = 'editattach';

	// Confirm they're OK being here...
	if (!empty($_POST))
		checkSession('post');

	// Some basic checks...  Valid attachment, valid user...
	// Only admins & the actual poster of the message for this attachment can modify.
	// Otherwise nope the heck outta here...
	if (empty($_REQUEST['attach']) || !is_numeric($_REQUEST['attach']))
		redirectexit();
	$context['attachment'] = get_attachment_info($_REQUEST['attach']);
	if (empty($context['attachment']))
		redirectexit();
	if (($context['attachment']['id_member'] != $user_info['id']) && empty($user_info['is_admin']))
		redirectexit();

	// Submit button pressed - update the tags on the record
	if (!empty($_REQUEST['submit']))
	{
		validateToken('attbr_edit_tags', 'post');
		// Clear out the old ones before updating
		if (empty($_REQUEST['tags']))
			$_REQUEST['tags'] = array();
		$success = add_attachment_tags($_REQUEST['attach'], array_keys($_REQUEST['tags']), true);
		if (!$success)
			fatal_lang_error('attbr_too_many', false);
	}

	// Save off a return URL so you can return back to exactly where you were if you have come from sa=all or sa=filter
	$context['return_info'] = return_url();

	// Get the filter prompt info...
	$context['fileexts'] = get_unique_fileexts();
	$context['tag_info'] = get_tags_with_aliases();

	// Reload attach info, it may have changed
	$context['attachment'] = get_attachment_info($_REQUEST['attach']);
	// For our purposes of this form, we want the tags as an array
	$context['attachment']['tags'] = explode(', ', $context['attachment']['tags']);

	// Do the linktree...
	$context['linktree'][] = array(
		'url' => $scripturl . '?action=attbr;sa=filter',
		'name' => &$context['page_title']
	);
	// Do not highlight these buttons, we're not there...
	unset($context['attbr_buttons']['attbr_browse']['active']);
	unset($context['attbr_buttons']['attbr_filter']['active']);

	// Token for tag edits
	createToken('attbr_edit_tags');
}

/**
 * attachment_tags - action.
 *
 * Primary action called from the admin menu for managing the attachment tags.
 * Sets subactions & list columns & figures out if which subaction to call.
 *
 * Action: attbrtag
 *
 * @return null
 *
 */
function attachment_tags()
{
	global $txt, $context;

	// You have to be able to moderate the forum to do this.
	isAllowedTo('manage_attachments');

	// Setup the template stuff we'll probably need.
	loadTemplate('AttachmentBrowserMaint');

	// Sub actions...
	$subActions = array(
		'tags' => 'manage_attachment_tags',
		'autotag' => 'auto_tag_attachments',
	);

	// This uses admin tabs
	$context[$context['admin_menu_name']]['tab_data'] = array(
		'title' => $txt['attbr_tags'],
		'description' => $txt['attbr_desc'],
	);

	// Pick the correct sub-action.
	if (isset($_REQUEST['sa']) && isset($subActions[$_REQUEST['sa']]))
		$context['sub_action'] = $_REQUEST['sa'];
	else
		$context['sub_action'] = 'tags';

	$_REQUEST['sa'] = $context['sub_action'];

	// Set the page title
	$context['page_title'] = $txt['attbr_tagmaint'];

	// Finally fall through to what we are doing.
	call_helper($subActions[$context['sub_action']]);
}

/**
 * manage_attachment_tags subaction.
 *
 * Action: attbrtag
 * Subaction: tagmaint
 *
 * @return null
 *
 */
function manage_attachment_tags()
{
	global $txt, $context, $sourcedir, $smcFunc;

	// Make sure the right person is putzing...
	if (!empty($_POST))
		checkSession('post');

	require_once($sourcedir . '/AttachmentBrowserModel.php');

	// Are we adding/modifying one?
	if (!empty($_POST['cat_name']) && !empty($_POST['tag_name']) && !empty($_POST['add']))
	{
		validateToken('attbr_tag_maint', 'post');
		if (empty($_POST['alias_name']))
			$_POST['alias_name'] = '';

		$tag = validate_tag($_POST['tag_name']);
		if (($tag === false) || (mb_strlen($tag) > 25))
			fatal_lang_error('attbr_bad_tag', false);

		$aliases = validate_tag($_POST['alias_name'], true);
		if (($aliases === false) || (mb_strlen($aliases) > 50))
			fatal_lang_error('attbr_bad_aliases', false);

		$cat = validate_tag($_POST['cat_name']);
		if (($cat === false) || (mb_strlen($cat) > 25))
			fatal_lang_error('attbr_bad_cat', false);

		add_tag($tag, $cat, $aliases);
	}
	// Are we deleting some?
	elseif (!empty($_POST['tag']) && !empty($_POST['delete']))
	{
		validateToken('attbr_tag_maint', 'post');
		$delete_tags = array_keys($_POST['tag']);
		delete_tag($delete_tags);
	}

	// Get all the tags for display
	$context['tag_info'] = get_tags_with_aliases();

	$context['sub_template'] = 'tags';

	// Token for tag maint
	createToken('attbr_tag_maint');
}

/**
 * validate_tag.  Make sure there are no unacceptable characters in there.
 * Look at the content & return true or false...
 * The idea is to be as open as possible, but refuse certain characters that:
 * - may be confused with URL & HTML syntax 
 * - 4-byte UTF8 chars
 *
 * Action: NA - helper function
 * 
 * @param string - tag(s) to evaluate
 * @param bool - whether this might be a comma separated list (to check the aliases string)
 *
 * @return mixed - cleansed input, or, false if something bad found
 *
 */
function validate_tag($passed, $commalist = false)
{
	global $smcFunc;

	// Exclude []#?&;,'"<>=+ and 4-byte utf8 chars
	static $funky_chars = '~[\[\]\#\?\&;,\'\"<>=+\x{10000}-\x{10FFFF}]~u';

	if ($commalist)
		$tags = explode(',', $passed);
	else
		$tags = array($passed);

	// Trim input & check for funky chars
	$cleaned = array();
	foreach ($tags AS $tag)
	{
		$cleaned[] = $smcFunc['htmltrim']($tag);
		if (preg_match($funky_chars, $tag))
			return false;
	}

	// Return as string
	if ($commalist)
		$output = implode(',', $cleaned);
	else
		$output = $cleaned[0];

	return $output;
}
/**
 * auto_tag_attachments - Run the auto-tag function for tagging all attachments.
 *
 * Action: attbrtag
 * Subaction: autotag
 *
 * @return null
 *
 */
function auto_tag_attachments()
{
	global $context, $sourcedir, $modSettings, $txt;

	// How many to do each time...
	static $limit = 5000;

	// Calculate percentage...
	require_once($sourcedir . '/AttachmentBrowserModel.php');
	$total_attachments = get_admin_attachment_count();

	if (empty($modSettings['attbr_tagall_count']))
		$tagall_count = 0;
	else
		$tagall_count = (int) $modSettings['attbr_tagall_count'];

	if ($total_attachments == 0)
		$context['attbr_tagall_percentage'] = 100;
	else
		$context['attbr_tagall_percentage'] = (int) (($tagall_count * 100) / $total_attachments);

	// Figure out the status...
	if ($tagall_count >= $total_attachments)
	{
		$done = true;
		$tagall_count = $total_attachments;
		$context['attbr_tagall_percentage'] = 100;
		delete_setting('attbr_tagall_count');
		$context['attbr_autotag_status'] = $txt['attbr_complete'];
	}
	else
	{
		$done = false;
		if ($tagall_count > 0)
			$context['attbr_autotag_status'] = $txt['attbr_resume'];
		else
			$context['attbr_autotag_status'] = $txt['attbr_start'];
	}

	// Set this before we might return...
	$context['sub_template'] = 'autotag';

	// Only continue if they hit the button & there is more to do...
	if (!empty($_POST) && !$done)
	{
		// Make sure the right person is putzing...
		checkSession('post');
		validateToken('attbr_autotag', 'post');

		// Do a chunk of 'em....
		$query_parameters = array(
			'sort' => 'a.id_attach ASC',
			'start' => $tagall_count,
			'max' => $limit,
		);

		$atts = query_attachments($query_parameters);
		foreach ($atts['attachments'] AS $attachment)
			auto_tag_message($attachment['id_msg'], array($attachment['id_attach']));

		// Save off where we are...  Wait a bit...  Then do it again!
		$update = array('attbr_tagall_count' => $tagall_count + $limit);
		require_once($sourcedir . '/Load.php');
		updateSettings($update);
	}

	// Token for tag maint
	createToken('attbr_autotag');
}

/**
 * auto_tag_message - Auto tag one message
 *
 * Inspect the message & subject for occurances of the tag or its aliases.
 * Also look at the names & descriptions of the boards all the way up the
 * board hierarchy & the category as well.
 *
 * Action: NA - helper function
 *
 * @param int - msg ID
 * @param array - attach IDs
 *
 * @return array tags
 *
 */
function auto_tag_message($msg_id, $attach_ids)
{
	global $sourcedir, $smcFunc;

	// Build an array of regexes for searches (if not yet built...)
	// Uses regex to allow for case insensitive searches and also to utilize word boundaries.
	static $regexes = null;

	require_once($sourcedir . '/AttachmentBrowserModel.php');

	if ($regexes === null)
	{
		$attach_cats = get_tags_with_aliases();

		// Regexes for plain string compares - boards & categories
		$regexes['plain'] = array();
		foreach ($attach_cats AS $all_tags)
		{
			foreach ($all_tags AS $one_tag => $aliases)
			{
				$preg = '/(?:\W|^)(?:' . preg_quote($one_tag, '/');
				if (!empty($aliases))
				{
					$vals = explode(',', $aliases);
					foreach ($vals AS $val)
					{
						$preg .= '|' . preg_quote($val, '/');
					}
				}
				$preg .= ')(?:\W|$)/iu';
				$regexes['plain'][$one_tag] = $preg;
			}
		}
		// Special regexes for message subject - htmlspecialchars
		$regexes['subj'] = array();
		foreach ($attach_cats AS $all_tags)
		{
			foreach ($all_tags AS $one_tag => $aliases)
			{
				$preg = '/(?:\W|^)(?:' . preg_quote($smcFunc['htmlspecialchars']($one_tag), '/');
				if (!empty($aliases))
				{
					$vals = explode(',', $aliases);
					foreach ($vals AS $val)
					{
						$preg .= '|' . preg_quote($smcFunc['htmlspecialchars']($val), '/');
					}
				}
				$preg .= ')(?:\W|$)/iu';
				$regexes['subj'][$one_tag] = $preg;
			}
		}
		// Special regexes for message body - htmlspecialchars w/ENT_QUOTES
		$regexes['body'] = array();
		foreach ($attach_cats AS $all_tags)
		{
			foreach ($all_tags AS $one_tag => $aliases)
			{
				$preg = '/(?:\W|^)(?:' . preg_quote($smcFunc['htmlspecialchars']($one_tag, ENT_QUOTES), '/');
				if (!empty($aliases))
				{
					$vals = explode(',', $aliases);
					foreach ($vals AS $val)
					{
						$preg .= '|' . preg_quote($smcFunc['htmlspecialchars']($val, ENT_QUOTES), '/');
					}
				}
				$preg .= ')(?:\W|$)/iu';
				$regexes['body'][$one_tag] = $preg;
			}
		}
	}

	// Inspect the message subject & body itself
	$tags = array();

	$msg_info = get_message_text_fields($msg_id);

	foreach ($regexes['subj'] AS $tag => $regex)
		if (preg_match($regex, $msg_info['subject']))
			$tags[] = $tag;

	foreach ($regexes['body'] AS $tag => $regex)
		if (preg_match($regex, $msg_info['body']))
			$tags[] = $tag;

	// Now check through board & parents, names & descriptions.
	// Keep climbing hierarchy until there aren't any parent boards.
	$board = $msg_info['id_board'];
	$loop_protect = 0;
	while (!empty($board))
	{
		$board_info = get_board_text_fields($board);

		foreach ($regexes['plain'] AS $tag => $regex)
			if (preg_match($regex, $board_info['name']) || preg_match($regex, $board_info['description']))
				$tags[] = $tag;

		$board = $board_info['id_parent'];

		if (++$loop_protect > 20)
			break;
	}

	// Now check the category of the topmost board.
	if (!empty($board_info['id_cat']))
	{
		$cat_info = get_cat_text_fields($board_info['id_cat']);

		foreach ($regexes['plain'] AS $tag => $regex)
			if (preg_match($regex, $cat_info['name']) || preg_match($regex, $cat_info['description']))
				$tags[] = $tag;
	}

	// Finally, apply the tags!
	if (!empty($tags))
	{
		$tags = array_unique($tags);
		foreach ($attach_ids AS $attach_id)
			add_attachment_tags($attach_id, $tags);
	}
}

/**
 * save_request_info - Save off request info in case needed for return later.
 *
 * Moves some content from $_REQUEST to $_SESSION.  This enables us to recreate
 * exact parameters used when viewing, whether we got here via POST, GET, etc.
 *
 * Action: NA - helper function
 *
 * @return null
 *
 */
function save_request_info()
{
	unset($_SESSION['old_request']);

	$_SESSION['old_request']['action'] = $_REQUEST['action'];

	if (!empty($_REQUEST['sa']))
		$_SESSION['old_request']['sa'] = $_REQUEST['sa'];
	if (isset($_REQUEST['start']))
		$_SESSION['old_request']['start'] = $_REQUEST['start'];
	if (!empty($_REQUEST['sort']))
		$_SESSION['old_request']['sort'] = $_REQUEST['sort'];
	if (isset($_REQUEST['desc']))
		$_SESSION['old_request']['desc'] = $_REQUEST['desc'];
	if (!empty($_REQUEST['poster']))
		$_SESSION['old_request']['poster'] = $_REQUEST['poster'];

	if (!empty($_REQUEST['fileexts']))
	{
		if (is_array($_REQUEST['fileexts']))
			$_SESSION['old_request']['fileexts'] = implode(',', array_keys($_REQUEST['fileexts']));
		else
			$_SESSION['old_request']['fileexts'] = $_REQUEST['fileexts'];
	}

	if (!empty($_REQUEST['tags']))
	{
		if (is_array($_REQUEST['tags']))
			$_SESSION['old_request']['tags'] = implode(',', array_keys($_REQUEST['tags']));
		else
			$_SESSION['old_request']['tags'] = $_REQUEST['tags'];
	}
}

/**
 * return_url - Build a URL for when you are returning from editing a specific attachment.
 * Trying here to put you back exactly where you were - same page, sort order, filter, etc.
 *
 * Action: NA - helper function
 *
 * @return string URL
 *
 */
function return_url()
{
	global $scripturl;

	// If not set yet, default to main...
	// (Just logged on, new session, hasn't navigated around yet at all...)
	if (empty($_SESSION['old_request']))
		return $scripturl . '?action=attbr;sa=all';

	$url = $scripturl . '?action=' . $_SESSION['old_request']['action'];

	if (!empty($_SESSION['old_request']['sa']))
		$url .= ';sa=' . $_SESSION['old_request']['sa'];
	if (isset($_SESSION['old_request']['start']))
		$url .= ';start=' . $_SESSION['old_request']['start'];
	if (!empty($_SESSION['old_request']['sort']))
		$url .= ';sort=' . $_SESSION['old_request']['sort'];
	if (isset($_SESSION['old_request']['desc']))
		$url .= ';desc';
	if (!empty($_SESSION['old_request']['poster']))
		$url .= ';poster=' . $_SESSION['old_request']['poster'];
	if (!empty($_SESSION['old_request']['fileexts']))
		$url .= ';fileexts=' . $_SESSION['old_request']['fileexts'];
	if (!empty($_SESSION['old_request']['tags']))
		$url .= ';tags=' . $_SESSION['old_request']['tags'];

	return $url;
}
