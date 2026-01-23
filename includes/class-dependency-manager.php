<?php
/**
 * Dependency Manager - Verwaltet kompatible und empfohlene Plugins
 * 
 * Dieser Manager bietet Funktionen zur Verwaltung von Plugin-Kompatibilität
 * und empfohlenen Plugins. Dies sind OPTIONALE Verbindungen, keine erforderlichen.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PS_Update_Manager_Dependency_Manager {

	private static $instance = null;
	private $scanner = null;
	private $registry = null;

	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		$this->scanner = PS_Update_Manager_Product_Scanner::get_instance();
		$this->registry = PS_Update_Manager_Product_Registry::get_instance();
	}

	/**
	 * Ruft alle kompatiblen Plugins für ein Produkt ab
	 * 
	 * @param string $slug Plugin/Theme Slug
	 * @return array Array mit kompatiblen Plugins
	 */
	public function get_compatible_plugins( $slug ) {
		$official_products = $this->scanner->get_official_products();
		
		if ( ! isset( $official_products[ $slug ] ) ) {
			return array();
		}

		$product = $official_products[ $slug ];
		$compatible = array();

		// Explizit definierte kompatible Plugins
		if ( ! empty( $product['compatible_with'] ) && is_array( $product['compatible_with'] ) ) {
			foreach ( $product['compatible_with'] as $compat_slug => $compat_info ) {
				if ( isset( $official_products[ $compat_slug ] ) ) {
					$compatible[ $compat_slug ] = array(
						'slug'        => $compat_slug,
						'name'        => $official_products[ $compat_slug ]['name'],
						'description' => $compat_info,
						'installed'   => $this->is_plugin_installed( $compat_slug ),
						'active'      => $this->is_plugin_active( $compat_slug ),
					);
				}
			}
		}

		return $compatible;
	}

	/**
	 * Ruft alle Plugins ab, die mit diesem Plugin kompatibel sind
	 * (Reverse lookup - welche Plugins nutzen DIESES Plugin)
	 * 
	 * @param string $slug Plugin/Theme Slug
	 * @return array Array mit Plugins die dieses Plugin nutzen können
	 */
	public function get_plugins_using_this( $slug ) {
		$official_products = $this->scanner->get_official_products();
		$plugins_using = array();

		// Durchsuche alle Produkte und finde die, die dieses Plugin haben
		foreach ( $official_products as $product_slug => $product ) {
			if ( $product_slug === $slug ) {
				continue; // Überspringen wir uns selbst
			}

			if ( ! empty( $product['compatible_with'] ) && is_array( $product['compatible_with'] ) ) {
				if ( isset( $product['compatible_with'][ $slug ] ) ) {
					$plugins_using[ $product_slug ] = array(
						'slug'        => $product_slug,
						'name'        => $product['name'],
						'description' => $product['compatible_with'][ $slug ],
						'installed'   => $this->is_plugin_installed( $product_slug ),
						'active'      => $this->is_plugin_active( $product_slug ),
					);
				}
			}
		}

		return $plugins_using;
	}

	/**
	 * Prüft ob ein Plugin installiert ist
	 * 
	 * @param string $slug Plugin Slug
	 * @return bool
	 */
	public function is_plugin_installed( $slug ) {
		$installed = $this->registry->get_all();
		return isset( $installed[ $slug ] );
	}

	/**
	 * Prüft ob ein Plugin aktiv ist
	 * 
	 * @param string $slug Plugin Slug
	 * @return bool
	 */
	public function is_plugin_active( $slug ) {
		$installed = $this->registry->get_all();
		return isset( $installed[ $slug ] ) && $installed[ $slug ]['is_active'];
	}

	/**
	 * Ruft Informationen über ein Plugin ab
	 * 
	 * @param string $slug Plugin Slug
	 * @return array|null Plugin-Daten oder null
	 */
	public function get_plugin_info( $slug ) {
		$official_products = $this->scanner->get_official_products();
		
		if ( ! isset( $official_products[ $slug ] ) ) {
			return null;
		}

		$product = $official_products[ $slug ];
		$installed = $this->registry->get_all();

		return array(
			'slug'         => $slug,
			'name'         => $product['name'],
			'description'  => $product['description'],
			'type'         => $product['type'],
			'repo'         => $product['repo'],
			'icon'         => $product['icon'] ?? 'dashicons-admin-plugins',
			'installed'    => isset( $installed[ $slug ] ),
			'active'       => isset( $installed[ $slug ] ) && $installed[ $slug ]['is_active'],
			'version'      => isset( $installed[ $slug ] ) ? $installed[ $slug ]['version'] : null,
			'compatible'   => $this->get_compatible_plugins( $slug ),
		);
	}

	/**
	 * Generiert einen HTML-String für kompatible Plugins (Pills-Format)
	 * 
	 * @param string $slug Plugin Slug
	 * @return string HTML für Pills
	 */
	public function render_compatibility_banner( $slug ) {
		$compatible = $this->get_compatible_plugins( $slug );
		
		if ( empty( $compatible ) ) {
			return ''; // Kein Banner wenn keine Kompatibilität definiert
		}

		$installed_active = array_filter( $compatible, function( $item ) {
			return $item['installed'] && $item['active'];
		});

		$installed_inactive = array_filter( $compatible, function( $item ) {
			return $item['installed'] && ! $item['active'];
		});

		$not_installed = array_filter( $compatible, function( $item ) {
			return ! $item['installed'];
		});

		$html = '<div class="ps-compatibility-pills">';

		// Aktive Plugins
		foreach ( $installed_active as $item ) {
			$html .= sprintf(
				'<span class="ps-compatibility-pill ps-compatibility-pill-active" title="%s"><span class="dashicons dashicons-yes"></span>%s</span>',
				esc_attr( $item['description'] ),
				esc_html( $item['name'] )
			);
		}

		// Installierte, aber inaktive Plugins
		foreach ( $installed_inactive as $item ) {
			$html .= sprintf(
				'<span class="ps-compatibility-pill ps-compatibility-pill-inactive" title="%s"><span class="dashicons dashicons-warning"></span>%s</span>',
				esc_attr( $item['description'] ),
				esc_html( $item['name'] )
			);
		}

		// Nicht installierte Plugins
		foreach ( $not_installed as $item ) {
			$html .= sprintf(
				'<span class="ps-compatibility-pill ps-compatibility-pill-available" title="%s"><span class="dashicons dashicons-lightbulb"></span>%s</span>',
				esc_attr( $item['description'] ),
				esc_html( $item['name'] )
			);
		}

		$html .= '</div>';

		return $html;
	}

	/**
	 * Generiert einen HTML-String für Plugins die DIESES Plugin erweitern (Infobox-Format)
	 * 
	 * @param string $slug Plugin Slug
	 * @return string HTML für Infobox
	 */
	public function render_extends_banner( $slug ) {
		$extends = $this->get_plugins_using_this( $slug );
		
		if ( empty( $extends ) ) {
			return ''; // Kein Banner wenn keine Plugins dieses erweitern
		}

		$installed_active = array_filter( $extends, function( $item ) {
			return $item['installed'] && $item['active'];
		});

		$installed_inactive = array_filter( $extends, function( $item ) {
			return $item['installed'] && ! $item['active'];
		});

		$not_installed = array_filter( $extends, function( $item ) {
			return ! $item['installed'];
		});

		$has_extends = ! empty( $installed_active ) || ! empty( $installed_inactive ) || ! empty( $not_installed );
		
		if ( ! $has_extends ) {
			return '';
		}

		$html = '<div class="ps-extends-box">';
		$html .= '<div class="ps-extends-header">';
		$html .= '<span class="dashicons dashicons-admin-tools"></span>';
		$html .= '<strong>' . esc_html__( 'Erweitere deine Möglichkeiten mit:', 'ps-update-manager' ) . '</strong>';
		$html .= '</div>';
		$html .= '<div class="ps-extends-list">';

		// Aktive Plugins
		foreach ( $installed_active as $item ) {
			$html .= sprintf(
				'<a href="#" class="ps-extends-item ps-extends-item-active ps-extends-link" data-slug="%s"><span class="dashicons dashicons-yes"></span><span class="ps-extends-name">%s</span><span class="ps-extends-desc">%s</span></a>',
				esc_attr( $item['slug'] ),
				esc_html( $item['name'] ),
				esc_html( $item['description'] )
			);
		}

		// Installierte, aber inaktive Plugins
		foreach ( $installed_inactive as $item ) {
			$html .= sprintf(
				'<a href="#" class="ps-extends-item ps-extends-item-inactive ps-extends-link" data-slug="%s"><span class="dashicons dashicons-warning"></span><span class="ps-extends-name">%s</span><span class="ps-extends-desc">%s</span></a>',
				esc_attr( $item['slug'] ),
				esc_html( $item['name'] ),
				esc_html( $item['description'] )
			);
		}

		// Nicht installierte Plugins
		foreach ( $not_installed as $item ) {
			$html .= sprintf(
				'<a href="#" class="ps-extends-item ps-extends-item-available ps-extends-link" data-slug="%s"><span class="dashicons dashicons-lightbulb"></span><span class="ps-extends-name">%s</span><span class="ps-extends-desc">%s</span></a>',
				esc_attr( $item['slug'] ),
				esc_html( $item['name'] ),
				esc_html( $item['description'] )
			);
		}

		$html .= '</div>';
		$html .= '</div>';

		return $html;
	}

	/**
	 * Gibt alle kompatiblen Plugins als JSON-Struktur zurück
	 * 
	 * @param string $slug Plugin Slug
	 * @return array JSON-ready Array
	 */
	public function get_compatible_plugins_json( $slug ) {
		$compatible = $this->get_compatible_plugins( $slug );
		$result = array();

		foreach ( $compatible as $compat ) {
			$result[] = array(
				'slug'        => $compat['slug'],
				'name'        => $compat['name'],
				'description' => $compat['description'],
				'installed'   => $compat['installed'],
				'active'      => $compat['active'],
			);
		}

		return $result;
	}
}
