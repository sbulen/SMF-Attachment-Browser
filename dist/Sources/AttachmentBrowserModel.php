<?php
/**
 *	DB interaction for the Attachment Browser mod for SMF.
 *
 *	Copyright 2022-2024 Shawn Bulen
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
 * get_tags - returns an array of tags as keys, with the categories as values.
 *
 * @return array
 *
 */
function get_tags()
{
	global $smcFunc, $cache_enable;

	if (($attach_tags = cache_get_data('attbr_tags', 920)) == null)
	{
		$request = $smcFunc['db_query']('', '
			SELECT attach_cat, attach_tag
			FROM {db_prefix}attachment_tags
			ORDER BY attach_cat, attach_tag',
			array()
		);

		$attach_tags = array();
		while ($row = $smcFunc['db_fetch_assoc']($request))
			$attach_tags[$row['attach_tag']] = $row['attach_cat'];
		$smcFunc['db_free_result']($request);

		if (!empty($cache_enable))
			cache_put_data('attbr_tags', $attach_tags, 920);
	}
	return $attach_tags;
}

/**
 * get_tags_with_aliases - returns an array of cats, tags & aliases.
 * Used mainly during the auto-tagging processes.
 *
 * @return array
 *
 */
function get_tags_with_aliases()
{
	global $smcFunc, $cache_enable;

	if (($attach_tags = cache_get_data('attbr_aliases', 860)) == null)
	{
		$request = $smcFunc['db_query']('', '
			SELECT attach_cat, attach_tag, aliases
			FROM {db_prefix}attachment_tags
			ORDER BY attach_cat, attach_tag',
			array()
		);

		$attach_tags = array();
		while ($row = $smcFunc['db_fetch_assoc']($request))
			$attach_tags[$row['attach_cat']][$row['attach_tag']] = $row['aliases'];
		$smcFunc['db_free_result']($request);

		if (!empty($cache_enable))
			cache_put_data('attbr_aliases', $attach_tags, 860);
	}

	return $attach_tags;
}

/**
 * add_tag - adds one single tag
 *
 * @param string tag
 * @param string att_cat
 * @param string aliases
 *
 * @return null
 *
 */
function add_tag($attach_tag, $attach_cat, $aliases = '')
{
	global $smcFunc, $cache_enable;

	$request = $smcFunc['db_insert']('replace',
		'{db_prefix}attachment_tags',
		array('attach_tag' => 'string', 'attach_cat' => 'string', 'aliases' => 'string'),
		array($attach_tag, $attach_cat, $aliases),
		array('attach_tag')
	);

	// Kill the cache...
	if (!empty($cache_enable))
	{
		cache_put_data('attbr_aliases', null);
		cache_put_data('attbr_tags', null);
	}
}

/**
 * delete_tag - deletes one or more tags from attachment_tags.
 *
 * @param array tag
 *
 * @return null
 *
 */
function delete_tag($tag)
{
	global $smcFunc, $cache_enable;

	$request = $smcFunc['db_query']('', '
		DELETE FROM {db_prefix}attachment_tags
		WHERE attach_tag IN ({array_string:attach_tag})',
		array(
			'attach_tag' => $tag
		)
	);

	// Kill the cache...
	if (!empty($cache_enable))
	{
		cache_put_data('attbr_aliases', null);
		cache_put_data('attbr_tags', null);
	}
}

/**
 * add_attachment_tags - adds to the list of tags on the attachment record.
 * If requested, will clear first, substituting new list for the old one.
 * When clearing first, errors are not logged (but sucess flag is returned), intended for online edits.
 *
 * @param int $id_attach
 * @param array $tags
 * @param bool $clear_first
 *
 * @return bool $success - will return false if too many tags selected
 *
 */
