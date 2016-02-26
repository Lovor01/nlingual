<p><?php _e( 'You can customize key to look under for getting the language requested in fetching objects. This can be customized for purposes like conflict prevention with other translation systems, or just personal taste.', 'nlingual' ); ?></p>

<p><?php _e( 'When someone visits the site, the language to be served is determined by the following checks, each overriding the previous if matched:', 'nlingual' ); ?></p>

<ol>
	<li><?php _e( 'The visitor’s language according to their browser.', 'nlingual' );?></li>
	<li><?php _e( 'The language specified in the URL (based on the scheme specified by the Redirection Method)', 'nlingual' );?></li>
	<li><?php _e( 'The GET/POST query argument if specified.', 'nlingual' );?></li>
	<li><?php _e( '(Optional) The language of the requested object if it differs from the on previously detected.', 'nlingual' );?></li>
</ol>

<p><?php _e( 'If the language cannot be determined by any of the above means, the language specified as the default will be used.', 'nlingual' ); ?></p>

<p><?php _e( 'By default, any redirects will be performed as "temporary" (HTTP 302). You can set these to to be "permanent" (HTTP 301) instead. <em>This is often better for SEO, but changes to the settings after the fact or conflicts caused by other plugins/themes could result in hard-to-undo errors.</em>', 'nlingual' );?></p>