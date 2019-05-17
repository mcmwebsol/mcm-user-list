# mcm-user-list
Lists users in WordPress using a shortcode

=== Plugin Name ===
Contributors: mcmwebsol
Tags: plugin
Requires at least: 5.1
Tested up to: 5.2
Requires PHP: 5.6
Stable tag: 1.0
License: GPLv2
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Implements a shortcode [mcm-user-list] for displaying and filtering users



== Installation ==

1. Unzip the plugin zip file
2. Upload the entire folder to the `/wp-content/plugins/` directory
3. Activate the plugin through the 'Plugins' menu in WordPress


== Changelog ==

= 1.2 =
* Use add_shortcode instead of content_filter
* improve UI/UX
* improve comments

= 1.1 =
* Bug fix - remove debug code that broke AJAX responses when WP_DEBUG was set to false

= 1.0 =
* Initial version
