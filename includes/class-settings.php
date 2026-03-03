<?php
/**
 * Settings Klasse für PS Update Manager
 * Verwaltet alle Plugin-Einstellungen
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PS_Update_Manager_Settings {
	
	private static $instance = null;
	
	/**
	 * Option-Key für Haupteinstellungen
	 */
	const OPTION_KEY = 'ps_update_manager_settings';
	
	/**
	 * Standardeinstellungen
	 */
	private $defaults = array(
		'allowed_roles'           => array( 'administrator' ),
		'catalog_roles'           => array( 'administrator' ),
		'check_updates_roles'     => array( 'administrator' ),
		'install_roles'           => array( 'administrator' ),
		'update_roles'            => array( 'administrator' ),
		'manage_plugins_roles'    => array( 'administrator' ),
		'manage_themes_roles'     => array( 'administrator' ),
		'network_tools_roles'     => array( 'administrator' ),
		'manage_tos_roles'        => array( 'administrator' ),
		'manage_settings_roles'   => array( 'administrator' ),
		'test_api_roles'          => array( 'administrator' ),
	);
	
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}
	
	private function __construct() {
		// Registriere die Einstellungen für die Settings API
		add_action( 'admin_init', array( $this, 'register_settings' ) );
	}
	
	/**
	 * Einstellungen registrieren
	 */
	public function register_settings() {
		// Nur im Netzwerk-Admin registrieren wenn Multisite aktiv ist
		if ( is_multisite() ) {
			register_setting( 'ps_update_manager_settings', self::OPTION_KEY, array(
				'sanitize_callback' => array( $this, 'sanitize_settings' ),
				'type'              => 'array',
			) );
		}
	}
	
	/**
	 * Einstellungen speichern
	 */
	public function save_settings( $settings ) {
		$settings = $this->sanitize_settings( $settings );
		
		if ( is_multisite() ) {
			update_site_option( self::OPTION_KEY, $settings );
		} else {
			update_option( self::OPTION_KEY, $settings );
		}
		
		return $settings;
	}
	
	/**
	 * Einstellungen abrufen
	 */
	public function get_settings() {
		if ( is_multisite() ) {
			$settings = get_site_option( self::OPTION_KEY, array() );
		} else {
			$settings = get_option( self::OPTION_KEY, array() );
		}
		
		// Mit Standardwerten zusammenführen
		return wp_parse_args( $settings, $this->defaults );
	}
	
	/**
	 * Einzelne Einstellung abrufen
	 */
	public function get_setting( $key, $default = null ) {
		$settings = $this->get_settings();
		
		if ( $default === null ) {
			$default = isset( $this->defaults[ $key ] ) ? $this->defaults[ $key ] : null;
		}
		
		return isset( $settings[ $key ] ) ? $settings[ $key ] : $default;
	}
	
	/**
	 * Einzelne Einstellung speichern
	 */
	public function update_setting( $key, $value ) {
		$settings = $this->get_settings();
		$settings[ $key ] = $value;
		return $this->save_settings( $settings );
	}
	
	/**
	 * Einstellungen validieren und säubern
	 */
	public function sanitize_settings( $settings ) {
		if ( ! is_array( $settings ) ) {
			$settings = array();
		}
		
		// Standardwerte setzen wenn nicht vorhanden
		if ( ! isset( $settings['allowed_roles'] ) ) {
			$settings['allowed_roles'] = $this->defaults['allowed_roles'];
		}
		
		// Sicherstellen dass allowed_roles ein Array ist
		if ( ! is_array( $settings['allowed_roles'] ) ) {
			$settings['allowed_roles'] = array();
		}
		
		// Nur valide Rollen-Slugs erlauben
		$valid_roles = array_keys( wp_roles()->roles );
		$settings['allowed_roles'] = array_filter( $settings['allowed_roles'], function( $role ) use ( $valid_roles ) {
			return in_array( $role, $valid_roles, true );
		} );
		
		return $settings;
	}
	
	/**
	 * Alle verfügbaren Rollen abrufen
	 */
	public function get_available_roles() {
		return wp_roles()->roles;
	}
	
	/**
	 * Prüfe ob ein Benutzer Zugriff auf das Dashboard hat
	 */
	public function user_can_access_dashboard( $user_id = null ) {
		if ( $user_id === null ) {
			$user_id = get_current_user_id();
		}
		
		if ( ! $user_id ) {
			return false;
		}
		
		// Im Netzwerk-Modus: Nur Netzwerk-Admin sieht das Dashboard
		if ( is_multisite() ) {
			if ( ! is_super_admin( $user_id ) ) {
				return false;
			}
		} else {
			// Im normalen Modus: nur Administratoren
			if ( ! user_can( $user_id, 'manage_options' ) ) {
				return false;
			}
		}
		
		// Zusätzliche Rollen-Prüfung
		$allowed_roles = $this->get_setting( 'allowed_roles' );
		
		if ( empty( $allowed_roles ) ) {
			return false;
		}
		
		$user = get_user_by( 'id', $user_id );
		if ( ! $user ) {
			return false;
		}
		
		// Prüfe ob Benutzer eine der erlaubten Rollen hat
		foreach ( $user->roles as $role ) {
			if ( in_array( $role, $allowed_roles, true ) ) {
				return true;
			}
		}
		
		return false;
	}

	/**
	 * Prüfe ob Benutzer Zugriff auf Dashboard hat (allgemeiner Zugriff)
	 */
	public function user_can_access( $capability = 'dashboard', $user_id = null ) {
		if ( $user_id === null ) {
			$user_id = get_current_user_id();
		}

		if ( ! $user_id ) {
			return false;
		}

		// Netzwerk-Admin hat immer Zugriff
		if ( is_multisite() && is_super_admin( $user_id ) ) {
			return true;
		}

		// Zugriff basierend auf Fähigkeit prüfen
		switch ( $capability ) {
			case 'dashboard':
				$roles = $this->get_setting( 'allowed_roles' );
				break;
			case 'view_catalog':
				$roles = $this->get_setting( 'catalog_roles' );
				break;
			case 'check_updates':
				$roles = $this->get_setting( 'check_updates_roles' );
				break;
			case 'install_products':
				$roles = $this->get_setting( 'install_roles' );
				break;
			case 'update_products':
				$roles = $this->get_setting( 'update_roles' );
				break;
			case 'manage_plugins':
				$roles = $this->get_setting( 'manage_plugins_roles' );
				break;
			case 'manage_themes':
				$roles = $this->get_setting( 'manage_themes_roles' );
				break;
			case 'manage_network_tools':
				$roles = $this->get_setting( 'network_tools_roles' );
				break;
			case 'manage_tos':
				$roles = $this->get_setting( 'manage_tos_roles' );
				break;
			case 'manage_settings':
				$roles = $this->get_setting( 'manage_settings_roles' );
				break;
			case 'test_api':
				$roles = $this->get_setting( 'test_api_roles' );
				break;
			default:
				return false;
		}

		if ( empty( $roles ) ) {
			return false;
		}

		$user = get_user_by( 'id', $user_id );
		if ( ! $user ) {
			return false;
		}

		// Prüfe ob Benutzer eine der erlaubten Rollen hat
		foreach ( $user->roles as $role ) {
			if ( in_array( $role, $roles, true ) ) {
				return true;
			}
		}

		return false;
	}
}
