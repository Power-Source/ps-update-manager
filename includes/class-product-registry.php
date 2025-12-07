<?php
/**
 * Produkt-Registry Klasse
 * Verwaltet alle registrierten Plugins und Themes
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Lade Admin-Funktionen wenn nicht verfügbar (z.B. bei AJAX)
if ( ! function_exists( 'is_plugin_active' ) ) {
	require_once ABSPATH . 'wp-admin/includes/plugin.php';
}

class PS_Update_Manager_Product_Registry {
	
	private static $instance = null;
	private $products = array();
	
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}
	
	private function __construct() {
		add_action( 'ps_update_manager_init', array( $this, 'init' ), 5 );
	}
	
	public function init() {
		// Produkte aus Option laden (für Persistenz)
		// Nutze Transient mit Cache für bessere Performance
		$cache_key = 'ps_update_manager_products_cache';
		$cached = get_transient( $cache_key );
		
		if ( false !== $cached ) {
			$this->products = $cached;
		} else {
			$saved_products = get_option( 'ps_update_manager_products', array() );
			if ( is_array( $saved_products ) ) {
				$this->products = $saved_products;
				// Cache für 12 Stunden
				set_transient( $cache_key, $saved_products, 12 * HOUR_IN_SECONDS );
			}
		}
	}
	
	/**
	 * Produkt registrieren
	 * 
	 * @param array $args Produkt-Argumente
	 * @return bool
	 */
	public function register( $args ) {
		// Validierung
		$required = array( 'slug', 'name', 'version', 'type', 'file' );
		foreach ( $required as $field ) {
			if ( empty( $args[ $field ] ) ) {
				return false;
			}
		}
		
		// Typ validieren
		if ( ! in_array( $args['type'], array( 'plugin', 'theme' ) ) ) {
			return false;
		}
		
		// Defaults setzen
		$product = wp_parse_args( $args, array(
			'slug'          => '',
			'name'          => '',
			'version'       => '',
			'type'          => 'plugin',
			'file'          => '',
			'github_repo'   => '',
			'update_url'    => '',
			'docs_url'      => '',
			'support_url'   => '',
			'changelog_url' => '',
			'description'   => '',
			'author'        => 'PSource',
			'author_url'    => 'https://github.com/cp-psource',
			'registered_at' => current_time( 'mysql' ),
		) );
		
		// Basename für Plugin generieren
		if ( 'plugin' === $product['type'] ) {
			$product['basename'] = plugin_basename( $product['file'] );
		}
		
		// Status prüfen (aktiv/inaktiv)
		$product['is_active'] = $this->check_active_status( $product );
		
		// In Registry speichern
		$this->products[ $product['slug'] ] = $product;
		
		// In Datenbank speichern
		$this->save();
		
		// Cache invalidieren
		delete_transient( 'ps_update_manager_products_cache' );
		
		return true;
	}
	
	/**
	 * Prüft ob Plugin/Theme aktiv ist
	 */
	private function check_active_status( $product ) {
		if ( 'plugin' === $product['type'] ) {
			// Prüfe Einzelsite-Aktivierung
			if ( is_plugin_active( $product['basename'] ) ) {
				return true;
			}
			
			// Für Multisite: Netzwerk-Aktivierung prüfen
			if ( is_multisite() ) {
				return is_plugin_active_for_network( $product['basename'] );
			}
			
			return false;
		} elseif ( 'theme' === $product['type'] ) {
			$current_theme = wp_get_theme();
			return ( $current_theme->get_stylesheet() === $product['slug'] || $current_theme->get_template() === $product['slug'] );
		}
		return false;
	}
	
	/**
	 * Alle Produkte abrufen
	 */
	public function get_all() {
		// Cache für Status aktualisieren (1 Minute)
		$cache_key = 'ps_update_manager_status_cache';
		$cached_time = get_transient( $cache_key );
		
		// Nur wenn wir nicht gerade gecacht haben - verhindert konstante DB-Zugriffe
		if ( ! $cached_time || ( current_time( 'timestamp' ) - $cached_time > 60 ) ) {
			// Status aktualisieren
			foreach ( $this->products as $slug => &$product ) {
				$product['is_active'] = $this->check_active_status( $product );
			}
			// Cache aktualisieren
			set_transient( $cache_key, current_time( 'timestamp' ), HOUR_IN_SECONDS );
		}
		
		return $this->products;
	}
	
	/**
	 * Einzelnes Produkt abrufen
	 */
	public function get( $slug ) {
		if ( isset( $this->products[ $slug ] ) ) {
			$product = $this->products[ $slug ];
			$product['is_active'] = $this->check_active_status( $product );
			return $product;
		}
		return false;
	}
	
	/**
	 * Produkte nach Typ filtern
	 */
	public function get_by_type( $type ) {
		return array_filter( $this->products, function( $product ) use ( $type ) {
			return $product['type'] === $type;
		});
	}
	
	/**
	 * In Datenbank speichern
	 */
	private function save() {
		update_option( 'ps_update_manager_products', $this->products, false );
	}
	
	/**
	 * Produkt entfernen (z.B. wenn Plugin gelöscht wurde)
	 */
	public function unregister( $slug ) {
		if ( isset( $this->products[ $slug ] ) ) {
			unset( $this->products[ $slug ] );
			$this->save();
			return true;
		}
		return false;
	}
}
