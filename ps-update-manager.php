<?php
/**
 * Plugin Name: PSOURCE Manager
 * Plugin URI: https://github.com/Power-Source/ps-update-manager/
 * Description: PSOURCE Management & Toolbox Hub - Updates, Tools & Netzwerk-Administration
 * Version: 1.2.6
 * Author: PSource
 * Author URI: https://github.com/Power-Source
 * Text Domain: ps-update-manager
 * Domain Path: /languages
 * PS Network: required
 */

// Direkten Zugriff verhindern
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Plugin-Konstanten definieren
define( 'PS_UPDATE_MANAGER_VERSION', '1.2.6' );
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
		require_once PS_UPDATE_MANAGER_DIR . 'includes/class-product-scanner.php';
		require_once PS_UPDATE_MANAGER_DIR . 'includes/class-update-checker.php';
		require_once PS_UPDATE_MANAGER_DIR . 'includes/class-github-api.php';
		require_once PS_UPDATE_MANAGER_DIR . 'includes/class-settings.php';
		require_once PS_UPDATE_MANAGER_DIR . 'includes/class-dependency-manager.php';
		require_once PS_UPDATE_MANAGER_DIR . 'includes/class-admin-dashboard.php';
		
		// Tools System (NEU)
		require_once PS_UPDATE_MANAGER_DIR . 'includes/tools/class-ps-tool.php';
		require_once PS_UPDATE_MANAGER_DIR . 'includes/class-tool-manager.php';
	}
	
	/**
	 * Hooks initialisieren
	 */
	private function init_hooks() {
		add_action( 'plugins_loaded', array( $this, 'load_textdomain' ) );
		add_action( 'init', array( $this, 'init' ) );
		
		// Nur im Admin-Bereich für bessere Performance
		if ( is_admin() ) {
			// Settings initialisieren
			PS_Update_Manager_Settings::get_instance();
			
			// Dashboard initialisieren
			PS_Update_Manager_Admin_Dashboard::get_instance();
			
			// Update-Checker initialisieren
			PS_Update_Manager_Update_Checker::get_instance();
			
			// Scanner initialisieren
			PS_Update_Manager_Product_Scanner::get_instance();
			
		// Tools Manager initialisieren (stellt sicher, dass admin-post Hooks registriert sind)
		PS_Manager_Tool_Manager::get_instance();
		}
		
		// Täglicher Scan-Hook
		if ( ! wp_next_scheduled( 'ps_update_manager_daily_scan' ) ) {
			wp_schedule_event( time(), 'daily', 'ps_update_manager_daily_scan' );
		}
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
		
		// Initialer Scan wenn noch nie gescannt wurde
		if ( is_admin() && ! get_transient( 'ps_last_scan_time' ) ) {
			PS_Update_Manager_Product_Scanner::get_instance()->scan_all();
		}
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
 * Plugin Deaktivierung - Aufräumen
 */
register_deactivation_hook( __FILE__, function() {
	// Cron-Job entfernen
	$timestamp = wp_next_scheduled( 'ps_update_manager_daily_scan' );
	if ( $timestamp ) {
		wp_unschedule_event( $timestamp, 'ps_update_manager_daily_scan' );
	}
	
	// Optional: Transients aufräumen (auskommentiert, da Daten erhalten bleiben sollen)
	// delete_transient( 'ps_last_scan_time' );
	// delete_transient( 'ps_discovered_products' );
	// delete_transient( 'ps_update_manager_products_cache' );
	// delete_transient( 'ps_update_manager_status_cache' );
} );

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
 *     @type string $github_repo  GitHub Repo (z.B. 'Power-Source/default-theme') (optional)
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