function add_attachment_tags($id_attach, $new_tags, $clear_first = false)
{
	global $smcFunc, $txt;
	static $all_tags = null;

	if (empty($id_attach))
		return;

	// Get all the tags
	if ($all_tags === null)
	{
		$all_tags = get_tags();
		$all_tags = array_keys($all_tags);
		sort($all_tags);
	}

	if ($clear_first)
		$old_tags = array();
	else
	{
		$att_info = get_attachment_info($id_attach);
		if (!empty($att_info['tags']))
			$old_tags = explode(',', $att_info['tags']);
		else
			$old_tags = array();
	}

	// Merge old & new, trim all, dedupe, sort...
	$new_tags = array_merge($old_tags, $new_tags);
	$new_tags = array_map('trim', $new_tags);
	$new_tags = array_unique($new_tags);
	sort($new_tags);

	// Only use active, current tags
	$new_tags = array_intersect($new_tags, $all_tags);

	$new_str = implode(',', $new_tags);

	// Check vs field length...
	// If exceeded, log an error & see if we can clean it up a bit...
	if (mb_strlen($new_str) > 255)
	{
		$success = false;
		if (!$clear_first)
			log_error(sprintf($txt['attbr_too_long'], $id_attach));

		$new_str = mb_substr($new_str, 0, 255);
		$pos = mb_strrpos($new_str, ',');
		if ($pos === false)
			$new_str = '';
		else
			$new_str = mb_substr($new_str, 0, $pos);
	}
	else
		$success = true;

	$request = $smcFunc['db_query']('', '
		UPDATE {db_prefix}attachments
			SET tags = {string:new_tags}
		WHERE id_attach = {int:id_attach}
			AND attachment_type = {int:attachment_type}',
		array(
			'id_attach' => $id_attach,
			'new_tags' => $new_str,
			'attachment_type' => 0,
		)
	);
	return $success;
}

/**
 * get_attachment_info - get attachment info for a specific attachment or an array of attachments.
 *
 * @param int|array $id_attach
 *
 * @return array $info
 *
 */
