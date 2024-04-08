<?php
/**
 *	Template for the Attachment Browser mod for SMF.
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

/**
 * Displays a sortable listing of all attachments the user can see.
 */
function template_main()
{
	global $context, $settings, $scripturl, $txt;

	echo '
	<div class="main_section">
		<div class="pagesection">
			', template_button_strip($context['attbr_buttons'], 'right'), '
			<div class="pagelinks floatleft">', $context['page_index'], '</div>
		</div>
		<div class="cat_bar">
			<h3 class="catbg">
				<span class="floatleft">', $txt['attbr_list'], '</span>
			</h3>
		</div>';

	echo '
		<div id="mlist">
			<table class="table_grid">
				<thead>
					<tr class="title_bar">';

	// Display each of the column headers of the table.
	foreach ($context['columns'] as $key => $column)
	{
		if ($key == 'email_address' && !$context['can_send_email'])
			continue;

		// This is a selected column, so underline it or some such.
		if ($column['selected'])
			echo '
						<th scope="col" class="', isset($column['class']) ? ' ' . $column['class'] : '', ' selected" style="width: auto;">
							<a href="' . $column['href'] . '" rel="ugc">' . $column['label'] . '</a><span class="main_icons sort_' . $context['sort_direction'] . '"></span></th>';

		// This is just some column... show the link and be done with it.
		else
			echo '
						<th scope="col" class="', isset($column['class']) ? ' ' . $column['class'] : '', '"', isset($column['width']) ? ' style="width: ' . $column['width'] . '"' : '', '>
						', $column['link'], '</th>';
	}

	echo '
					</tr>
				</thead>
				<tbody>';

	// Loop through each one displaying the data.
	if (!empty($context['attachments']))
	{
		foreach ($context['attachments'] as $attachment)
		{
			echo '
					<tr class="windowbg">',
						'<td class="absubject lefttext"><a href="' . $scripturl . '?msg=', $attachment['id_msg'], '" rel="ugc" target="_blank">', $attachment['subject'], '</a></td>',
						'<td class="abposter lefttext">', $attachment['real_name'], '</td>',
						'<td class="abposttime lefttext">', $attachment['post_time'], '</td>',
						'<td class="abfilename lefttext"><a href="' . $scripturl . '?action=dlattach;attach=', $attachment['id_attach'], '" rel="ugc">', $attachment['filename'], '</a></td>',
						'<td class="abfileext lefttext">', $attachment['fileext'], '</td>',
						'<td class="absize righttext">', $attachment['size'], '</td>',
						'<td class="abdownloads righttext">', $attachment['downloads'], '</td>',
						'<td class="abtags lefttext">', empty($attachment['editable']) ? $attachment['tags'] : '<a href="' . $scripturl . '?action=attbr;sa=editattach;attach=' . $attachment['id_attach'] . '" rel="ugc">' . (empty($attachment['tags']) ? '...' : $attachment['tags']), '</a></td>',
					'</tr>';
		}
	}
	// Display a message across all columns
	else
		echo '
					<tr>
						<td colspan="7" class="windowbg">', $txt['search_no_results'], '</td>
					</tr>';

	echo '
				</tbody>
			</table>
		</div>';

	// Show the page numbers again.
	echo '
		<div class="pagesection">
			<div class="pagelinks floatleft">', $context['page_index'], '</div>';

	// If displaying the result of a search show a "search again" link to edit their criteria.
	// again=1 helps differentiate between going TO there or FROM there...
	if (!empty($context['search_again_string']))
		echo '
			<div class="buttonlist floatright">
				<a class="button" href="', $scripturl, '?action=attbr;sa=filter;again=1', $context['search_again_string'], '">', $txt['mlist_search_again'], '</a>
			</div>';
	echo '
		</div>
	</div>';
}

/**
 * A page allowing people to filter the attachment list.
 */
