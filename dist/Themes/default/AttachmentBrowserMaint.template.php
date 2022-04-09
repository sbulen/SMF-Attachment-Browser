<?php
/**
 *	Template for admin functions for the Attachment Browser mod for SMF.
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

/**
 * Displays a sortable listing of all attachments the user can see.
 */
function template_tags()
{
	global $context, $scripturl, $txt;

	// Add tags
	echo '
	<div>
		<form action="', $scripturl, '?action=admin;area=attbrtag;sa=tagmaint" method="post" accept-charset="', $context['character_set'], '">
			<div class="cat_bar">
				<h3 class="catbg">', $txt['attbr_add_tag'], '
				</h3>
			</div>
			<div class="windowbg">
				<dl class="settings">';

	// Tag.
	echo '
					<dt>
						<strong>', $txt['attbr_tag'], ':</strong><br>
						<span class="smalltext">', $txt['attbr_tag_desc'], '</span>
					</dt>
					<dd>
						<input type="text" name="tag_name" size="30">
					</dd>';

	// Aliases.
	echo '
					<dt>
						<strong>', $txt['attbr_aliases'], ':</strong><br>
						<span class="smalltext">', $txt['attbr_aliases_desc'], '</span>
					</dt>
					<dd>
						<input type="text" name="alias_name" size="60">
					</dd>';

	// Category.
	echo '
					<dt>
						<strong>', $txt['attbr_category'], ':</strong><br>
						<span class="smalltext">', $txt['attbr_category_desc'], '</span>
					</dt>
					<dd>
						<input type="text" name="cat_name" size="30">
					</dd>';

	// Table footer & button.
	echo '
				</dl>
				<input type="submit" name="add" value="', $txt['attbr_add_tag'] ,'" class="button">
				<input type="hidden" name="', $context['attbr_tag_maint_token_var'], '" value="', $context['attbr_tag_maint_token'], '">
				<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '">';

	echo '
			</div>
		</form>
	</div>';

	// List all Categories & tags...
	echo '
	<div>
		<form action="', $scripturl, '?action=admin;area=attbrtag;sa=tagmaint" method="post" accept-charset="', $context['character_set'], '">
			<div class="cat_bar">
				<h3 class="catbg">', $txt['attbr_tagmaint'], '
				</h3>
			</div>
			<div class="windowbg">';

	// Loop thru categories and tags
	foreach($context['tag_info'] AS $cat => $tags)
	{
		echo '
				<fieldset>
					<legend>&nbsp;', $cat, '&nbsp;</legend>
					<ul>';

		foreach($tags AS $tag => $aliases)
		{
			echo '
						<li>
							<input name="tag[', $tag, ']" type = "checkbox">
							<label for="', $tag, '">', $tag, (!empty($aliases) ? ' (' . $aliases . ')' : ''), '</label>
						</li>';
		}

		echo '
					</ul>
				</fieldset>';
	}

	// Table footer & button.
	echo '
				<input type="submit" name="delete" value="', $txt['attbr_delete_tag'] ,'" class="button">
				<input type="hidden" name="', $context['attbr_tag_maint_token_var'], '" value="', $context['attbr_tag_maint_token'], '">
				<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '">';

	echo '
			</div>
		</form>
	</div>';
}

/**
 * A page allowing people to filter the attachment list.
 */
function template_autotag()
{
	global $context, $scripturl, $txt;

	// Add tags
	echo '
	<div>
		<form action="', $scripturl, '?action=admin;area=attbrtag;sa=autotag" method="post" name="auto_sub" accept-charset="', $context['character_set'], '">
			<div class="cat_bar">
				<h3 class="catbg">', $txt['attbr_autotag'], '
				</h3>
			</div>
			<div class="windowbg">';

	// Progress.
	echo '
				<div class="windowbg">
					<div>
						<p>', $txt['attbr_autotag_desc'], '</p>
						<div class="progress_bar">
							<span>', $context['attbr_tagall_percentage'], '%</span>
							<div class="bar" style="width: ', $context['attbr_tagall_percentage'], '%;"></div>
						</div>
					</div>
					<input type="submit" name="status" value="', $context['attbr_autotag_status'], '" class="button"', $context['attbr_autotag_status'] == $txt['attbr_complete'] ? ' disabled' : '' ,'>
					<input type="hidden" name="', $context['attbr_autotag_token_var'], '" value="', $context['attbr_autotag_token'], '">
					<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '">
				</div>';

	echo '
			</div>
		</form>
	</div>';

	// Autosubmit script...
	if (!empty($_POST) && ($context['attbr_autotag_status'] != $txt['attbr_complete']))
		echo '
		<script>
			var countdown = 2;
			doAutoSubmit();

			function doAutoSubmit()
			{
				if (countdown == 0)
					document.forms.auto_sub.submit();
				else if (countdown == -1)
					return;

				document.forms.auto_sub.status.value = "', $txt['attbr_continue'], ' (" + countdown + ")";
				countdown--;

				setTimeout("doAutoSubmit();", 1000);
			}
		</script>';
}