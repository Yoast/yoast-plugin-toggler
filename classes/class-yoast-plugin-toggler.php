<?php

class Yoast_Plugin_Toggler {

	/**
	 * The plugins to compare
	 *
	 * @var array
	 */
	private $plugins = array(

		'wordpress seo'    => array(
			'free'    => 'wordpress-seo/wp-seo.php',
			'premium' => 'wordpress-seo-premium/wp-seo-premium.php'

		),
		'hakkie takkie' => array(
			'free'    => 'andy/andy.php',
			'premium' => 'hakkietakkie/blaat.php'

		),
		'google analytics' => array(
			'free'    => 'google-analytics-for-wordpress/googleanalytics.php',
			'premium' => 'google-analytics-premium/googleanalytics-premium.php'

		)

	);

	private $which_is_active = array();

	/**
	 * Constructing the object and set init hook
	 */
	public function __construct() {
		add_action( 'plugins_loaded', array( $this, 'init' ) );
	}

	/**
	 * Initialize plugin
	 *
	 * Check for rights and look which plugin is active.
	 * Also adding hooks
	 *
	 */
	public function init() {
		if ( $this->has_rights() ) {

			// Load core plugin.php if not exists
			if ( ! function_exists( 'is_plugin_active' ) ) {
				include_once( ABSPATH . 'wp-admin/includes/plugin.php' );
			}

			// First check if both versions of plugin do exist
			$this->check_plugin_versions_available();

			// Check which version is active
			$this->check_which_is_active();

			// Adding the hooks
			$this->add_hooks();
		}
	}

	/**
	 * Adding the toggle fields to the page
	 */
	public function add_toggle() {

		echo "<ul class='Yoast-Toggle' >";
		foreach ( $this->which_is_active AS $plugin => $current_active ) {
			echo "<li><label>{$plugin}</label> <a href='#' data-plugin='{$plugin}'>{$current_active}</a></li>";
		}

		echo "</ul>";
	}

	/**
	 * Adding the assets to the page
	 *
	 */
	public function add_assets() {
		// JS file
		wp_enqueue_script( 'yoast-toggle-script', plugin_dir_url( ToggleFile ) . 'assets/js/yoast-toggle.js' );

		// CSS file
		wp_enqueue_style( 'yoast-toggle-style', plugin_dir_url( ToggleFile ) . 'assets/css/yoast-toggle.css' );
	}

	/**
	 * Toggle between the versions
	 *
	 * The active version will be deactivated. The inactive version will be printed as JSON and will be used to active
	 * this version in another AJAX request
	 *
	 */
	public function ajax_toggle_plugin_version() {
		$current_plugin        = filter_input( INPUT_GET, 'plugin' );
		$version_to_activate   = $this->get_inactive_version( $current_plugin );
		$version_to_deactivate = $this->which_is_active[ $current_plugin ];

		// First deactivate current version
		$this->deactivate_plugin_version( $current_plugin, $version_to_deactivate );

		$response = array(
			'activated_version' => $version_to_activate
		);

		echo json_encode( $response );
		die();
	}

	/**
	 * This ajax version has to be done, because of preventing conflicts between plugins.
	 * When deactivating plugin it's code is still active, because of a refresh that isn't done.
	 * To solve this issue, we will do another request, just to activate the plugin
	 *
	 */
	public function ajax_activate_toggled_version() {
		$current_plugin      = filter_input( INPUT_GET, 'plugin' );
		$version_to_activate = filter_input( INPUT_GET, 'activated_version' );

		$this->activate_plugin_version( $current_plugin, $version_to_activate );

		echo 1;
		die();
	}


	/**
	 * Check if there are enough rights to display the toggle
	 *
	 * If current page is adminpage and current user can activatie plugins return true
	 *
	 * @return bool
	 */
	private function has_rights() {
		return ( is_admin() && current_user_can( 'activate_plugins' ) );
	}

	/**
	 * Check if both version of each plugin really do exists.
	 *
	 * This is to prevent toggling between versions resulting in errors
	 *
	 */
	private function check_plugin_versions_available() {
		foreach ( $this->plugins AS $plugin => $versions ) {
			foreach ( $versions AS $version => $plugin_path ) {
				$full_plugin_path = ABSPATH . 'wp-content/plugins/' . plugin_basename( $plugin_path );

				if ( ! file_exists( $full_plugin_path ) ) {
					unset($this->plugins[$plugin]);
					break;
				}
			}

		}
	}

	/**
	 * Loop through $this->plugin and check which version is active
	 *
	 */
	private function check_which_is_active() {

		foreach ( $this->plugins AS $plugin => $versions ) {

			foreach ( $versions AS $version => $plugin_path ) {
				if ( is_plugin_active( $plugin_path ) ) {
					$this->add_active_plugin( $plugin, $version );
				}
			}
		}
	}

	/**
	 * Add current active plugin to $this->which_is_active
	 *
	 * @param string $plugin
	 * @param string $version
	 */
	private function add_active_plugin( $plugin, $version ) {
		$this->which_is_active[$plugin] = $version;
	}


	/**
	 * Adding the hooks
	 *
	 */
	private function add_hooks() {
		// Setting AJAX-request for toggle between version
		add_action( 'wp_ajax_toggle_version', array( $this, 'ajax_toggle_plugin_version' ) );

		add_action( 'wp_ajax_activate_toggled_version', array( $this, 'ajax_activate_toggled_version' ) );

		// Adding assets
		add_action( 'admin_init', array( $this, 'add_assets' ) );

		// Adding toggler to DOM
		add_action( 'admin_footer', array( $this, 'add_toggle' ) );
	}

	/**
	 * Activate the $version for given $plugin
	 *
	 * @param string $plugin
	 * @param string $version
	 */
	private function activate_plugin_version( $plugin, $version ) {
		$plugin_to_enable = $this->plugins[$plugin][$version];

		// Activate plugin
		activate_plugin( plugin_basename( $plugin_to_enable ), null, false, true );
	}

	/**
	 * Getting the version of given $plugin which is inactive
	 *
	 * @param string $plugin
	 *
	 * @return int|string
	 */
	private function get_inactive_version( $plugin ) {
		foreach ( $this->plugins[$plugin] AS $version => $plugin_path ) {
			if ( $this->which_is_active[$plugin] !== $version ) {
				return $version;
			}
		}
	}

	/**
	 * Deactivate the $version for given $plugin
	 *
	 * This will be performed in silent mode
	 *
	 * @param string $plugin
	 * @param string $version [free or premium]
	 */
	private function deactivate_plugin_version( $plugin, $version ) {
		$plugin_to_disable = $this->plugins[$plugin][$version];

		// Disable plugin
		deactivate_plugins( plugin_basename( $plugin_to_disable ), true );
	}

}
