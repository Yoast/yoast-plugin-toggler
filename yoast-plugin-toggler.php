<?php
/*
Plugin Name: Yoast plugin toggler
Plugin URI: https://github.com/Yoast/yoast-plugin-toggler
Description: Toggle between the premium and free version of Yoast plugins
Version: 1.1
Author: Team Yoast
Author URI: https://github.com/Yoast/yoast-plugin-toggler
*/

define( 'YOAST_PLUGIN_TOGGLE_FILE', __FILE__ );
define( 'YOAST_PLUGIN_TOGGLE_DIR', dirname( YOAST_PLUGIN_TOGGLE_FILE ) );

// Because there is only one class it could be required directly, but only when is_admin and user can activate plugins
if ( ! class_exists( 'Yoast_Plugin_Toggler', false ) ) {
	require YOAST_PLUGIN_TOGGLE_DIR . '/classes/class-yoast-plugin-toggler.php';

	new Yoast_Plugin_Toggler();
}
