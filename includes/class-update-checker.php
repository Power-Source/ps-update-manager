<?php
/**
 * Update Checker Klasse
 * Integriert sich in ClassicPress Update-System
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
		add_filter( 'upgrader_source_selection', array( $this, 'normalize_upgrader_source' ), 10, 4 );
		
		// Theme Updates
		add_filter( 'pre_set_site_transient_update_themes', array( $this, 'check_theme_updates' ) );
		
		// Täglicher Sync soll WordPress-Update-Transients auch ohne Dashboard-Besuch befüllen.
		// Priority 20: Scanner läuft vorher auf Priority 10 und füllt die Registry zuerst.
		add_action( 'ps_update_manager_daily_scan', array( $this, 'force_check' ), 20 );
		
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
			
			$installed_version = '';
			if ( ! empty( $product['basename'] ) && isset( $transient->checked[ $product['basename'] ] ) ) {
				$installed_version = (string) $transient->checked[ $product['basename'] ];
			} elseif ( ! empty( $product['version'] ) ) {
				$installed_version = (string) $product['version'];
			}

			if ( empty( $installed_version ) || empty( $update_info['version'] ) || empty( $update_info['download_url'] ) ) {
				continue;
			}

			// Neue Version verfügbar?
			if ( version_compare( (string) $update_info['version'], $installed_version, '>' ) ) {
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
			
			$installed_version = '';
			if ( ! empty( $product['slug'] ) && isset( $transient->checked[ $product['slug'] ] ) ) {
				$installed_version = (string) $transient->checked[ $product['slug'] ];
			} elseif ( ! empty( $product['version'] ) ) {
				$installed_version = (string) $product['version'];
			}

			if ( empty( $installed_version ) || empty( $update_info['version'] ) || empty( $update_info['download_url'] ) ) {
				continue;
			}

			if ( version_compare( (string) $update_info['version'], $installed_version, '>' ) ) {
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
	 * Mit Caching für bessere Performance
	 */
	private function get_update_info( $product ) {
		// Cache Key generieren
		$cache_key = 'ps_update_info_' . md5( $product['slug'] );
		$cached = get_transient( $cache_key );
		
		if ( false !== $cached ) {
			return $cached;
		}
		
		$update_info = false;
		
		// Erst GitHub prüfen
		if ( ! empty( $product['github_repo'] ) ) {
			$github = PS_Update_Manager_GitHub_API::get_instance();
			$update_info = $github->get_latest_release( $product['github_repo'] );
		}
		
		// Dann Custom URL
		if ( ! $update_info && ! empty( $product['update_url'] ) ) {
			$update_info = $this->get_custom_update_info( $product['update_url'] );
		}
		
		// Cache Update-Info für 6 Stunden
		if ( $update_info && ! is_wp_error( $update_info ) ) {
			set_transient( $cache_key, $update_info, 6 * HOUR_IN_SECONDS );
		}
		
		return $update_info;
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

		if ( 200 !== wp_remote_retrieve_response_code( $response ) ) {
			return false;
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
		
		if ( ! is_array( $data ) ) {
			return false;
		}

		if ( empty( $data['version'] ) || empty( $data['download_url'] ) ) {
			return false;
		}

		return $data;
	}

	/**
	 * Plugin-spezifische Update-Info-Caches löschen
	 */
	private function clear_update_info_cache() {
		global $wpdb;
		$wpdb->query( "DELETE FROM $wpdb->options WHERE option_name LIKE '_transient_ps_update_info_%'" );
		$wpdb->query( "DELETE FROM $wpdb->options WHERE option_name LIKE '_transient_timeout_ps_update_info_%'" );
	}

	/**
	 * Normalisiert den temporär entpackten Quellordner beim Core-Upgrader.
	 *
	 * Manche Release-ZIPs enthalten einen Root-Ordner wie "ps-plugin-1.2.3".
	 * Ohne Umbenennung übernimmt der Upgrader diesen Namen 1:1 ins Plugin-/Theme-Verzeichnis.
	 */
	public function normalize_upgrader_source( $source, $remote_source, $upgrader, $hook_extra ) {
		if ( ! is_string( $source ) || ! is_dir( $source ) || ! is_array( $hook_extra ) ) {
			return $source;
		}

		$expected_dir = $this->resolve_expected_upgrader_directory( $hook_extra );
		if ( '' === $expected_dir ) {
			return $source;
		}

		$source_dir_name = basename( untrailingslashit( $source ) );
		if ( $source_dir_name === $expected_dir ) {
			return $source;
		}

		$parent_dir = trailingslashit( dirname( untrailingslashit( $source ) ) );
		$normalized_source = $parent_dir . $expected_dir;

		if ( file_exists( $normalized_source ) ) {
			$this->delete_directory_recursive( $normalized_source );
			if ( file_exists( $normalized_source ) ) {
				return $source;
			}
		}

		if ( @rename( $source, $normalized_source ) ) {
			return $normalized_source;
		}

		return $source;
	}

	/**
	 * Ermittelt den erwarteten Zielordner für den Upgrader-Lauf.
	 */
	private function resolve_expected_upgrader_directory( $hook_extra ) {
		if ( ! is_array( $hook_extra ) ) {
			return '';
		}

		if ( ! empty( $hook_extra['plugin'] ) ) {
			$plugin_basename = (string) $hook_extra['plugin'];
			$product = $this->find_product_by_basename( $plugin_basename );
			if ( $product && ! empty( $product['slug'] ) ) {
				return sanitize_file_name( (string) $product['slug'] );
			}

			$directory = dirname( $plugin_basename );
			if ( '.' === $directory ) {
				$directory = basename( $plugin_basename, '.php' );
			}

			return $this->normalize_plugin_directory_name( $directory );
		}

		if ( ! empty( $hook_extra['theme'] ) ) {
			$theme_slug = sanitize_file_name( (string) $hook_extra['theme'] );
			$product = PS_Update_Manager_Product_Registry::get_instance()->get( $theme_slug );
			if ( ! $product || ( isset( $product['type'] ) && 'theme' !== $product['type'] ) ) {
				return '';
			}

			return $theme_slug;
		}

		return '';
	}

	/**
	 * Entfernt Versions-Suffixe aus Plugin-Verzeichnisnamen.
	 */
	private function normalize_plugin_directory_name( $directory ) {
		$directory = sanitize_file_name( (string) $directory );
		if ( '' === $directory ) {
			return '';
		}

		$without_version = preg_replace( '/-(?:v)?\d+(?:\.\d+){1,3}(?:[-._]?[a-z0-9]+)?$/i', '', $directory );
		if ( is_string( $without_version ) && '' !== $without_version ) {
			$directory = $without_version;
		}

		$products = PS_Update_Manager_Product_Registry::get_instance()->get_by_type( 'plugin' );
		foreach ( $products as $product ) {
			$slug = sanitize_file_name( (string) ( $product['slug'] ?? '' ) );
			if ( '' === $slug ) {
				continue;
			}
			if ( $directory === $slug || 0 === strpos( $directory, $slug . '-' ) ) {
				return $slug;
			}
		}

		return $directory;
	}

	/**
	 * Sucht ein registriertes Produkt anhand seines Plugin-Basenames.
	 */
	private function find_product_by_basename( $plugin_basename ) {
		$products = PS_Update_Manager_Product_Registry::get_instance()->get_by_type( 'plugin' );
		$plugin_basename = (string) $plugin_basename;
		$lookup_dir = dirname( $plugin_basename );
		if ( '.' === $lookup_dir ) {
			$lookup_dir = basename( $plugin_basename, '.php' );
		}
		$lookup_dir = $this->normalize_plugin_directory_name( $lookup_dir );

		foreach ( $products as $product ) {
			if ( isset( $product['basename'] ) && $product['basename'] === $plugin_basename ) {
				return $product;
			}
			$product_slug = sanitize_file_name( (string) ( $product['slug'] ?? '' ) );
			if ( '' !== $product_slug && $product_slug === $lookup_dir ) {
				return $product;
			}
		}

		return false;
	}

	/**
	 * Verzeichnis rekursiv löschen.
	 */
	private function delete_directory_recursive( $dir ) {
		if ( ! file_exists( $dir ) ) {
			return;
		}
		if ( is_file( $dir ) || is_link( $dir ) ) {
			@unlink( $dir );
			return;
		}

		$items = scandir( $dir );
		if ( ! is_array( $items ) ) {
			return;
		}

		foreach ( $items as $item ) {
			if ( '.' === $item || '..' === $item ) {
				continue;
			}
			$this->delete_directory_recursive( $dir . DIRECTORY_SEPARATOR . $item );
		}

		@rmdir( $dir );
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
				
				// Docs Link
				if ( ! empty( $product['docs_url'] ) ) {
					$links[] = sprintf(
						'<a href="%s" target="_blank">%s</a>',
						esc_url( $product['docs_url'] ),
						__( 'Handbuch', 'ps-update-manager' )
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
		
		// Basic Markdown to HTML
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
		// Vor dem Update-Check immer frisch scannen, damit die Registry vollständige Basenames/Slugs enthält.
		PS_Update_Manager_Product_Scanner::get_instance()->scan_all();

		delete_site_transient( 'update_plugins' );
		delete_site_transient( 'update_themes' );
		$this->clear_update_info_cache();
		PS_Update_Manager_GitHub_API::get_instance()->clear_cache();
		
		wp_update_plugins();
		wp_update_themes();
		$this->store_update_snapshot();
	}

	/**
	 * Update-Snapshot abrufen
	 *
	 * Der Snapshot ist die zentrale Quelle für Dashboard- und Menü-Zähler.
	 */
	public function get_update_snapshot( $refresh_if_stale = true ) {
		$snapshot = get_transient( 'ps_update_manager_update_snapshot' );
		if ( ! is_array( $snapshot ) ) {
			$snapshot = array();
		}

		if ( $refresh_if_stale && $this->is_snapshot_stale( $snapshot ) ) {
			$this->force_check();
			$snapshot = get_transient( 'ps_update_manager_update_snapshot' );
			if ( ! is_array( $snapshot ) ) {
				$snapshot = array();
			}
		}

		if ( ! isset( $snapshot['count'] ) ) {
			$snapshot['count'] = $this->count_available_updates_from_wp();
		}

		if ( ! isset( $snapshot['checked_at'] ) ) {
			$snapshot['checked_at'] = 0;
		}

		return $snapshot;
	}

	/**
	 * Update-Zähler abrufen
	 */
	public function get_cached_update_count( $refresh_if_stale = true ) {
		$snapshot = $this->get_update_snapshot( $refresh_if_stale );
		return isset( $snapshot['count'] ) ? (int) $snapshot['count'] : 0;
	}

	/**
	 * Prüfen, ob der Snapshot veraltet ist.
	 */
	private function is_snapshot_stale( $snapshot ) {
		$checked_at = isset( $snapshot['checked_at'] ) ? (int) $snapshot['checked_at'] : 0;
		if ( ! $checked_at ) {
			return true;
		}

		return ( current_time( 'timestamp' ) - $checked_at ) > DAY_IN_SECONDS;
	}

	/**
	 * Snapshot aus den aktuellen WP-Update-Transients erzeugen.
	 */
	private function store_update_snapshot() {
		set_transient( 'ps_update_manager_update_snapshot', array(
			'count'      => $this->count_available_updates_from_wp(),
			'checked_at' => current_time( 'timestamp' ),
		), DAY_IN_SECONDS );
	}

	/**
	 * Verfügbare Updates aus den WordPress-Transients zählen.
	 */
	private function count_available_updates_from_wp() {
		$products = PS_Update_Manager_Product_Registry::get_instance()->get_all();
		$update_plugins = get_site_transient( 'update_plugins' );
		$update_themes  = get_site_transient( 'update_themes' );
		$count = 0;

		foreach ( $products as $product ) {
			if ( 'plugin' === $product['type'] ) {
				if ( ! empty( $product['basename'] ) && is_object( $update_plugins ) && isset( $update_plugins->response[ $product['basename'] ] ) ) {
					$count++;
				}
			} elseif ( 'theme' === $product['type'] ) {
				if ( ! empty( $product['slug'] ) && is_object( $update_themes ) && isset( $update_themes->response[ $product['slug'] ] ) ) {
					$count++;
				}
			}
		}

		return $count;
	}
}
