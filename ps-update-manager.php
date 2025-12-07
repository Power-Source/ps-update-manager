<?php
/**
 * Plugin Name: PS Update Manager
 * Plugin URI: https://github.com/cp-psource
 * Description: Zentraler Update-Manager für alle PSource Plugins und Themes. Verwaltet Updates von GitHub oder eigenem Server.
 * Version: 1.0.0
 * Author: PSource
 * Author URI: https://github.com/cp-psource
 * Text Domain: ps-update-manager
 * Domain Path: /languages
 * Network: true
 */

// Direkten Zugriff verhindern
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Plugin-Konstanten definieren
define( 'PS_UPDATE_MANAGER_VERSION', '1.0.0' );
define( 'PS_UPDATE_MANAGER_FILE', __FILE__ );
define( 'PS_UPDATE_MANAGER_DIR', plugin_dir_path( __FILE__ ) );
define( 'PS_UPDATE_MANAGER_URL', plugin_dir_url( __FILE__ ) );
define( 'PS_UPDATE_MANAGER_BASENAME', plugin_basename( __FILE__ ) );

/**
 * Hauptklasse für den PS Update Manager
 */
class PS_Update_Manager {
	
	/**
	 * Singleton Instance
	 */
	private static $instance = null;
	
	/**
	 * Registrierte Produkte
	 */
	private $products = array();
	
	/**
	 * Singleton Instance abrufen
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}
	
	/**
	 * Konstruktor
	 */
	private function __construct() {
		$this->load_dependencies();
		$this->init_hooks();
	}
	
	/**
	 * Abhängigkeiten laden
	 */
	private function load_dependencies() {
		require_once PS_UPDATE_MANAGER_DIR . 'includes/class-product-registry.php';
		require_once PS_UPDATE_MANAGER_DIR . 'includes/class-update-checker.php';
		require_once PS_UPDATE_MANAGER_DIR . 'includes/class-github-api.php';
		require_once PS_UPDATE_MANAGER_DIR . 'includes/class-settings.php';
		require_once PS_UPDATE_MANAGER_DIR . 'includes/class-admin-dashboard.php';
	}
	
	/**
	 * Hooks initialisieren
	 */
	private function init_hooks() {
		add_action( 'plugins_loaded', array( $this, 'load_textdomain' ) );
		add_action( 'init', array( $this, 'init' ) );
		
		// Settings initialisieren
		PS_Update_Manager_Settings::get_instance();
		
		// Admin-Bereich
		if ( is_admin() ) {
			PS_Update_Manager_Admin_Dashboard::get_instance();
		}
		
		// Update-Checker initialisieren
		PS_Update_Manager_Update_Checker::get_instance();
	}
	
	/**
	 * Textdomain laden
	 */
	public function load_textdomain() {
		load_plugin_textdomain(
			'ps-update-manager',
			false,
			dirname( PS_UPDATE_MANAGER_BASENAME ) . '/languages'
		);
	}
	
	/**
	 * Initialisierung
	 */
	public function init() {
		// Action für andere Plugins zum Registrieren
		do_action( 'ps_update_manager_init', $this );
	}
	
	/**
	 * Produkt registrieren
	 * 
	 * @param array $args Produkt-Argumente
	 * @return bool Success
	 */
	public function register_product( $args ) {
		return PS_Update_Manager_Product_Registry::get_instance()->register( $args );
	}
	
	/**
	 * Alle registrierten Produkte abrufen
	 * 
	 * @return array
	 */
	public function get_products() {
		return PS_Update_Manager_Product_Registry::get_instance()->get_all();
	}
	
	/**
	 * Einzelnes Produkt abrufen
	 * 
	 * @param string $slug
	 * @return array|false
	 */
	public function get_product( $slug ) {
		return PS_Update_Manager_Product_Registry::get_instance()->get( $slug );
	}
}

/**
 * Hauptfunktion für globalen Zugriff
 */
function ps_update_manager() {
	return PS_Update_Manager::get_instance();
}

// Plugin initialisieren
ps_update_manager();

/**
 * Helper-Funktion für andere Plugins
 * Produkt beim Update Manager registrieren
 * 
 * @param array $args {
 *     Produkt-Argumente
 *     
 *     @type string $slug         Plugin/Theme Slug (erforderlich)
 *     @type string $name         Anzeigename (erforderlich)
 *     @type string $version      Aktuelle Version (erforderlich)
 *     @type string $type         'plugin' oder 'theme' (erforderlich)
 *     @type string $file         Haupt-Plugin-Datei (__FILE__) (erforderlich)
 *     @type string $github_repo  GitHub Repo (z.B. 'cp-psource/default-theme') (optional)
 *     @type string $update_url   Custom Update URL (optional)
 *     @type string $docs_url     Dokumentation URL (optional)
 *     @type string $support_url  Support URL (optional)
 *     @type string $changelog_url Changelog URL (optional)
 * }
 */
function ps_register_product( $args ) {
	if ( function_exists( 'ps_update_manager' ) ) {
		return ps_update_manager()->register_product( $args );
	}
	return false;
}
