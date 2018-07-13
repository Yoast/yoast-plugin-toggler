<?php

class Yoast_Plugin_Toggler {

	/** @var array The plugins to compare */
	private $plugins = array();

	/** @var string Regex with 2 groups to filter the plugins by name */
	private $grouped_name_filter = '/^Yoast SEO([ \d.]*$)|^Yoast SEO Premium([ \d.]*$)/';

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
		if ( ! $this->has_rights() ) {
			return;
		}

		// Load core plugin.php if not exists
		if ( ! function_exists( 'is_plugin_active' ) ||
			 ! function_exists( 'get_plugins' )
		) {
			include_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		// Apply filters to adapt the $this->grouped_name_filter property
		$this->grouped_name_filter = apply_filters( 'yoast_plugin_toggler_filter', $this->grouped_name_filter );

		// Find the plugins.
		$this->plugins = $this->get_filtered_plugin_groups( $this->grouped_name_filter );

		// Apply filters to extend the $this->plugins property
		$this->plugins = apply_filters( 'yoast_plugin_toggler_extend', $this->plugins );

		// First check if both versions of plugin do exist
		$this->check_plugin_versions_available();

		// Check which version is active
		$this->check_which_is_active();

		// Adding the hooks
		$this->add_hooks();
	}

	/**
	 * Adding the toggle fields to the page
	 */
	public function add_toggle() {
		$nonce = wp_create_nonce( 'yoast-plugin-toggle' );

		/** \WP_Admin_Bar $wp_admin_bar */
		global $wp_admin_bar;

		foreach ( $this->which_is_active as $label => $version ) {
			$menu_id = 'wpseo-plugin-toggler-' . sanitize_title( $label );
			$wp_admin_bar->add_menu( array(
				'id'    => $menu_id,
				'title' => $label === "" ? $version : $label . ': ' . $version,
				'href'  => '#',
			) );

			foreach ( $this->plugins[ $label ] as $switch_version => $data ) {
				if ( $switch_version !== $version ) {
					$wp_admin_bar->add_menu( array(
						'parent' => $menu_id,
						'id'     => 'wpseo-plugin-toggle-' . sanitize_title( $label ),
						'title'  => 'Switch to ' . $switch_version,
						'href'   => '#',
						'meta'   => array( 'onclick' => 'Yoast_Plugin_Toggler.toggle_plugin( "' . $label . '", "' . $nonce . '")' )
					) );
				}
			}
		}
	}

	/**
	 * Adding the assets to the page
	 *
	 */
	public function add_assets() {
		// JS file
		wp_enqueue_script( 'yoast-toggle-script', plugin_dir_url( YOAST_PLUGIN_TOGGLE_FILE ) . 'assets/js/yoast-toggle.js' );
	}

	/**
	 * Toggle between the versions
	 *
	 * The active version will be deactivated. The inactive version will be printed as JSON and will be used to active
	 * this version in another AJAX request
	 *
	 */
	public function ajax_toggle_plugin_version() {

		$response = array();

		// If nonce is valid
		if ( $this->verify_nonce() ) {
			$current_plugin        = filter_input( INPUT_GET, 'plugin' );
			$version_to_activate   = $this->get_inactive_version( $current_plugin );
			$version_to_deactivate = $this->which_is_active[ $current_plugin ];

			// First deactivate current version
			$this->deactivate_plugin_version( $current_plugin, $version_to_deactivate );
			$this->activate_plugin_version( $current_plugin, $version_to_activate );

			$response = array(
				'activated_version' => $version_to_activate
			);
		}

		echo json_encode( $response );
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
	 * Check the plugins directory and retrieve plugins that match the filter.
	 *
	 * Example:
	 * $grouped_name_filter = '/^Yoast SEO([ \d.]*$)|^Yoast SEO Premium([ \d.]*$)/'
	 * $plugins = array(
	 * 	'' => array(
	 * 		'Yoast SEO'         => 'wordpress-seo/wp-seo.php',
	 * 		'Yoast SEO Premium' => 'wordpress-seo-premium/wp-seo-premium.php',
	 * 	),
	 * );
	 *
	 * @param string $grouped_name_filter Regex to filter on the plugin data name.
	 *                                    This has to include 2 groups to match the keys.
	 *
	 * @return array The plugins grouped by matching filter 1 & 2.
	 */
	function get_filtered_plugin_groups( $grouped_name_filter ) {
		$all_plugins = get_plugins();
		$plugins = array();

		foreach ( $all_plugins as $file => $data ) {
			$matches = array();
			$name = $data[ 'Name' ];
			if ( preg_match( $grouped_name_filter, $name, $matches ) ) {
				$group = '';
				if ( isset( $matches[2] ) && $matches[2] !== "" ) {
					$group = $matches[2];
				} elseif ( isset( $matches[1] ) && $matches[1] !== "" ) {
					$group = $matches[1];
				}
				if ( ! isset( $plugins[ $group ] ) ) {
					$plugins[ $group ] = array();
				}
				$plugins[ $group ][ $name ] = $file;
			}
		}

		return $plugins;
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
					unset( $this->plugins[ $plugin ] );
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
		$this->which_is_active[ $plugin ] = $version;
	}


	/**
	 * Adding the hooks
	 *
	 */
	private function add_hooks() {
		// Setting AJAX-request for toggle between version
		add_action( 'wp_ajax_toggle_version', array( $this, 'ajax_toggle_plugin_version' ) );

		// Adding assets
		add_action( 'admin_init', array( $this, 'add_assets' ) );

		add_action( 'admin_bar_menu', array( $this, 'add_toggle' ), 100 );
	}

	/**
	 * Activate the $version for given $plugin
	 *
	 * @param string $plugin
	 * @param string $version
	 */
	private function activate_plugin_version( $plugin, $version ) {
		$plugin_to_enable = $this->plugins[ $plugin ][ $version ];

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
		foreach ( $this->plugins[ $plugin ] AS $version => $plugin_path ) {
			if ( $this->which_is_active[ $plugin ] !== $version ) {
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
		$plugin_to_disable = $this->plugins[ $plugin ][ $version ];

		// Disable plugin
		deactivate_plugins( plugin_basename( $plugin_to_disable ), true );
	}

	/**
	 * Verify the set nonce with the posted one
	 *
	 * @return bool
	 */
	private function verify_nonce() {
		// Get the nonce value
		$ajax_nonce = filter_input( INPUT_GET, 'ajax_nonce' );

		// If nonce is valid return true
		if ( wp_verify_nonce( $ajax_nonce, 'yoast-plugin-toggle' ) ) {
			return true;
		}
	}

}
