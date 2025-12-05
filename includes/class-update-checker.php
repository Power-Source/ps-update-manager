<?php
/**
 * Update Checker Klasse
 * Integriert sich in WordPress Update-System
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PS_Update_Manager_Update_Checker {
	
	private static $instance = null;
	
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}
	
	private function __construct() {
		// Plugin Updates
		add_filter( 'pre_set_site_transient_update_plugins', array( $this, 'check_plugin_updates' ) );
		add_filter( 'plugins_api', array( $this, 'plugin_info' ), 10, 3 );
		
		// Theme Updates
		add_filter( 'pre_set_site_transient_update_themes', array( $this, 'check_theme_updates' ) );
		
		// Update-Links anpassen
		add_filter( 'plugin_row_meta', array( $this, 'plugin_row_meta' ), 10, 2 );
	}
	
	/**
	 * Plugin Updates prüfen
	 */
	public function check_plugin_updates( $transient ) {
		if ( empty( $transient->checked ) ) {
			return $transient;
		}
		
		$products = PS_Update_Manager_Product_Registry::get_instance()->get_by_type( 'plugin' );
		
		foreach ( $products as $product ) {
			// GitHub Repo vorhanden?
			if ( empty( $product['github_repo'] ) && empty( $product['update_url'] ) ) {
				continue;
			}
			
			// Update Info abrufen
			$update_info = $this->get_update_info( $product );
			
			if ( is_wp_error( $update_info ) || ! $update_info ) {
				continue;
			}
			
			// Neue Version verfügbar?
			if ( version_compare( $update_info['version'], $product['version'], '>' ) ) {
				$plugin_data = array(
					'slug'        => $product['slug'],
					'new_version' => $update_info['version'],
					'url'         => $product['docs_url'] ?? $update_info['html_url'] ?? '',
					'package'     => $update_info['download_url'],
					'tested'      => get_bloginfo( 'version' ),
				);
				
				$transient->response[ $product['basename'] ] = (object) $plugin_data;
			}
		}
		
		return $transient;
	}
	
	/**
	 * Theme Updates prüfen
	 */
	public function check_theme_updates( $transient ) {
		if ( empty( $transient->checked ) ) {
			return $transient;
		}
		
		$products = PS_Update_Manager_Product_Registry::get_instance()->get_by_type( 'theme' );
		
		foreach ( $products as $product ) {
			if ( empty( $product['github_repo'] ) && empty( $product['update_url'] ) ) {
				continue;
			}
			
			$update_info = $this->get_update_info( $product );
			
			if ( is_wp_error( $update_info ) || ! $update_info ) {
				continue;
			}
			
			if ( version_compare( $update_info['version'], $product['version'], '>' ) ) {
				$theme_data = array(
					'theme'       => $product['slug'],
					'new_version' => $update_info['version'],
					'url'         => $product['docs_url'] ?? $update_info['html_url'] ?? '',
					'package'     => $update_info['download_url'],
				);
				
				$transient->response[ $product['slug'] ] = $theme_data;
			}
		}
		
		return $transient;
	}
	
	/**
	 * Plugin-Info für Popup bereitstellen
	 */
	public function plugin_info( $result, $action, $args ) {
		if ( 'plugin_information' !== $action ) {
			return $result;
		}
		
		if ( empty( $args->slug ) ) {
			return $result;
		}
		
		// Ist es eines unserer Produkte?
		$product = PS_Update_Manager_Product_Registry::get_instance()->get( $args->slug );
		
		if ( ! $product || 'plugin' !== $product['type'] ) {
			return $result;
		}
		
		$update_info = $this->get_update_info( $product );
		
		if ( is_wp_error( $update_info ) || ! $update_info ) {
			return $result;
		}
		
		// Plugin-Info Objekt erstellen
		$plugin_info = new stdClass();
		$plugin_info->name = $product['name'];
		$plugin_info->slug = $product['slug'];
		$plugin_info->version = $update_info['version'];
		$plugin_info->author = '<a href="' . esc_url( $product['author_url'] ) . '">' . esc_html( $product['author'] ) . '</a>';
		$plugin_info->homepage = $product['docs_url'] ?? $update_info['html_url'] ?? '';
		$plugin_info->download_link = $update_info['download_url'];
		$plugin_info->sections = array(
			'description' => $product['description'] ?? __( 'Keine Beschreibung verfügbar.', 'ps-update-manager' ),
			'changelog'   => $this->format_changelog( $update_info['changelog'] ?? '' ),
		);
		$plugin_info->banners = array();
		
		// Weitere Links
		if ( ! empty( $product['support_url'] ) ) {
			$plugin_info->sections['support'] = sprintf(
				'<a href="%s" target="_blank">%s</a>',
				esc_url( $product['support_url'] ),
				__( 'Support & Diskussion', 'ps-update-manager' )
			);
		}
		
		return $plugin_info;
	}
	
	/**
	 * Update-Info von GitHub oder Custom URL abrufen
	 */
	private function get_update_info( $product ) {
		// Erst GitHub prüfen
		if ( ! empty( $product['github_repo'] ) ) {
			$github = PS_Update_Manager_GitHub_API::get_instance();
			return $github->get_latest_release( $product['github_repo'] );
		}
		
		// Dann Custom URL
		if ( ! empty( $product['update_url'] ) ) {
			return $this->get_custom_update_info( $product['update_url'] );
		}
		
		return false;
	}
	
	/**
	 * Update-Info von Custom URL abrufen
	 */
	private function get_custom_update_info( $url ) {
		$response = wp_remote_get( $url, array(
			'timeout' => 15,
		) );
		
		if ( is_wp_error( $response ) ) {
			return $response;
		}
		
		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );
		
		// Erwartetes Format:
		// {
		//   "version": "1.2.3",
		//   "download_url": "https://...",
		//   "changelog": "...",
		//   "html_url": "..."
		// }
		
		return $data;
	}
	
	/**
	 * Plugin Row Meta erweitern
	 */
	public function plugin_row_meta( $links, $file ) {
		$products = PS_Update_Manager_Product_Registry::get_instance()->get_by_type( 'plugin' );
		
		foreach ( $products as $product ) {
			if ( $product['basename'] === $file ) {
				// Dokumentation Link
				if ( ! empty( $product['docs_url'] ) ) {
					$links[] = sprintf(
						'<a href="%s" target="_blank">%s</a>',
						esc_url( $product['docs_url'] ),
						__( 'Dokumentation', 'ps-update-manager' )
					);
				}
				
				// Support Link
				if ( ! empty( $product['support_url'] ) ) {
					$links[] = sprintf(
						'<a href="%s" target="_blank">%s</a>',
						esc_url( $product['support_url'] ),
						__( 'Support', 'ps-update-manager' )
					);
				}
				
				// Changelog Link
				if ( ! empty( $product['changelog_url'] ) ) {
					$links[] = sprintf(
						'<a href="%s" target="_blank">%s</a>',
						esc_url( $product['changelog_url'] ),
						__( 'Changelog', 'ps-update-manager' )
					);
				}
				
				break;
			}
		}
		
		return $links;
	}
	
	/**
	 * Changelog formatieren
	 */
	private function format_changelog( $changelog ) {
		if ( empty( $changelog ) ) {
			return __( 'Kein Changelog verfügbar.', 'ps-update-manager' );
		}
		
		// Markdown zu HTML (simpel)
		require_once ABSPATH . WPINC . '/class-simplepie.php';
		$changelog = \SimplePie_Misc::absolutize_url( $changelog, '' );
		
		// Basic Markdown
		$changelog = preg_replace( '/^### (.+)$/m', '<h4>$1</h4>', $changelog );
		$changelog = preg_replace( '/^## (.+)$/m', '<h3>$1</h3>', $changelog );
		$changelog = preg_replace( '/^# (.+)$/m', '<h2>$1</h2>', $changelog );
		$changelog = preg_replace( '/^\* (.+)$/m', '<li>$1</li>', $changelog );
		$changelog = preg_replace( '/(<li>.*<\/li>)/s', '<ul>$1</ul>', $changelog );
		$changelog = nl2br( $changelog );
		
		return $changelog;
	}
	
	/**
	 * Update-Check manuell auslösen
	 */
	public function force_check() {
		delete_site_transient( 'update_plugins' );
		delete_site_transient( 'update_themes' );
		PS_Update_Manager_GitHub_API::get_instance()->clear_cache();
		
		wp_update_plugins();
		wp_update_themes();
	}
}
