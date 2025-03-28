<?php
/**
 * The core plugin class.
 *
 * This is used to define internationalization, admin-specific hooks, and
 * public-facing site hooks.
 *
 * @since      1.0.0
 * @package    Open_Accessibility
 */

class Open_Accessibility {

	/**
	 * The loader that's responsible for maintaining and registering all hooks that power
	 * the plugin.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @var      Open_Accessibility_Loader    $loader    Maintains and registers all hooks for the plugin.
	 */
	protected $loader;

	/**
	 * Define the core functionality of the plugin.
	 *
	 * Set the plugin name and the plugin version that can be used throughout the plugin.
	 * Load the dependencies, define the locale, and set the hooks for the admin area and
	 * the public-facing side of the site.
	 *
	 * @since    1.0.0
	 */
	public function __construct() {
		$this->load_dependencies();
		$this->set_locale();
		$this->define_admin_hooks();
		$this->define_public_hooks();
	}

	/**
	 * Load the required dependencies for this plugin.
	 *
	 * Include the following files that make up the plugin:
	 *
	 * - Open_Accessibility_Loader. Orchestrates the hooks of the plugin.
	 * - Open_Accessibility_i18n. Defines internationalization functionality.
	 * - Open_Accessibility_Admin. Defines all hooks for the admin area.
	 * - Open_Accessibility_Public. Defines all hooks for the public side of the site.
	 * - Open_Accessibility_Widget. Defines the accessibility widget.
	 *
	 * Create an instance of the loader which will be used to register the hooks
	 * with WordPress.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function load_dependencies() {
		/**
		 * The class responsible for orchestrating the actions and filters of the
		 * core plugin.
		 */
		require_once OPEN_ACCESSIBILITY_PLUGIN_DIR . 'includes/class-open-accessibility-loader.php';

		/**
		 * The class responsible for defining internationalization functionality
		 * of the plugin.
		 */
		require_once OPEN_ACCESSIBILITY_PLUGIN_DIR . 'includes/class-open-accessibility-i18n.php';

		/**
		 * The class responsible for defining all actions that occur in the admin area.
		 */
		require_once OPEN_ACCESSIBILITY_PLUGIN_DIR . 'admin/class-open-accessibility-admin.php';

		/**
		 * The class responsible for defining all actions that occur in the public-facing
		 * side of the site.
		 */
		require_once OPEN_ACCESSIBILITY_PLUGIN_DIR . 'public/class-open-accessibility-public.php';

		/**
		 * The class responsible for defining the accessibility widget.
		 */
		require_once OPEN_ACCESSIBILITY_PLUGIN_DIR . 'includes/class-open-accessibility-widget.php';

		/**
		 * Core utilities and helpers
		 */
		require_once OPEN_ACCESSIBILITY_PLUGIN_DIR . 'includes/class-open-accessibility-utils.php';

		/**
		 * Database functionality
		 */
		require_once OPEN_ACCESSIBILITY_PLUGIN_DIR . 'includes/database/class-open-accessibility-db.php';

		/**
		 * AJAX handlers
		 */
		require_once OPEN_ACCESSIBILITY_PLUGIN_DIR . 'includes/ajax/class-open-accessibility-ajax.php';

		$this->loader = new Open_Accessibility_Loader();
	}

	/**
	 * Define the locale for this plugin for internationalization.
	 *
	 * Uses the Open_Accessibility_i18n class in order to set the domain and to register the hook
	 * with WordPress.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function set_locale() {
		$plugin_i18n = new Open_Accessibility_i18n();

		// Using 'init' hook as recommended for better compatibility with WordPress
		// This is only needed for WordPress versions prior to 4.6
		$this->loader->add_action( 'init', $plugin_i18n, 'load_plugin_textdomain' );
	}

	/**
	 * Register all of the hooks related to the admin area functionality
	 * of the plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function define_admin_hooks() {
		$plugin_admin = new Open_Accessibility_Admin();

		$this->loader->add_action( 'admin_enqueue_scripts', $plugin_admin, 'enqueue_styles' );
		$this->loader->add_action( 'admin_enqueue_scripts', $plugin_admin, 'enqueue_scripts' );
		$this->loader->add_action( 'admin_menu', $plugin_admin, 'add_options_page' );
		$this->loader->add_action( 'admin_init', $plugin_admin, 'register_settings' );
	}

	/**
	 * Register all of the hooks related to the public-facing functionality
	 * of the plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function define_public_hooks() {
		$plugin_public = new Open_Accessibility_Public();

		$this->loader->add_action( 'wp_enqueue_scripts', $plugin_public, 'enqueue_styles' );
		$this->loader->add_action( 'wp_enqueue_scripts', $plugin_public, 'enqueue_scripts' );
		$this->loader->add_action( 'wp_footer', $plugin_public, 'render_accessibility_widget' );
		$this->loader->add_action( 'wp_head', $plugin_public, 'add_skip_to_content_link' );
	}

	/**
	 * Run the loader to execute all of the hooks with WordPress.
	 *
	 * @since    1.0.0
	 */
	public function run() {
		$this->loader->run();
	}
}