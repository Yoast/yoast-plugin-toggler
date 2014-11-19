<?php

/*
Plugin Name: Yoast plugin toggler
Plugin URI: https://github.com/Yoast/yoast-plugin-toggler
Description: Toggle between the premium and free version of Yoast plugins
Version: 1.0
Author: Andy Meerwaldt for Yoast
Author URI: https://github.com/Yoast/yoast-plugin-toggler
*/

define( 'ToggleFile', __FILE__ );
define( 'ToggleDir', dirname( __FILE__ ) );

// Because there is only one class it could be required directly, but only when is_admin and user can activate plugins
if ( ! class_exists( 'Yoast_Plugin_Toggler', false ) ) {
	require( ToggleDir . '/classes/class-yoast-plugin-toggler.php' );

	new Yoast_Plugin_Toggler();

}