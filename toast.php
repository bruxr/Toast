<?php
/**
 * Toast
 * Toasts RSS feeds!
 *
 * @package Toast
 * @author brux <brux.romuar@gmail.com>
 */
/*
Plugin Name: Toast
Plugin URI: http://github.com/imoz32/Toast
Description: Automatically imports new posts from a RSS feed.
Author: Brux
Version: 0.2
*/

/**
 * Sets the default options (if they aren't set yet) on
 * plugin activation.
 */
function toast_activate()
{

    $options = get_option('toast');

    if ( ! $options )
    {

        $current_user = get_current_user_id();

        $options = array(
            'rss'       => '',
            'author'    => $current_user
        );
        update_option('toast', $options);

    }

}
register_activation_hook(__FILE__, 'toast_activate');

/**
 * Adds a Toast settings page, under the WP's settings menu.
 */
function toast_admin_menus()
{

    add_options_page('Toast', 'Toast', 'manage_options', 'toast', 'toast_settings_page');

}
add_action('admin_menu', 'toast_admin_menus');

/**
 * Imports posts from the saved RSS feed URL.
 * Returns an array of post IDs, or a FALSE if the import failed.
 * 
 * @return array|boolean
 */
function toast_import()
{

    // Grab our options
    $options = get_option('toast');

    // Initialize SimpleXML
    $rss_url = $options['rss'];
    $rss = wp_remote_fopen($rss_url);
    $rss = simplexml_load_string($rss);
    if ( ! $rss )
        return false;

    $gmt = new DateTimeZone('GMT');

    // Start importing each post!
    $posts = array();
    foreach ( $rss->channel->item as $item )
    {

        $guid = strval($item->guid);
        if ( ! toast_post_exists($guid) )
        {

            $post_date = new DateTime($item->pubDate);

            // Create the post
            $post_id = wp_insert_post(array(
                'post_title'    => strval($item->title),
                'post_author'   => $options['author'],
                'post_content'  => strval($item->description),
                'post_date'     => $post_date->format('Y-m-d H:i:s'),
                'post_date_gmt' => $post_date->setTimezone($gmt)->format('Y-m-d H:i:s'),
                'post_status'   => 'publish'
            ));

            // Parse the categories
            $cats = array();
            foreach ( $item->category as $category )
            {
                $cats[] = wp_create_category(strval($category));
            }
            wp_set_post_terms($post_id, $cats, 'category');

            // Set the GUID
            add_post_meta($post_id, '_toast_guid', strval($item->guid));

            $posts[] = $post_id;

        }

    }

    return $posts;

}

/**
 * Returns TRUE if a post with the provided GUID already exists.
 * 
 * @param  string $guid GUID/Globally Unique Identifier
 * @return bool
 */
function toast_post_exists($guid)
{

    $posts = get_posts(array(
        'post_type'     => 'post',
        'post_status'   => null,
        'meta_key'      => '_toast_guid',
        'meta_value'    => $guid
    ));

    return count($posts) >= 1;

}

/**
 * Generates the Toast settings page.
 */
function toast_settings_page()
{

    $message = '';

    $options = get_option('toast');

    $users = get_users(array(
        'orderby'   => 'display_name'
    ));

    // Save options
    if ( isset($_POST['save_changes']) )
    {

        $rss = trim($_POST['rss_url']);
        $author = intval($_POST['author']);

        $options = compact('rss', 'author');
        update_option('toast', $options);

        $message = '<strong>Settings saved.</strong>';

    }

    // Force import
    if ( isset($_POST['force_import']) )
    {

        $posts = toast_import();
        $num_posts = $posts ? count($posts) : 0;

        $message = sprintf('<strong>Import Finished.</strong> %d posts imported.', $num_posts);

    }

?>
<div class="wrap">
    
    <?php screen_icon(); ?>
    <h2>Toast Settings</h2>

    <?php if ( $message ): ?>
    <div class="updated"> 
        <p><?php echo $message; ?></p>
    </div>
    <?php endif; ?>

    <form action="options-general.php?page=toast" method="post">

        <p><label>Import posts from this RSS Feed: <input type="url" name="rss_url" value="<?php echo $options['rss']; ?>" class="regular-text"></label></p>

        <p><label>Default Author:
            <select name="author">
            <?php foreach ( $users as $user ): ?>
                <option value="<?php echo $user->ID; ?>" <?php selected($user->ID, $options['author']); ?>><?php echo esc_html($user->display_name); ?></option>
            <?php endforeach; ?>
            </select></label></p>

        <p><input type="submit" name="save_changes" value="Save Changes" class="button-primary">
            <input type="submit" name="force_import" value="Import Posts Now" class="button"></p>

    </form>

</div>
<?php
}

?>