function get_attachment_info($id_attach)
{
	global $smcFunc, $user_info, $txt, $scripturl, $modSettings;

	$info = array();

	if (empty($id_attach) || (!is_string($id_attach) && !is_numeric($id_attach) && !is_array($id_attach)))
		return $info;

	if (is_string($id_attach) || is_numeric($id_attach))
	{
		$id_attach = array((int) $id_attach);
		$single = true;
	}
	else
		$single = false;

	$request = $smcFunc['db_query']('', '
		SELECT a.id_attach, a.id_msg, a.filename, a.fileext, a.size, a.downloads, a.tags, m.subject, m.id_member, a.id_thumb, a.height, a.width, COALESCE(mem.real_name,\'' . $txt['guest_title'] . '\') AS real_name, CASE WHEN m.modified_time > 0 THEN m.modified_time ELSE m.poster_time END AS post_time
			FROM {db_prefix}attachments AS a
			INNER JOIN {db_prefix}messages AS m ON (a.id_msg = m.id_msg)
			LEFT JOIN {db_prefix}members AS mem ON (m.id_member = mem.id_member)
		WHERE a.id_attach IN ({array_int:id_attach})
			AND a.approved =  {int:approved}
			AND m.approved =  {int:approved}
			AND a.attachment_type = {int:attachment_type}',
		array(
			'id_attach' => $id_attach,
			'approved' => 1,
			'attachment_type' => 0,
		)
	);
	while($row = $smcFunc['db_fetch_assoc']($request))
	{
		$info[$row['id_attach']] = $row;

		// Do some one-time universal tweaks here.  Needed always, so now's the time...
		if (empty($info[$row['id_attach']]['tags']))
		    $info[$row['id_attach']]['tags'] = '';
		else
		    $info[$row['id_attach']]['tags'] = str_replace(',', ', ', $info[$row['id_attach']]['tags']);
		if ((!empty($user_info['id']) && ($info[$row['id_attach']]['id_member'] == $user_info['id'])) || !empty($user_info['is_admin']))
			$info[$row['id_attach']]['editable'] = true;
		$info[$row['id_attach']]['size'] = format_bkmg($info[$row['id_attach']]['size']);
		$info[$row['id_attach']]['downloads'] = comma_format($info[$row['id_attach']]['downloads']);
		$info[$row['id_attach']]['post_time'] =  smf_strftime('%Y-%m-%d', $info[$row['id_attach']]['post_time']);
		$info[$row['id_attach']]['href'] = $scripturl . '?action=dlattach;attach=' . $row['id_attach'];

		$info[$row['id_attach']]['is_image'] = !empty($info[$row['id_attach']]['width']) && !empty($info[$row['id_attach']]['height']);

		// Handle thumbnails...
		if (!empty($info[$row['id_attach']]['id_thumb']))
			$info[$row['id_attach']]['thumbnail'] = array(
				'id' => $info[$row['id_attach']]['id_thumb'],
				'href' => $scripturl . '?action=dlattach;attach=' . $info[$row['id_attach']]['id_thumb'] . ';image',
			);
		$info[$row['id_attach']]['thumbnail']['has_thumb'] = !empty($info[$row['id_attach']]['id_thumb']);

		// If the image is too large to show inline, make it a popup.
		if (((!empty($modSettings['max_image_width']) && $info[$row['id_attach']]['width'] > $modSettings['max_image_width']) || (!empty($modSettings['max_image_height']) && $info[$row['id_attach']]['height'] > $modSettings['max_image_height'])))
			$info[$row['id_attach']]['thumbnail']['javascript'] = 'return reqWin(\'' . $info[$row['id_attach']]['href'] . ';image\', ' . ($info[$row['id_attach']]['width'] + 20) . ', ' . ($info[$row['id_attach']]['height'] + 20) . ', true);';
		else
			$info[$row['id_attach']]['thumbnail']['javascript'] = 'return expandThumb(' . $row['id_attach'] . ');';
	}

	// Gotta check it exists...  Thumbnails can get caught late here...
	if ($single)
		if (!empty($info[$id_attach[0]]))
			$info = $info[$id_attach[0]];
		else
			$info = array();

	return $info;
}

/**
 * format_bkmg - format a number in bytes, KB, MB or GB.
 *
 * @param int $bytes
 *
 * @return string
 *
 */
function format_bkmg($bytes)
{
	$bytes = (int) $bytes;

	if ($bytes < 1024)
	{
		$num = $bytes;
		$suffix = '';
	}
	elseif ($bytes < 1024**2)
	{
		$num = $bytes/1024;
		$suffix = 'KB';
	}
	elseif ($bytes < 1024**3)
	{
		$num = $bytes/1024**2;
		$suffix = 'MB';
	}
	elseif ($bytes < 1024**4)
	{
		$num = $bytes/1024**3;
		$suffix = 'GB';
	}
	else
	{
		$num = $bytes/1024**4;
		$suffix = 'TB';
	}
	return comma_format($num) . $suffix;
}

/**
 * query_attachments - loads the attachments that match the current filter.
 * This is used by the browsing/filtering functions as well as auto tag
 * admin function, for consistency.
 *
 * Expects a query parameters array, values including:
 * - sort, the full order by clause - REQUIRED
 * - start (offset for limit) - REQUIRED
 * - max (for limit) - REQUIRED
 * - fileext, an array
 * - poster
 * - tags, an array
 *
 * Updates $query_result['attachments'] with the results of the query.
 * Also updates $query_result['num_attachments'] with the # of attachments that meet the criteria.
 *
 * @params array - $query_parameters
 * 
 * @return array - $query_result
 *
 */
function query_attachments($query_parameters)
{
	global $smcFunc, $user_info;

	$query_result = array();
	$query_result['attachments'] = array();

	// The query parameters we can just set once here...
	$query_parameters['boards'] = boardsAllowedTo('view_attachments', true, true);
	$query_parameters['approved'] = 1;
	$query_parameters['attachment_type'] = 0;

	// Interlopers can't see poop...
	if (empty($user_info['is_admin']) && empty($query_parameters['boards']))
		redirectexit();

	// Note that when an admin, the response from boardsAllowedTo() is array(0), which means everything.
	// Or, we can just check if the user is an admin....
	if (!empty($user_info['is_admin']))
		$where_clause = '';
	else 
		$where_clause = 'AND m.id_board IN ({array_int:boards}) ';

	// Check other filter options & flesh out where clause further...
	if (!empty($query_parameters['fileexts']))
		$where_clause .= 'AND a.fileext IN ({array_string:fileexts}) ';

	if (!empty($query_parameters['poster']))
		$where_clause .= 'AND mem.real_name = {string:poster} ';

	// Date range specified?  Note we factor in modified as well as post dates here,
	// in case folks update attachments to a post.
	if (!empty($query_parameters['start_date']))
	{
		$where_clause .= 'AND CASE WHEN m.modified_time > 0 THEN m.modified_time >= {int:start_date} ELSE m.poster_time >= {int:start_date} END ';
	}

	// To capture whole date, check for < end_date + 1 day
	if (!empty($query_parameters['end_date']))
	{
		$query_parameters ['end_date'] += 86400;
		$where_clause .= 'AND CASE WHEN m.modified_time > 0 THEN m.modified_time < {int:end_date} ELSE m.poster_time < {int:end_date} END ';
	}

	// If tags in query, build tags clause
	$tags_clause = '';
	if (!empty($query_parameters['tags']))
	{
		$query_parameters['tags_clause'] = build_tags_clause($query_parameters['tags']);
		$tags_clause = 'AND {raw:tags_clause} ';
	}

	$request = $smcFunc['db_query']('', '
		SELECT a.id_attach
		FROM {db_prefix}attachments AS a
			INNER JOIN {db_prefix}messages AS m ON (a.id_msg = m.id_msg)
			LEFT JOIN {db_prefix}members AS mem ON (m.id_member = mem.id_member)
		WHERE a.approved = {int:approved}
			AND m.approved =  {int:approved}
			AND a.attachment_type = {int:attachment_type}
			' . $where_clause . '
			' . $tags_clause . '
		ORDER BY {raw:sort}, a.id_attach
		LIMIT {int:start}, {int:max}',
		$query_parameters
	);

	while ($row = $smcFunc['db_fetch_assoc']($request))
		$query_result['attachments'][] = $row;
	$smcFunc['db_free_result']($request);

	// Now that we have our narrow set of IDs, do additional navigation needed
	populate_rows($query_result['attachments']);

	// Now get the total count...  Same query parameters, but no LIMIT or ORDER BY.
	// This query can be quite expensive, oddly sometimes more expensive than the prior query.
	// But if folks are navigating around, e.g., paging & sorting, the count isn't different.
	// So, only do it if parameters have changed.

	if (new_count_needed($query_parameters))
	{
		$request = $smcFunc['db_query']('', '
			SELECT COUNT(*)
			FROM {db_prefix}attachments AS a
				INNER JOIN {db_prefix}messages AS m ON (a.id_msg = m.id_msg)
				LEFT JOIN {db_prefix}members AS mem ON (m.id_member = mem.id_member)
			WHERE a.approved = {int:approved}
				AND m.approved =  {int:approved}
				AND a.attachment_type = {int:attachment_type}
				' . $where_clause . '
				' . $tags_clause,
			$query_parameters
		);
		list($query_result['num_attachments']) = $smcFunc['db_fetch_row']($request);
		$smcFunc['db_free_result']($request);

		// Save it off...
		$_SESSION['attbr_prev_count']['count'] = $query_result['num_attachments'];
	}
	else
		$query_result['num_attachments'] = $_SESSION['attbr_prev_count']['count'];

	return $query_result;
}

/**
 * build_tags_clause - given the tags, build the AND clause.
 * The idea is to do ORs within categories, and ANDs across categories.
 * Data is cleansed here - only valid tags are used in the query.
 *
 * @param array $tags
 *
 * @return string $tags_clause
 *
 */
function build_tags_clause($tags)
{
	global $context;

	static $all_tags = null;
	if ($all_tags === null)
		$all_tags = get_tags();

	// Group the tags by category
	// Not found will be treated as an 'unknown' cat/tag, so an AND will be used.
	// This will basically result in an empty list, so the behavior will be
	// consistent with invalid fileext and poster, & no uncleansed data is ever used.
	$tags_by_cat = array();
	foreach ($tags AS $tag)
		if (array_key_exists($tag, $all_tags))
			$tags_by_cat[$all_tags[$tag]][] = $tag;
		else
			$tags_by_cat['unknown'][] = 'unknowntag';

	// Sanity check, in case passed a lot of junk (URL hand-edited...)
	if (empty($tags_by_cat))
		return '';

	// OK... Gottem grouped & cleansed...  Do the deed...
	$tags_clause = '';
	foreach ($tags_by_cat AS $cats)
	{
		$tags_clause .= '(';
		foreach ($cats AS $cat => $tag)
		{
			$tags_clause .= '(FIND_IN_SET(\'' . $tag . '\', tags) != 0) OR ';
		}
		$tags_clause = substr($tags_clause, 0, -4);
		$tags_clause .= ') AND ';
	}
	$tags_clause = substr($tags_clause, 0, -5);

	return $tags_clause;
}

/**
 * populate_rows - given the attachment IDs, get everything else.
 * Enables QueryAttachments to be nice & lean & only get the IDs.
 *
 * @param array &$attachments - passed by reference
 *
 * @return null
 *
 */
function populate_rows(&$attachments)
{
	$get_these = array();
	foreach ($attachments AS $ix => $att)
		$get_these[$ix] = $att['id_attach'];

	$info = get_attachment_info(array_values($get_these));

	foreach ($attachments AS $ix => $att)
		$attachments[$ix] = $info[$att['id_attach']];
}

/**
 * new_count_needed - detect if query *filter* parameters have changed.
 * (Sort & start have no bearing on the count, only the filters...)
 * Note the session is used, instead of cache, because this is unique to the user.
 * Will also recount if more than 10 minutes has passed - items may have been added, etc.
 *
 * That count can be expensive on large boards, so avoid it if we can.
 * Note the count in the session *must* be set by the query in query_attachments().
 *
 * @params array - $query_parameters
 * 
 * @return bool - Filters have changed, so a recount is needed
 *
 */
function new_count_needed($query_parameters)
{
	// Capture the current filters: boards, fileexts, poster & tags
	$temp = array();
	if (!empty($query_parameters['boards']))
		$temp['boards'] = $query_parameters['boards'];
	if (!empty($query_parameters['fileexts']))
		$temp['fileexts'] = $query_parameters['fileexts'];
	if (!empty($query_parameters['poster']))
		$temp['poster'] = $query_parameters['poster'];
	if (!empty($query_parameters['tags']))
		$temp['tags'] = $query_parameters['tags'];
	if (!empty($query_parameters['start_date']))
		$temp['start_date'] = $query_parameters['start_date'];
	if (!empty($query_parameters['end_date']))
		$temp['end_date'] = $query_parameters['end_date'];

	if (!isset($_SESSION['attbr_prev_count']['count']) || !isset($_SESSION['attbr_prev_count']['filters']) || !isset($_SESSION['attbr_prev_count']['timer']) || ($temp !== $_SESSION['attbr_prev_count']['filters']) || (time() - $_SESSION['attbr_prev_count']['timer'] > 600))
	{
		$count_needed = true;
		$_SESSION['attbr_prev_count'] = array();
		$_SESSION['attbr_prev_count']['filters'] = $temp;
		$_SESSION['attbr_prev_count']['timer'] = time();
	}
	else
		$count_needed = false;

	return $count_needed;
}

/**
 * get_admin_attachment_count - Just return the count...
 *
 * To be called from admin panel ***ONLY***...
 * The idea is to count all attachments, i.e., admin access.
 * Intended for use in progress bar in AutoTagAll, before you've even started querying attachments.
 *
 * @return int - $total_attachments
 *
 */
function get_admin_attachment_count()
{
	global $smcFunc, $user_info;

	// Setup all the query parameter defaults.
	$query_parameters = array(
		'approved' => 1,
		'attachment_type' => 0,
	);

	// Get the total count...
	$request = $smcFunc['db_query']('', '
		SELECT COUNT(*)
		FROM {db_prefix}attachments AS a
			INNER JOIN {db_prefix}messages AS m ON (a.id_msg = m.id_msg)
			INNER JOIN {db_prefix}boards AS b ON (m.id_board = b.id_board)
		WHERE a.approved = {int:approved}
			AND m.approved =  {int:approved}
			AND a.attachment_type = {int:attachment_type}',
		$query_parameters
	);

	list($total_attachments) = $smcFunc['db_fetch_row']($request);
	$smcFunc['db_free_result']($request);

	return $total_attachments;
}

/**
 * get_message_text_fields - gets subject & body for specified message
 *
 * @param int - message ID
 *
 * @return array
 *
 */
function get_message_text_fields($msg_id)
{
	global $smcFunc;

	if (empty($msg_id) || !is_numeric($msg_id))
		return array();

	$msg_id = (int) $msg_id;
	$request = $smcFunc['db_query']('', '
		SELECT id_board, subject, body
		FROM {db_prefix}messages
		WHERE id_msg = {int:id_msg}',
		array(
			'id_msg' => $msg_id
		)
	);
	if ($smcFunc['db_num_rows']($request) == 1)
		return $smcFunc['db_fetch_assoc']($request);
	else
		return array();
}

/**
 * get_board_text_fields - gets name and description for specified board
 *
 * @param int - board ID
 *
 * @return array
 *
 */
function get_board_text_fields($board_id)
{
	global $smcFunc;

	if (empty($board_id) || !is_numeric($board_id))
		return array();

	$board_id = (int) $board_id;
	$request = $smcFunc['db_query']('', '
		SELECT id_parent, id_cat, name, description
		FROM {db_prefix}boards
		WHERE id_board = {int:id_board}',
		array(
			'id_board' => $board_id
		)
	);
	if ($smcFunc['db_num_rows']($request) == 1)
		return $smcFunc['db_fetch_assoc']($request);
	else
		return array();
}

/**
 * get_cat_text_fields - gets name and description for specified category
 *
 * @param int - category ID
 *
 * @return array
 *
 */
function get_cat_text_fields($cat_id)
{
	global $smcFunc;

	if (empty($cat_id) || !is_numeric($cat_id))
		return array();

	$cat_id = (int) $cat_id;
	$request = $smcFunc['db_query']('', '
		SELECT name, description
		FROM {db_prefix}categories
		WHERE id_cat = {int:id_cat}',
		array(
			'id_cat' => $cat_id
		)
	);
	if ($smcFunc['db_num_rows']($request) == 1)
		return $smcFunc['db_fetch_assoc']($request);
	else
		return array();
}

/**
 * get_unique_fileexts - gets unique file extensions for the dropdown.
 *
 * Use cache if available.
 *
 * @return array fileexts
 *
 */
function get_unique_fileexts()
{
	global $smcFunc, $cache_enable;

	if (($fileexts = cache_get_data('attbr_fileext', 960)) == null)
	{
		$fileexts = array();
		$request = $smcFunc['db_query']('', '
			SELECT DISTINCT a.fileext
			FROM {db_prefix}attachments AS a
			WHERE a.fileext != {string:blank}
			ORDER BY a.fileext',
			array(
				'blank' => ''
			)
		);
		while ($row = $smcFunc['db_fetch_assoc']($request))
			$fileexts[] = $row['fileext'];
		$smcFunc['db_free_result']($request);

		if (!empty($cache_enable))
			cache_put_data('attbr_fileext', $fileexts, 960);
	}

	return $fileexts;
}

/**
 * add_attbr_background_task.
 *
 * @param array $attach_ids
 * @param int $msg_id
 *
 * @return null
 *
 * @return array fileexts
 *
 */
function add_attbr_background_task($attach_ids, $msg_id)
{
	global $smcFunc;

	if (!empty($attach_ids) && !empty($msg_id))
	{
		$smcFunc['db_insert']('',
			'{db_prefix}background_tasks',
			array('task_file' => 'string', 'task_class' => 'string', 'task_data' => 'string', 'claimed_time' => 'int'),
			array(
				'$sourcedir/tasks/AttachmentBrowser-AutoTag.php', 'Attachment_AutoTag_Background', $smcFunc['json_encode'](array(
					'attach_ids' => $attach_ids,
					'msg_id' => $msg_id
				)), 0
			),
			array('id_task')
		);
	}
}

/**
 * delete_setting - deletes a setting from the settings table.
 *
 * @param string setting
 *
 * @return null
 *
 */
function delete_setting($setting)
{
	global $smcFunc;

	$request = $smcFunc['db_query']('', '
		DELETE FROM {db_prefix}settings
		WHERE variable = {string:setting}',
		array(
			'setting' => $setting
		)
	);
}
