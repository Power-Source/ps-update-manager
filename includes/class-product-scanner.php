<?php
/**
 * Product Scanner Klasse
 * Scannt Plugin und Theme Verzeichnisse nach PSource Produkten
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PS_Update_Manager_Product_Scanner {
	
	private static $instance = null;
	
	/**
	 * Offizielle PSource Produkte aus Manifest
	 */
	private $official_products = array();
	
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}
	
	private function __construct() {
		// Manifest laden
		$this->load_manifest();
		
		// Hook für periodisches Scannen (täglich)
		add_action( 'ps_update_manager_daily_scan', array( $this, 'scan_all' ) );
	}
	
	/**
	 * Offizielle Produkte aus Manifest laden
	 */
	private function load_manifest() {
		$manifest_file = PS_UPDATE_MANAGER_DIR . 'includes/products-manifest.php';
		
		if ( file_exists( $manifest_file ) ) {
			$this->official_products = include $manifest_file;
		}
		
		if ( ! is_array( $this->official_products ) ) {
			$this->official_products = array();
		}
	}
	
	/**
	 * Alle Plugins und Themes scannen
	 */
	public function scan_all() {
		$discovered = array();
		
		// Plugins scannen
		$plugins = $this->scan_plugins();
		if ( ! empty( $plugins ) ) {
			$discovered = array_merge( $discovered, $plugins );
		}
		
		// Themes scannen
		$themes = $this->scan_themes();
		if ( ! empty( $themes ) ) {
			$discovered = array_merge( $discovered, $themes );
		}
		
		// In Registry speichern
		$this->save_discovered_products( $discovered );
		
		// Transients aktualisieren
		set_transient( 'ps_last_scan_time', current_time( 'timestamp' ), WEEK_IN_SECONDS );
		set_transient( 'ps_discovered_products', $discovered, WEEK_IN_SECONDS );
		
		return $discovered;
	}
	
	/**
	 * Plugins scannen
	 */
	private function scan_plugins() {
		// WordPress Plugin-Funktionen laden
		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		
		$all_plugins = get_plugins();
		$psource_plugins = array();
		
		foreach ( $all_plugins as $plugin_file => $plugin_data ) {
			// Plugin-Slug aus Verzeichnis extrahieren
			$slug = dirname( $plugin_file );
			if ( '.' === $slug ) {
				$slug = basename( $plugin_file, '.php' );
			}
			
			// Prüfe ob Plugin im offiziellen Manifest ist
			if ( ! isset( $this->official_products[ $slug ] ) ) {
				continue;
			}
			
			$manifest = $this->official_products[ $slug ];
			
			// Sicherheitsprüfung: Muss vom Typ Plugin sein
			if ( 'plugin' !== $manifest['type'] ) {
				continue;
			}
			
			$psource_plugins[ $slug ] = array(
				'slug'          => $slug,
				'name'          => $manifest['name'],
				'version'       => $plugin_data['Version'],
				'type'          => 'plugin',
				'file'          => WP_PLUGIN_DIR . '/' . $plugin_file,
				'basename'      => $plugin_file,
				'github_repo'   => $manifest['repo'],
				'description'   => $manifest['description'],
				'author'        => $plugin_data['Author'] ?? 'PSource',
				'author_url'    => 'https://github.com/Power-Source',
				'docs_url'      => 'https://github.com/' . $manifest['repo'],
				'support_url'   => 'https://github.com/' . $manifest['repo'] . '/issues',
				'changelog_url' => 'https://github.com/' . $manifest['repo'] . '/releases',
				'icon'          => $manifest['icon'] ?? 'dashicons-admin-plugins',
				'category'      => $manifest['category'] ?? 'general',
				'is_active'     => is_plugin_active( $plugin_file ) || ( is_multisite() && is_plugin_active_for_network( $plugin_file ) ),
				'discovered'    => true,
				'official'      => true,
			);
		}
		
		return $psource_plugins;
	}
	
	/**
	 * Themes scannen
	 */
	private function scan_themes() {
		$all_themes = wp_get_themes();
		$psource_themes = array();
		
		foreach ( $all_themes as $theme_slug => $theme_obj ) {
			// Prüfe ob Theme im offiziellen Manifest ist
			if ( ! isset( $this->official_products[ $theme_slug ] ) ) {
				continue;
			}
			
			$manifest = $this->official_products[ $theme_slug ];
			
			// Sicherheitsprüfung: Muss vom Typ Theme sein
			if ( 'theme' !== $manifest['type'] ) {
				continue;
			}
			
			$current_theme = wp_get_theme();
			$is_active = ( $current_theme->get_stylesheet() === $theme_slug || $current_theme->get_template() === $theme_slug );
			
			$psource_themes[ $theme_slug ] = array(
				'slug'          => $theme_slug,
				'name'          => $manifest['name'],
				'version'       => $theme_obj->get( 'Version' ),
				'type'          => 'theme',
				'file'          => $theme_obj->get_stylesheet_directory() . '/style.css',
				'github_repo'   => $manifest['repo'],
				'description'   => $manifest['description'],
				'author'        => 'PSource',
				'author_url'    => 'https://github.com/Power-Source',
				'docs_url'      => 'https://github.com/' . $manifest['repo'],
				'support_url'   => 'https://github.com/' . $manifest['repo'] . '/issues',
				'changelog_url' => 'https://github.com/' . $manifest['repo'] . '/releases',
				'icon'          => $manifest['icon'] ?? 'dashicons-admin-appearance',
				'category'      => $manifest['category'] ?? 'theme',
				'is_active'     => $is_active,
				'discovered'    => true,
				'official'      => true,
			);
		}
		
		return $psource_themes;
	}
	
	/**
	 * Entdeckte Produkte in Registry speichern
	 */
	private function save_discovered_products( $discovered ) {
		if ( empty( $discovered ) ) {
			return;
		}
		
		$registry = PS_Update_Manager_Product_Registry::get_instance();
		
		foreach ( $discovered as $slug => $product ) {
			// Prüfe ob bereits registriert (von Plugin selbst)
			$existing = $registry->get( $slug );
			
			// Nur überschreiben wenn noch nicht existiert ODER wenn es auch discovered war
			if ( $existing && ! isset( $existing['discovered'] ) ) {
				// Plugin hat sich selbst registriert - nicht überschreiben
				continue;
			}
			
			// In Registry registrieren
			$registry->register( $product );
		}
	}
	
	/**
	 * Letzte Scan-Zeit abrufen
	 */
	public function get_last_scan_time() {
		return get_transient( 'ps_last_scan_time' );
	}
	
	/**
	 * Gecachte entdeckte Produkte abrufen
	 */
	public function get_discovered_products() {
		$cached = get_transient( 'ps_discovered_products' );
		
		if ( false === $cached ) {
			return $this->scan_all();
		}
		
		return $cached;
	}
	
	/**
	 * Offizielle Produkte aus Manifest abrufen
	 */
	public function get_official_products() {
		return $this->official_products;
	}
	
	/**
	 * Einzelnes offizielles Produkt abrufen
	 */
	public function get_official_product( $slug ) {
		return isset( $this->official_products[ $slug ] ) ? $this->official_products[ $slug ] : false;
	}
}
