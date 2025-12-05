<?php
/**
 * PS Update Manager - Integration
 * 
 * Diese Datei in dein Plugin/Theme einfügen und anpassen.
 * Sie registriert dein Produkt beim PS Update Manager.
 * 
 * VERWENDUNG:
 * 1. Diese Datei in dein Plugin kopieren (z.B. in einen 'psource' Ordner)
 * 2. In deiner Haupt-Plugin-Datei einbinden:
 *    require_once plugin_dir_path( __FILE__ ) . 'psource/ps-integration.php';
 * 3. Die Produkt-Daten unten anpassen
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * PS Update Manager Integration
 */
class PS_Product_Integration {
	
	private $product_data = array();
	private $main_file;
	
	/**
	 * Konstruktor
	 * 
	 * @param string $main_file Pfad zur Haupt-Plugin-Datei (__FILE__ aus der Hauptdatei)
	 * @param array  $product_data Produkt-Informationen
	 */
	public function __construct( $main_file, $product_data ) {
		$this->main_file = $main_file;
		$this->product_data = $product_data;
		
		// Hooks registrieren
		add_action( 'plugins_loaded', array( $this, 'check_and_register' ), 5 );
		add_action( 'admin_notices', array( $this, 'admin_notice' ) );
	}
	
	/**
	 * Prüfen ob Update Manager aktiv ist und Produkt registrieren
	 */
	public function check_and_register() {
		// Update Manager verfügbar?
		if ( ! function_exists( 'ps_register_product' ) ) {
			return;
		}
		
		// Produkt registrieren
		ps_register_product( array_merge(
			$this->product_data,
			array( 'file' => $this->main_file )
		) );
	}
	
	/**
	 * Admin Notice wenn Update Manager nicht installiert ist
	 */
	public function admin_notice() {
		// Nur für Admins
		if ( ! current_user_can( 'install_plugins' ) ) {
			return;
		}
		
		// Update Manager bereits aktiv?
		if ( function_exists( 'ps_register_product' ) ) {
			return;
		}
		
		// Notice nur auf Plugin-Seiten
		$screen = get_current_screen();
		if ( ! $screen || ! in_array( $screen->id, array( 'plugins', 'plugins-network' ) ) ) {
			return;
		}
		
		// Ist Update Manager installiert aber nicht aktiv?
		$plugin_file = 'ps-update-manager/ps-update-manager.php';
		$installed_plugins = get_plugins();
		
		if ( isset( $installed_plugins[ $plugin_file ] ) ) {
			// Installiert aber nicht aktiv
			$activate_url = wp_nonce_url(
				add_query_arg(
					array(
						'action' => 'activate',
						'plugin' => $plugin_file,
					),
					admin_url( 'plugins.php' )
				),
				'activate-plugin_' . $plugin_file
			);
			
			printf(
				'<div class="notice notice-warning is-dismissible"><p><strong>%s</strong> %s <a href="%s" class="button button-primary">%s</a></p></div>',
				esc_html( $this->product_data['name'] ),
				esc_html__( 'empfiehlt den PS Update Manager für Updates und Support.', 'ps-update-manager' ),
				esc_url( $activate_url ),
				esc_html__( 'Jetzt aktivieren', 'ps-update-manager' )
			);
		} else {
			// Nicht installiert
			$install_url = wp_nonce_url(
				add_query_arg(
					array(
						'action' => 'install-plugin',
						'plugin' => 'ps-update-manager',
					),
					admin_url( 'update.php' )
				),
				'install-plugin_ps-update-manager'
			);
			
			printf(
				'<div class="notice notice-warning is-dismissible"><p><strong>%s</strong> %s <a href="%s" target="_blank">%s</a></p></div>',
				esc_html( $this->product_data['name'] ),
				esc_html__( 'empfiehlt den PS Update Manager für Updates und Support.', 'ps-update-manager' ),
				'https://github.com/cp-psource/ps-update-manager',
				esc_html__( 'Mehr erfahren', 'ps-update-manager' )
			);
		}
	}
}

/**
 * BEISPIEL-VERWENDUNG:
 * 
 * In deiner Haupt-Plugin-Datei (z.B. default-theme.php):
 */

/*
// PS Update Manager Integration laden
require_once plugin_dir_path( __FILE__ ) . 'psource/ps-integration.php';

// Produkt registrieren
new PS_Product_Integration( __FILE__, array(
	'slug'          => 'default-theme',                    // Plugin/Theme Slug
	'name'          => 'Default Theme',                    // Anzeigename
	'version'       => '1.0.0',                            // Aktuelle Version
	'type'          => 'plugin',                           // 'plugin' oder 'theme'
	'github_repo'   => 'cp-psource/default-theme',         // GitHub Repo (owner/repo)
	'docs_url'      => 'https://deine-docs.de',            // Optional: Dokumentation
	'support_url'   => 'https://github.com/cp-psource/default-theme/issues',  // Optional: Support
	'changelog_url' => 'https://github.com/cp-psource/default-theme/releases', // Optional: Changelog
	'description'   => 'Dein cooles Plugin beschreibung',  // Optional: Beschreibung
) );
*/

/**
 * ALTERNATIVE: Direkter Check ohne Klasse (noch minimalistischer)
 */

/*
// Nach dem Plugin-Header in deiner Hauptdatei:
add_action( 'plugins_loaded', function() {
	if ( function_exists( 'ps_register_product' ) ) {
		ps_register_product( array(
			'slug'        => 'default-theme',
			'name'        => 'Default Theme',
			'version'     => '1.0.0',
			'type'        => 'plugin',
			'file'        => __FILE__,
			'github_repo' => 'cp-psource/default-theme',
			'docs_url'    => 'https://deine-docs.de',
		) );
	}
}, 5 );

// Admin Notice wenn Update Manager fehlt
add_action( 'admin_notices', function() {
	if ( ! function_exists( 'ps_register_product' ) && current_user_can( 'install_plugins' ) ) {
		echo '<div class="notice notice-info"><p>';
		echo '<strong>Default Theme:</strong> ';
		echo 'Installiere den <a href="https://github.com/cp-psource/ps-update-manager" target="_blank">PS Update Manager</a> für automatische Updates.';
		echo '</p></div>';
	}
});
*/
