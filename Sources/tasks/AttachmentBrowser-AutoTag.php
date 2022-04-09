<?php
/**
 * Invoked as a background task to auto-tag new attachments.
 */
class Attachment_AutoTag_Background extends SMF_BackgroundTask
{
	/**
	 * This executes the task: Adds tags for the specified attachment on the specified message.
	 * and inserts any alerts as needed.
	 *
	 * @return bool Always returns true
	 */
	public function execute()
	{
		global $sourcedir, $cache_enable, $cacheAPI;

		$attach_ids = $this->_details['attach_ids'];
		$msg_id = $this->_details['msg_id'];

		require_once($sourcedir . '/AttachmentBrowser.php');
		auto_tag_message($msg_id, $attach_ids);

		return true;
	}
}

?>