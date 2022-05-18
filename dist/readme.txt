[b]Description:[/b]
The Attachment Browser lets you browse, filter & sort attachments, while honoring user access.

A simple tagging system is provided.  Categories of tags are supported.  Each tag belongs to a single category, and each tag may have one or more aliases.  

A form is also provided to allow users to add or remove tags from their attachments.  Admins may also update tags.  To update the tags on an attachment, click on the tags value when browsing.

[b]Auto Tagging:[/b]
When an attachment is added to a post, the message subject and body are scanned for tags & their aliases.  The board and category names & descriptions are also scanned.  If found, the tags are associated with the attachment.

There is also a bulk Auto Tag admin feature, where all existing attachments can be auto-tagged.  You may choose to run this after you configure your tags so historical attachments are included.  The Auto Tag process can be re-run - tags found are added to the existing list.

For example, if you have different boards set up for different products in your forum, with tags that correspond to the products, you can easily filter by product.

[b]Tag Filters:[/b]
When using the tags to filter the list of attachments, the search utilizes ANDs across tag categories, and ORs within tag categories.

A "Search Again" button is provided to allow the user to refine the filter.

[b]Limitations:[/b]
 - Only 255 characters of tag info per attachment are supported.  You need to find the right level of categorization.
 - The focus is attachments - avatars & thumbnails are excluded.
 - Performance is a concern given the flexibility provided.  This mod may not be appropriate for extremely large forums.  In testing, for forums with less than 20K attachments, performance is sub-second.  For a forum with 500K attachments, performance is ~2-6 seconds per page. YMMV. The more filters used to narrow the result set, the better!

[b]Releases:[/b]
 - v1.9 Addresses array offset on null error
 - v1.8 Addresses undefined error
 - v1.7 Added Spanish translations
 - v1.6 Initial public release

[b]Acknowledgements:[/b]
 - Thanks to Bugo for the Russian translation!
 - Thanks to @rjen for the Dutch translation!
 - Thanks to -Rock Lee- for the Spanish translations!
