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
		// Transient prüfen (Cache für 12 Stunden)
		$transient_key = 'ps_github_release_' . md5( $repo );
		$cached = get_transient( $transient_key );
		
		if ( false !== $cached ) {
			return $cached;
		}
		
		// GitHub API Request
		$url = "https://api.github.com/repos/{$repo}/releases/latest";
		
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
		
		$code = wp_remote_retrieve_response_code( $response );
		if ( 200 !== $code ) {
			return new WP_Error( 'github_api_error', sprintf(
				__( 'GitHub API returned error code %d', 'ps-update-manager' ),
				$code
			) );
		}
		
		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );
		
		if ( ! $data || ! isset( $data['tag_name'] ) ) {
			return new WP_Error( 'github_invalid_response', __( 'Invalid GitHub API response', 'ps-update-manager' ) );
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
		
		// In Transient speichern
		set_transient( $transient_key, $release, 12 * HOUR_IN_SECONDS );
		
		return $release;
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
