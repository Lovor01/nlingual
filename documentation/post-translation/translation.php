<?php
// Get the current post type and singular/plural labels
$post_type = get_current_screen()->post_type;
$singular = strtolower( get_post_type_object( $post_type )->labels->singular_name );
$plural = strtolower( get_post_type_object( $post_type )->labels->name );
?>
<title><?php _e( 'Languages & Translations', 'nlingual' ); ?></title>

<p><?php
/* translators: This uses markdown-style formatting; **bold text** [link text](url). %1$s = The singular name of the post type, %2$s = The URL for the link. */
echo nLingual\markitup( _f( 'The **Languages & Translation** box allows you to assign a language to this %1$s, from the list of languages that have been registered [here](%2$s) for use. Once you assign a language, you can also assign the translated versions of the %1$s in each of the other languages, assuming they exist already.', 'nlingual', $singular, admin_url( 'admin.php?page=nlingual-languages' ) ) ); ?></p>

<p><?php _e( 'If you don’t have a translated version for a particular language yet, you can select the option to create a new one; a copy of the current post will be created in draft form for you to work on.', 'nlingual' ); ?></p>

<p><?php
/* translators: This uses markdown-style formatting; [link text](url). %s = The URL for the link. */
echo nLingual\markitup( _f( 'When you save your changes, certain fields and settings will be copied to it’s sister translations, synchronizing them. The exact fields/settings that will be synchronized is controlled [here](%s).', 'nlingual', admin_url( 'admin.php?page=nlingual-synchronizer' ) ) ); ?></p>

<?php require NL_PLUGIN_DIR . '/documentation/shared/post-sync-summary.php'; ?>
