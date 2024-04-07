<?php
/**
 *	Logic for the Attachment Browser mod hooks.
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
 *
 * Hook function - Add the attachment browser buttons to the main menu.
 *
 * Hook: integrate_menu_buttons
 *
 * @param array $buttons
 * @return null
 *
 */
function attachment_browser_buttons(&$buttons)
{
	global $scripturl, $txt;

	// Can they see attachments on any boards?
	$boards = boardsAllowedTo('view_attachments', true, true);

	$show = !empty($boards);

	loadLanguage('Post+AttachmentBrowser');
	$title = $txt['attachments'];

	// Add to the main menu
	$buttons['attbr'] = array(
		'title' => $title,
		'icon' => 'attachment',
		'href' => $scripturl . '?action=attbr',
		'show' => $show,
		'sub_buttons' => array(
			'attbr_view' => array(
				'title' => $txt['attbr_browse'],
				'href' => $scripturl . '?action=attbr;sa=all',
				'show' => true,
			),
			'attbr_filter' => array(
				'title' => $txt['attbr_filter'],
				'href' => $scripturl . '?action=attbr;sa=filter',
				'show' => true,
				'is_last' => true,
			),
		),
	);
}

/**
 *
 * Hook function - Add the Attachment Browser action to the main action array in index.php.
 *
 * Hook: integrate_actions
 *
 * @param array $actionArray
 * @return null
 *
 */
function attachment_browser_actions(&$action_array)
{
	$action_array['attbr'] = array('AttachmentBrowser.php', 'browse_attachments');
}

/**
 * Hook function - If you're the current action, well, make yourself the current action... 
 *
 * Hook: integrate_current_action
 *
 * @param string $current_action
 * @return null
 *
 */
function attachment_browser_current_action(&$current_action)
{
	global $context;

	if (isset($context['current_action']) && ($context['current_action'] == 'attbr'))
		$current_action = 'attbr';
}

/**
 *
 * Hook function - If adding attachments, create a background task to auto tag the attachment.
 * Done in this fashion so we can navigate the entire board/category hierarchy to determine all tags.
 *
 * Hook: integrate_assign_attachments
 *
 * @param array $attach_ids
 * @param int $msg_id
 *
 * @return null
 *
 */
function attachment_browser_auto_tag($attach_ids, $msg_id)
{
	global $sourcedir, $smcFunc;

	if (!empty($attach_ids) && !empty($msg_id))
	{
		require_once($sourcedir . '/AttachmentBrowserModel.php');
		add_attbr_background_task($attach_ids, $msg_id);
	}
}

/**
 *
 * Hook function - Add admin menu functions.
 *
 * Hook: integrate_admin_areas
 *
 * @param array $menu
 *
 * @return null
 *
 */
function attachment_browser_admin_menu(&$menu)
{
	global $txt;

	loadLanguage('AttachmentBrowser');
	$title = $txt['attbr_tags'];

	// Add to the main menu
	$menu['layout']['areas']['attbrtag'] = array(
		'label' => $title,
		'file' => 'AttachmentBrowser.php',
		'function' => 'attachment_tags',
		'icon' => 'attachment',
		'permission' => 'manage_attachments',
		'subsections' => array(
			'tagmaint' => array($txt['attbr_tagmaint']),
		    'autotag' => array($txt['attbr_autotag']),
		),
	);
}
