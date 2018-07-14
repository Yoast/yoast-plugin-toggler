<?php

class Yoast_Plugin_Toggler {

	/** @var array The plugins to compare */
	private $plugins = array();

	/** @var string Regex with groups to filter the plugins by name */
	private $grouped_name_filter = '/^((Yoast SEO)|(Yoast SEO) Premium)[ \d.]*$/';

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

		// Add a menu for each group.
		foreach ( $this->plugins as $group => $plugins ) {
			$active_plugin = $this->get_active_plugin( $group );
			$menu_id       = 'wpseo-plugin-toggler-' . sanitize_title( $group );
			$menu_title    = $active_plugin;

			// Menu title fallbacks: active plugin > group > first plugin.
			if ( $menu_title === '' ) {
				$menu_title = $group;
				if ( $menu_title === '' ) {
					reset( $plugins );
					$menu_title = key( $plugins );
				}
			}

			$wp_admin_bar->add_menu( array(
				'parent' => false,
				'id'     => $menu_id,
				'title'  => $menu_title,
				'href'   => '#',
			) );

			// Add a node for each plugin.
			foreach ( $plugins as $plugin => $plugin_path ) {
				if ( $plugin !== $active_plugin ) {
					$wp_admin_bar->add_node( array(
						'parent' => $menu_id,
						'id'     => 'wpseo-plugin-toggle-' . sanitize_title( $plugin ),
						'title'  => 'Switch to ' . $plugin,
						'href'   => '#',
						'meta'   => array(
							'onclick' => sprintf(
								'Yoast_Plugin_Toggler.toggle_plugin( "%1$s", "%2$s", "%3$s" )',
								$group,
								$plugin,
								$nonce
							)
						)
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
	 * Toggle between the versions.
	 */
	public function ajax_toggle_plugin_version() {

		$response = array();

		// If nonce is valid
		if ( $this->verify_nonce() ) {
			$group          = filter_input( INPUT_GET, 'group' );
			$plugin         = filter_input( INPUT_GET, 'plugin' );
			$current_plugin = $this->which_is_active[ $group ];

			// First deactivate the current plugin.
			$this->deactivate_plugin( $group, $current_plugin );
			$this->activate_plugin( $group, $plugin );

			$response = array(
				'activated_version' => $plugin
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
	 * $grouped_name_filter = '/^((Yoast SEO)|(Yoast SEO) Premium)[ \d.]*$/'
	 * $plugins = array(
	 * 	'Yoast SEO' => array(
	 * 		'Yoast SEO'             => 'wordpress-seo/wp-seo.php',
	 * 		'Yoast SEO 7.8'         => 'wordpress-seo 7.8/wp-seo.php',
	 * 		'Yoast SEO Premium'     => 'wordpress-seo-premium/wp-seo-premium.php',
	 * 		'Yoast SEO Premium 7.8' => 'wordpress-seo-premium 7.8/wp-seo-premium.php',
	 * 	),
	 * );
	 *
	 * @param string $grouped_name_filter Regex to filter on the plugin data name.
	 *
	 * @return array The plugins grouped by the regex matches.
	 */
	private function get_filtered_plugin_groups( $grouped_name_filter ) {
		// Use WordPress to get all the plugins with their data.
		$all_plugins = get_plugins();
		$plugins     = array();

		foreach ( $all_plugins as $file => $data ) {
			$matches = array();
			$name    = $data[ 'Name' ];

			// Save the plugin under a group.
			if ( preg_match( $grouped_name_filter, $name, $matches ) ) {
				$matches = array_reverse( $matches );
				$group   = '';

				foreach ( $matches as $match ) {
					if ( $match !== '' ) {
						$group = $match;
						break;
					}
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
	 * Check if the plugins really do exists.
	 *
	 * This is to prevent toggling between versions resulting in errors
	 *
	 */
	private function check_plugin_versions_available() {
		foreach ( $this->plugins AS $group => $plugins ) {
			foreach ( $plugins AS $plugin => $plugin_path ) {
				$full_plugin_path = ABSPATH . 'wp-content/plugins/' . plugin_basename( $plugin_path );

				// Remove the plugin from the group if it does not exist.
				if ( ! file_exists( $full_plugin_path ) ) {
					unset( $this->plugins[ $group ][ $plugin ] );
				}
			}

			// Remove the group entirely if there is less than 2 plugins in it.
			if ( count( $this->plugins[ $group ] ) < 2 ) {
				unset( $this->plugins[ $group ] );
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
	 * Retrieves the active plugin of a group.
	 *
	 * @param string $group
	 *
	 * @return string The plugin name or an empty string.
	 */
	private function get_active_plugin( $group ) {
		if ( array_key_exists( $group, $this->which_is_active ) ) {
			return $this->which_is_active[ $group ];
		}
		return '';
	}

	/**
	 * Add current active plugin to $this->which_is_active
	 *
	 * @param string $group
	 * @param string $plugin
	 */
	private function add_active_plugin( $group, $plugin ) {
		$this->which_is_active[ $group ] = $plugin;
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
	 * Activate the $plugin in the $group
	 *
	 * @param string $group
	 * @param string $plugin
	 */
	private function activate_plugin( $group, $plugin ) {
		$plugin_path = $this->plugins[ $group ][ $plugin ];

		// Activate plugin
		activate_plugin( plugin_basename( $plugin_path ), null, false, true );
	}

	/**
	 * Deactivate the $plugin in the $group
	 *
	 * This will be performed in silent mode
	 *
	 * @param string $group
	 * @param string $plugin [free or premium]
	 */
	private function deactivate_plugin( $group, $plugin ) {
		$plugin_path = $this->plugins[ $group ][ $plugin ];

		// Disable plugin
		deactivate_plugins( plugin_basename( $plugin_path ), true );
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
