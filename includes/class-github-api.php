<?php
/**
 * GitHub API Klasse
 * Kommuniziert mit GitHub API für Update-Checks
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PS_Update_Manager_GitHub_API {
	
	private static $instance = null;
	
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}
	
	/**
	 * GitHub Release Info abrufen
	 * 
	 * @param string $repo Format: 'owner/repo'
	 * @return array|WP_Error
	 */
	public function get_latest_release( $repo ) {
		// Transient prüfen (Cache für 12 Stunden) - ABER: nur bei Success, nicht bei Errors
		$transient_key = 'ps_github_release_' . md5( $repo );
		$cached = get_transient( $transient_key );
		
		// Gültiger Cache: Array mit 'version' Key
		if ( false !== $cached && is_array( $cached ) && isset( $cached['version'] ) ) {
			return $cached;
		}
		
		// GitHub API Request
		$url = "https://api.github.com/repos/{$repo}/releases/latest";
		
		// Optional: GitHub Token aus Environment/Settings für höhere Rate Limits
		$headers = array(
			'Accept' => 'application/vnd.github.v3+json',
			'User-Agent' => 'PS-Update-Manager/1.1.2',
		);
		
		// Prüfe auf GitHub Token in WP Settings
		$github_token = get_option( 'ps_github_api_token' );
		if ( ! empty( $github_token ) ) {
			$headers['Authorization'] = 'token ' . sanitize_text_field( $github_token );
		}
		
		$response = wp_remote_get( $url, array(
			'timeout' => 15,
			'headers' => $headers,
			'sslverify' => true,
		) );
		
		if ( is_wp_error( $response ) ) {
			// Nicht cachen bei Netzwerkfehlern
			return $response;
		}
		
		$code = wp_remote_retrieve_response_code( $response );
		$body = wp_remote_retrieve_body( $response );
		
		// Debug Info speichern
		set_transient( 'ps_github_last_api_call', array(
			'repo'   => $repo,
			'code'   => $code,
			'time'   => current_time( 'mysql' ),
		), HOUR_IN_SECONDS );
		
		// Fehlerhafte Response-Codes handeln
		if ( 200 !== $code ) {
			$error_data = json_decode( $body, true );
			$error_message = isset( $error_data['message'] ) ? $error_data['message'] : "HTTP {$code}";
			
			// Spezielle Fehler-Handling
			if ( 404 === $code ) {
				return new WP_Error( 'github_not_found', sprintf(
					__( 'Repository "%s" auf GitHub nicht gefunden', 'ps-update-manager' ),
					esc_html( $repo )
				) );
			} elseif ( 403 === $code ) {
				// Fallback: Releases-HTML parsen, um Asset-ZIP zu finden
				$fallback = $this->fallback_latest_release_via_html( $repo );
				if ( is_array( $fallback ) && ! empty( $fallback['download_url'] ) ) {
					// Nicht cachen (HTML kann sich schnell ändern), aber gib Ergebnis zurück
					return $fallback;
				}
				return new WP_Error( 'github_rate_limit', __( 'GitHub API Rate Limit erreicht. Bitte später versuchen oder GitHub Token konfigurieren.', 'ps-update-manager' ) );
			}
			
			return new WP_Error( 'github_api_error', sprintf(
				__( 'GitHub API Fehler: %s', 'ps-update-manager' ),
				esc_html( $error_message )
			) );
		}
		
		$data = json_decode( $body, true );
		
		if ( ! $data || ! isset( $data['tag_name'] ) ) {
			return new WP_Error( 'github_invalid_response', sprintf(
				__( 'GitHub API antwortete mit ungültiger Antwort für "%s"', 'ps-update-manager' ),
				esc_html( $repo )
			) );
		}
		
		// Daten strukturieren
		$release = array(
			'version'      => ltrim( $data['tag_name'], 'v' ),
			'tag_name'     => $data['tag_name'],
			'name'         => $data['name'] ?? $data['tag_name'],
			'download_url' => $data['zipball_url'] ?? '',
			'changelog'    => $data['body'] ?? '',
			'published_at' => $data['published_at'] ?? '',
			'html_url'     => $data['html_url'] ?? '',
		);
		
		// Asset ZIP suchen (falls vorhanden)
		if ( ! empty( $data['assets'] ) && is_array( $data['assets'] ) ) {
			foreach ( $data['assets'] as $asset ) {
				if ( isset( $asset['name'] ) && preg_match( '/\.zip$/i', $asset['name'] ) ) {
					$release['download_url'] = $asset['browser_download_url'];
					break;
				}
			}
		}
		
		// In Transient speichern (nur erfolgreiche Responses)
		set_transient( $transient_key, $release, 12 * HOUR_IN_SECONDS );
		
		return $release;
	}

	/**
	 * Fallback: Neueste Release-Asset über HTML-Releases-Seite extrahieren
	 * Hinweis: Nicht so robust wie die API, aber vermeidet Rate-Limit.
	 */
	private function fallback_latest_release_via_html( $repo ) {
		$releases_url = "https://github.com/{$repo}/releases";
		$response = wp_remote_get( $releases_url, array(
			'timeout' => 15,
			'headers' => array(
				'User-Agent' => 'PS-Update-Manager/1.1.2',
			),
		) );
		if ( is_wp_error( $response ) ) {
			return $response;
		}
		$code = wp_remote_retrieve_response_code( $response );
		if ( 200 !== $code ) {
			return new WP_Error( 'github_html_error', sprintf( __( 'GitHub Releases Seite antwortete mit %d', 'ps-update-manager' ), $code ) );
		}
		$body = wp_remote_retrieve_body( $response );
		// Suche nach dem ersten Asset-Link auf der Seite
		// Beispiel: href="/owner/repo/releases/download/v1.2.3/plugin.zip"
		if ( preg_match( '#href="(/[^\"]+/releases/download/[^\"]+\.zip)"#i', $body, $m ) ) {
			$asset_path = $m[1];
			$download_url = 'https://github.com' . $asset_path;
			return array(
				'version'      => '',
				'tag_name'     => '',
				'name'         => '',
				'download_url' => $download_url,
				'changelog'    => '',
				'published_at' => '',
				'html_url'     => $releases_url,
			);
		}
		// Alternativ: ZIP der Hauptbranch (nicht Release) – nur letzter Ausweg
		$default_branch_zip = "https://codeload.github.com/{$repo}/zip/refs/heads/main";
		return array(
			'version'      => '',
			'tag_name'     => '',
			'name'         => '',
			'download_url' => $default_branch_zip,
			'changelog'    => '',
			'published_at' => '',
			'html_url'     => $releases_url,
		);
	}
	
	/**
	 * Alle Releases abrufen
	 */
	public function get_all_releases( $repo, $per_page = 10 ) {
		$url = "https://api.github.com/repos/{$repo}/releases?per_page={$per_page}";
		
		$response = wp_remote_get( $url, array(
			'timeout' => 15,
			'headers' => array(
				'Accept' => 'application/vnd.github.v3+json',
				'User-Agent' => 'PS-Update-Manager',
			),
		) );
		
		if ( is_wp_error( $response ) ) {
			return $response;
		}
		
		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );
		
		return is_array( $data ) ? $data : array();
	}
	
	/**
	 * Repository-Info abrufen
	 */
	public function get_repo_info( $repo ) {
		$transient_key = 'ps_github_repo_' . md5( $repo );
		$cached = get_transient( $transient_key );
		
		if ( false !== $cached ) {
			return $cached;
		}
		
		$url = "https://api.github.com/repos/{$repo}";
		
		$response = wp_remote_get( $url, array(
			'timeout' => 15,
			'headers' => array(
				'Accept' => 'application/vnd.github.v3+json',
				'User-Agent' => 'PS-Update-Manager',
			),
		) );
		
		if ( is_wp_error( $response ) ) {
			return $response;
		}
		
		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );
		
		if ( ! $data ) {
			return new WP_Error( 'github_invalid_response', __( 'Invalid GitHub API response', 'ps-update-manager' ) );
		}
		
		$info = array(
			'name'        => $data['name'] ?? '',
			'description' => $data['description'] ?? '',
			'stars'       => $data['stargazers_count'] ?? 0,
			'forks'       => $data['forks_count'] ?? 0,
			'open_issues' => $data['open_issues_count'] ?? 0,
			'html_url'    => $data['html_url'] ?? '',
			'homepage'    => $data['homepage'] ?? '',
			'updated_at'  => $data['updated_at'] ?? '',
		);
		
		set_transient( $transient_key, $info, 24 * HOUR_IN_SECONDS );
		
		return $info;
	}
	
	/**
	 * Transients löschen (für Force-Check)
	 */
	public function clear_cache( $repo = null ) {
		if ( $repo ) {
			delete_transient( 'ps_github_release_' . md5( $repo ) );
			delete_transient( 'ps_github_repo_' . md5( $repo ) );
		} else {
			// Alle GitHub Transients löschen
			global $wpdb;
			$wpdb->query( "DELETE FROM $wpdb->options WHERE option_name LIKE '_transient_ps_github_%'" );
			$wpdb->query( "DELETE FROM $wpdb->options WHERE option_name LIKE '_transient_timeout_ps_github_%'" );
		}
	}
	
	/**
	 * Versionen vergleichen
	 * 
	 * @return bool True wenn neue Version verfügbar
	 */
	public function has_update( $current_version, $remote_version ) {
		return version_compare( $remote_version, $current_version, '>' );
	}
}