function template_filter()
{
	global $context, $scripturl, $txt;

	// Start the submission form for the filter
	echo '
	<form action="', $scripturl, '?action=attbr;sa=filter" method="post" accept-charset="', $context['character_set'], '">
		<div>
			<div class="pagesection">
				', template_button_strip($context['attbr_buttons'], 'right'), '
			</div>
			<div class="cat_bar">
				<h3 class="catbg mlist">
					<span class="main_icons filter"></span>', $txt['attbr_search'], '
				</h3>
			</div>
			<div id="attbr_search" class="roundframe">
				<dl id="attbr_params" class="settings">';

	// Poster dropdown
	echo '
					<dt>
						<label for="poster"><strong>', $txt['poster_name'], ':</strong></label>
					</dt>
					<dd>
						<input id="userspec" type="text" name="poster" size="40" autocomplete="off" value="', (!empty($context['old_search']['poster']) ? $context['old_search']['poster'] : ''), '">
					</dd>';

	// Posted on date range
	echo '
					<div id="post_range_input">
						<dt>
							<label for="start_date"><strong>', $txt['post_time'], ':</strong></label>
						</dt>
						<dd>
							<input type="text" name="start_date" id="start_date" value="', (!empty($context['old_search']['start_date']) ? $context['old_search']['start_date'] : ''), '" class="date_input start" data-type="date"> ', strtolower($txt['to']), ' </strong></label><input type="text" name="end_date" id="end_date" value="', (!empty($context['old_search']['end_date']) ? $context['old_search']['end_date'] : ''), '" class="date_input end" data-type="date">
						</dd>
					</div>
				</dl>';

	// File extension dropdown
	echo '
			<fieldset>
				<legend>&nbsp;', $txt['fileext_long'], '&nbsp;</legend>
				<ul>';

	foreach ($context['fileexts'] as $ix => $fileext)
	{
		echo '
					<div class="inline_block">
						<input id="attach_', $fileext, '" name="fileexts[', $fileext, ']" type="checkbox"', (!empty($context['old_search']['fileexts']) && in_array($fileext, $context['old_search']['fileexts'])  ? "checked" : ''), '>
						<label for="attach_', $fileext, '">', $fileext, '</label>
					</div>';
	}

	echo '
				</ul>
			</fieldset>';

	// Loop thru categories and tags
	foreach($context['tag_info'] AS $cat => $tags)
	{
		echo '
				<fieldset>
					<legend>&nbsp;', $cat, '&nbsp;</legend>';

		foreach($tags AS $tag => $aliases)
		{
			echo '
						<div class="inline_block">
							<input name="tags[', $tag, ']" type="checkbox"', (!empty($context['old_search']['tags']) && in_array($tag, $context['old_search']['tags']) ? "checked" : ''), '>
							<label for="', $tag, '">', $tag, (!empty($aliases) ? ' (' . $aliases . ')' : ''), '</label>
						</div>';
		}

		echo '
				</fieldset>';
	}

	echo '
				<input type="submit" name="submit" value="' . $txt['search'] . '" class="button floatright">
			</div>
		</div>
	</form>';

	// Make that autosuggest work...
	echo '
	<script>
		var oAddMemberSuggest = new smc_AutoSuggest({
			sSelf: \'oAddMemberSuggest\',
			sSessionId: smf_session_id,
			sSessionVar: smf_session_var,
			sControlId: \'userspec\',
			sSearchType: \'member\',
			bItemList: false
		});
	</script>';
}

/**
 * A page allowing people to edit the tags on an attachment.
 */
function template_editattach()
{
	global $context, $scripturl, $txt;

	// Start the submission form for the edit attachment tags form
	echo '
	<form action="', $scripturl, '?action=attbr;sa=editattach;attach=', $context['attachment']['id_attach'], '" method="post" accept-charset="', $context['character_set'], '">
		<div>
			<div class="pagesection">
				', template_button_strip($context['attbr_buttons'], 'right'), '
			</div>
			<div class="cat_bar">
				<h3 class="catbg mlist">
					<span class="main_icons filter"></span>', $txt['attbr_editattachtags'], '
				</h3>
			</div>
			<div id="attbr_editattach" class="roundframe">
				<dl id="attbr_params" class="settings">';

	// Message subject
	echo '
					<dt>
						<label for="subject"><strong>', $txt['subject'], ':</strong></label>
					</dt>
					<dd>' . $context['attachment']['subject'] . '</dd>';

	// Poster
	echo '
					<dt>
						<label for="poster"><strong>', $txt['poster_name'], ':</strong></label>
					</dt>
					<dd>' . $context['attachment']['real_name'] . '</dd>';

	// File name
	echo '
					<dt>
						<label for="filename"><strong>', $txt['filename'], ':</strong></label>
					</dt>
					<dd>' . $context['attachment']['filename'] . '</dd>';

	// Fileext
	echo '
					<dt>
						<label for="fileext"><strong>', $txt['fileext'], ':</strong></label>
					</dt>
					<dd>' . $context['attachment']['fileext'] . '</dd>';

	// Filesize
	echo '
					<dt>
						<label for="filesize"><strong>', $txt['filesize'], ':</strong></label>
					</dt>
					<dd>' . $context['attachment']['size'] . '</dd>';

	// Downloads
	echo '
					<dt>
						<label for="downloads"><strong>', $txt['downloads'], ':</strong></label>
					</dt>
					<dd>' . $context['attachment']['downloads'] . '</dd>';

	// Wrap up the list
	echo '
				</dl>';

	// Loop thru categories and tags
	foreach($context['tag_info'] AS $cat => $tags)
	{
		echo '
				<fieldset>
					<legend>&nbsp;', $cat, '&nbsp;</legend>';

		foreach($tags AS $tag => $aliases)
		{
			echo '
						<div class="inline_block">
							<input name="tags[', $tag, ']" type="checkbox"', (!empty($context['attachment']['tags']) && in_array($tag, $context['attachment']['tags']) ? "checked" : ''), '>
							<label for="', $tag, '">', $tag, (!empty($aliases) ? ' (' . $aliases . ')' : ''), '</label>
						</div>';
		}

		echo '
				</fieldset>';
	}

	// If available, let them go back to exactly where they came from...
	if (!empty($context['return_info']))
	{
		echo '
				<button type="button" class="button floatright" onclick="location.href=\'' . $context['return_info'] . '\';">' . $txt['return'] . '</button>';
	}

	echo '
				<input type="submit" name="submit" value="' . $txt['submit'] . '" class="button floatright">
				<input type="hidden" name="', $context['attbr_edit_tags_token_var'], '" value="', $context['attbr_edit_tags_token'], '">
				<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '">
			</div>
		</div>
	</form>';


}