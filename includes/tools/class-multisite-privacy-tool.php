<?php
/**
 * PS Manager: Multisite Privacy Tool
 * Provides blog privacy level selection (public, private, password-protected, etc.)
 *
 * @package PS_Manager
 * @subpackage Tools
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * PS_Manager_Multisite_Privacy_Tool
 *
 * Integrates multisite privacy levels: Public, Search Blocked, Network-only, Site-only,
 * Admin-only, and Password-protected. Adds privacy level selection to blog settings.
 */
class PS_Manager_Multisite_Privacy_Tool extends PS_Manager_Tool {

	/**
	 * Tool ID
	 *
	 * @var string
	 */
	public $id = 'multisite-privacy';

	/**
	 * Tool name
	 *
	 * @var string
	 */
	public $name = 'Multisite Privacy';

	/**
	 * Tool description
	 *
	 * @var string
	 */
	public $description = 'Control blog visibility and access levels (public, private, password-protected, etc.)';

	/**
	 * Tool type (network-only)
	 *
	 * @var string
	 */
	public $type = 'network-only';

	/**
	 * Tool icon
	 *
	 * @var string
	 */
	public $icon = 'lock';

	/**
	 * Initialize the tool
	 */
	public function init() {
		// Only on multisite
		if ( ! is_multisite() ) {
			return;
		}

		// Handle settings save from tool tab
		add_action( 'admin_init', array( $this, 'save_settings' ) );

		// Add privacy options to wpmu_options (network admin) - für WordPress native integration
		add_action( 'wpmu_options', array( $this, 'render_privacy_options' ) );
		add_action( 'update_wpmu_options', array( $this, 'save_privacy_options' ) );

		// Set default privacy for new blogs
		add_action( 'wpmu_new_blog', array( $this, 'set_default_privacy' ), 10, 6 );

		// Enqueue JS for password field toggle
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );

		// Frontend privacy enforcement
		add_filter( 'wp_authenticate', array( $this, 'check_privacy' ), 1, 2 );
		add_filter( 'template_redirect', array( $this, 'enforce_privacy' ), 1 );
	}

	/**
	 * Render privacy options in wpmu_options
	 */
	public function render_privacy_options() {
		?>
		<h3><?php esc_html_e( 'Blog Privacy Options', 'ps-manager' ); ?></h3>
		<table class="form-table" role="presentation">
			<tr valign="top">
				<th scope="row">
					<label for="blog-privacy-levels">
						<?php esc_html_e( 'Available Privacy Levels', 'ps-manager' ); ?>
					</label>
				</th>
				<td>
					<?php $this->render_privacy_levels(); ?>
					<p class="description">
						<?php esc_html_e( 'Check which privacy levels should be available for blogs on this network.', 'ps-manager' ); ?>
					</p>
				</td>
			</tr>
			<tr valign="top">
				<th scope="row">
					<label for="default-blog-privacy">
						<?php esc_html_e( 'Default Privacy Level', 'ps-manager' ); ?>
					</label>
				</th>
				<td>
					<?php $this->render_default_privacy(); ?>
					<p class="description">
						<?php esc_html_e( 'Set the default privacy level for newly created blogs.', 'ps-manager' ); ?>
					</p>
				</td>
			</tr>
		</table>
		<?php
	}

	/**
	 * Render privacy level checkboxes
	 */
	private function render_privacy_levels() {
		$privacy_available = get_site_option( 'privacy_available', array() );

		$levels = array(
			'1'  => __( 'Public - openly available to everyone', 'ps-manager' ),
			'0'  => __( 'Search Engine Blocked - indexed by search engines but not openly advertised', 'ps-manager' ),
			'-1' => __( 'Network-only - visible to logged-in network members only', 'ps-manager' ),
			'-2' => __( 'Site-only - visible to this site\'s members only', 'ps-manager' ),
			'-3' => __( 'Admin-only - visible to administrators only', 'ps-manager' ),
			'-4' => __( 'Password-protected - password required to access', 'ps-manager' ),
		);

		echo '<fieldset>';
		foreach ( $levels as $value => $label ) {
			$checked = isset( $privacy_available[ $value ] ) ? $privacy_available[ $value ] : false;
			?>
			<label>
				<input type="checkbox"
					name="privacy_available[<?php echo esc_attr( $value ); ?>]"
					value="1"
					<?php checked( $checked, true ); ?>
				/>
				<?php echo esc_html( $label ); ?>
			</label>
			<br />
			<?php
		}
		echo '</fieldset>';
	}

	/**
	 * Render default privacy level select
	 */
	private function render_default_privacy() {
		$default = get_site_option( 'default_blog_privacy', '1' );

		$levels = array(
			'1'  => __( 'Public', 'ps-manager' ),
			'0'  => __( 'Search Engine Blocked', 'ps-manager' ),
			'-1' => __( 'Network-only', 'ps-manager' ),
			'-2' => __( 'Site-only', 'ps-manager' ),
			'-3' => __( 'Admin-only', 'ps-manager' ),
			'-4' => __( 'Password-protected', 'ps-manager' ),
		);

		?>
		<select name="default_blog_privacy">
			<?php foreach ( $levels as $value => $label ) : ?>
				<option value="<?php echo esc_attr( $value ); ?>"
					<?php selected( $default, $value ); ?>>
					<?php echo esc_html( $label ); ?>
				</option>
			<?php endforeach; ?>
		</select>
		<?php
	}

	/**
	 * Save privacy options
	 */
	public function save_privacy_options() {
		// Verify nonce
		if ( ! isset( $_POST['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ), 'closedpostboxes_page' ) ) {
			return;
		}

		// Save available privacy levels
		if ( isset( $_POST['privacy_available'] ) ) {
			$privacy_available = array_map( function( $value ) {
				return true;
			}, (array) $_POST['privacy_available'] );

			update_site_option( 'privacy_available', $privacy_available );
		} else {
			update_site_option( 'privacy_available', array() );
		}

		// Save default privacy level
		if ( isset( $_POST['default_blog_privacy'] ) ) {
			$default = sanitize_text_field( wp_unslash( $_POST['default_blog_privacy'] ) );
			if ( in_array( $default, array( '1', '0', '-1', '-2', '-3', '-4' ), true ) ) {
				update_site_option( 'default_blog_privacy', $default );
			}
		}
	}

	/**
	 * Set default privacy for new blogs
	 *
	 * @param int    $blog_id Blog ID
	 * @param int    $user_id User ID
	 * @param string $domain  Domain
	 * @param string $path    Path
	 * @param int    $site_id Site ID
	 * @param array  $meta    Blog meta
	 */
	public function set_default_privacy( $blog_id, $user_id, $domain, $path, $site_id, $meta ) {
		$default = get_site_option( 'default_blog_privacy', '1' );
		update_blog_option( $blog_id, 'blog_privacy', $default );

		// If password-protected, set initial password
		if ( $default === '-4' ) {
			update_blog_option( $blog_id, 'blog_privacy_password', wp_generate_password( 12, false ) );
		}
	}

	/**
	 * Enqueue assets
	 *
	 * @param string $hook Current admin page
	 */
	public function enqueue_assets( $hook ) {
		// Only on network admin
		if ( ! is_network_admin() ) {
			return;
		}

		wp_enqueue_script(
			'ps-manager-privacy',
			plugins_url( 'includes/tools/assets/js/privacy-admin.js', dirname( dirname( __FILE__ ) ) ),
			array( 'jquery' ),
			PS_UPDATE_MANAGER_VERSION,
			true
		);
	}

	/**
	 * Check privacy on login
	 *
	 * @param string $user User name/email
	 * @param string $password Password
	 * @return string|void
	 */
	public function check_privacy( $user, $password ) {
		// Check if current blog allows anonymous access
		$privacy = get_blog_option( get_current_blog_id(), 'blog_privacy', '1' );

		// -2 = Site-only (logged-in members), -3 = Admin-only, -4 = Password-protected
		if ( in_array( $privacy, array( '-2', '-3', '-4' ), true ) ) {
			if ( ! is_user_logged_in() ) {
				return $user;
			}
		}
	}

	/**
	 * Enforce privacy on frontend
	 */
	public function enforce_privacy() {
		// Skip for admins
		if ( is_admin() || is_user_admin() ) {
			return;
		}

		$privacy = get_blog_option( get_current_blog_id(), 'blog_privacy', '1' );

		// Handle password-protected blogs
		if ( $privacy === '-4' ) {
			$password = get_blog_option( get_current_blog_id(), 'blog_privacy_password', '' );

			// Check if user provided password in cookie
			$cookie_name = 'blog_privacy_' . get_current_blog_id();
			$provided_password = isset( $_COOKIE[ $cookie_name ] ) ? sanitize_text_field( wp_unslash( $_COOKIE[ $cookie_name ] ) ) : '';

			if ( $provided_password !== $password && ! is_user_logged_in() ) {
				$this->show_password_form( $password );
				exit;
			}
		}

		// Handle site-only (members only)
		if ( $privacy === '-2' && ! is_user_logged_in() ) {
			wp_safe_remote_post(
				wp_login_url( $_SERVER['REQUEST_URI'] ),
				array(
					'blocking' => false,
				)
			);
			exit;
		}

		// Handle admin-only
		if ( $privacy === '-3' ) {
			if ( ! is_user_logged_in() || ! current_user_can( 'manage_options' ) ) {
				wp_safe_remote_post(
					wp_login_url( $_SERVER['REQUEST_URI'] ),
					array(
						'blocking' => false,
					)
				);
				exit;
			}
		}
	}

	/**
	 * Show password form for password-protected blogs
	 *
	 * @param string $password Expected password
	 */
	private function show_password_form( $password ) {
		if ( isset( $_POST['blog_password'] ) ) {
			$provided = sanitize_text_field( wp_unslash( $_POST['blog_password'] ) );
			if ( $provided === $password ) {
				setcookie( 'blog_privacy_' . get_current_blog_id(), $provided, time() + MONTH_IN_SECONDS, COOKIEPATH, COOKIE_DOMAIN );
				wp_safe_remote_post(
					$_SERVER['REQUEST_URI'],
					array(
						'blocking' => false,
					)
				);
				exit;
			}
		}

		?>
		<!DOCTYPE html>
		<html>
		<head>
			<title><?php esc_html_e( 'Protected Content', 'ps-manager' ); ?></title>
			<style>
				body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif; text-align: center; padding-top: 50px; background: #f1f1f1; }
				.password-form { background: white; padding: 40px; border-radius: 5px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); max-width: 300px; margin: 0 auto; }
				input { padding: 8px; width: 100%; box-sizing: border-box; margin: 10px 0; }
				button { padding: 8px 20px; background: #0073aa; color: white; border: none; cursor: pointer; border-radius: 3px; }
			</style>
		</head>
		<body>
			<div class="password-form">
				<h2><?php esc_html_e( 'This site is password protected', 'ps-manager' ); ?></h2>
				<form method="post">
					<input type="password" name="blog_password" placeholder="<?php esc_attr_e( 'Enter password', 'ps-manager' ); ?>" />
					<button type="submit"><?php esc_html_e( 'Enter', 'ps-manager' ); ?></button>
				</form>
			</div>
		</body>
		</html>
		<?php
	}

	/**
	 * Render tool in admin dashboard (if used as tab)
	 */
	public function render() {
		if ( ! is_network_admin() ) {
			echo '<div class="notice notice-warning"><p>';
			esc_html_e( 'Dieses Tool ist nur im Netzwerk-Admin verfügbar.', 'ps-manager' );
			echo '</p></div>';
			return;
		}

		// Get current settings
		$privacy_available = get_site_option( 'privacy_available', array() );
		$default_privacy = get_site_option( 'default_blog_privacy', '1' );

		?>
		<div class="ps-privacy-tool-settings">
			<h2><?php esc_html_e( 'Blog Privacy Einstellungen', 'ps-manager' ); ?></h2>
			<p class="description">
				<?php esc_html_e( 'Konfiguriere welche Privacy-Level für Blogs im Netzwerk verfügbar sein sollen.', 'ps-manager' ); ?>
			</p>

			<form method="post" action="">
				<?php wp_nonce_field( 'ps_privacy_settings', 'ps_privacy_nonce' ); ?>
				
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row">
							<label><?php esc_html_e( 'Verfügbare Privacy-Level', 'ps-manager' ); ?></label>
						</th>
						<td>
							<fieldset>
								<legend class="screen-reader-text">
									<span><?php esc_html_e( 'Verfügbare Privacy-Level', 'ps-manager' ); ?></span>
								</legend>
								<?php
								$levels = array(
									'1'  => array(
										'label' => __( 'Öffentlich', 'ps-manager' ),
										'desc'  => __( 'Für jeden frei zugänglich', 'ps-manager' ),
									),
									'0'  => array(
										'label' => __( 'Suchmaschinen blockiert', 'ps-manager' ),
										'desc'  => __( 'Von Suchmaschinen indexiert, aber nicht öffentlich beworben', 'ps-manager' ),
									),
									'-1' => array(
										'label' => __( 'Nur Netzwerk-Mitglieder', 'ps-manager' ),
										'desc'  => __( 'Nur für eingeloggte Netzwerk-Benutzer sichtbar', 'ps-manager' ),
									),
									'-2' => array(
										'label' => __( 'Nur Site-Mitglieder', 'ps-manager' ),
										'desc'  => __( 'Nur für Mitglieder dieser spezifischen Site sichtbar', 'ps-manager' ),
									),
									'-3' => array(
										'label' => __( 'Nur Administratoren', 'ps-manager' ),
										'desc'  => __( 'Nur für Site-Administratoren zugänglich', 'ps-manager' ),
									),
									'-4' => array(
										'label' => __( 'Passwortgeschützt', 'ps-manager' ),
										'desc'  => __( 'Passwort erforderlich für Zugriff', 'ps-manager' ),
									),
								);

								foreach ( $levels as $value => $level ) {
									$checked = isset( $privacy_available[ $value ] ) ? $privacy_available[ $value ] : false;
									?>
									<label style="display: block; margin-bottom: 12px;">
										<input type="checkbox"
											name="privacy_available[<?php echo esc_attr( $value ); ?>]"
											value="1"
											<?php checked( $checked, true ); ?>
										/>
										<strong><?php echo esc_html( $level['label'] ); ?></strong>
										<br>
										<span class="description" style="margin-left: 25px;">
											<?php echo esc_html( $level['desc'] ); ?>
										</span>
									</label>
									<?php
								}
								?>
							</fieldset>
						</td>
					</tr>

					<tr>
						<th scope="row">
							<label for="default_blog_privacy"><?php esc_html_e( 'Standard Privacy-Level', 'ps-manager' ); ?></label>
						</th>
						<td>
							<select name="default_blog_privacy" id="default_blog_privacy">
								<?php foreach ( $levels as $value => $level ) : ?>
									<option value="<?php echo esc_attr( $value ); ?>"
										<?php selected( $default_privacy, $value ); ?>>
										<?php echo esc_html( $level['label'] ); ?>
									</option>
								<?php endforeach; ?>
							</select>
							<p class="description">
								<?php esc_html_e( 'Dieser Privacy-Level wird automatisch für neu erstellte Blogs gesetzt.', 'ps-manager' ); ?>
							</p>
						</td>
					</tr>
				</table>

				<?php submit_button( __( 'Einstellungen speichern', 'ps-manager' ) ); ?>
			</form>
		</div>

		<style>
			.ps-privacy-tool-settings {
				background: white;
				padding: 20px;
				margin-top: 20px;
				border-radius: 4px;
				box-shadow: 0 1px 3px rgba(0,0,0,0.1);
			}
			
			.ps-privacy-tool-settings h2 {
				margin-top: 0;
				padding-bottom: 10px;
				border-bottom: 1px solid #ddd;
			}
			
			.ps-privacy-tool-settings .form-table th {
				padding: 20px 10px 20px 0;
				vertical-align: top;
			}
			
			.ps-privacy-tool-settings .form-table td {
				padding: 15px 10px;
			}
		</style>
		<?php
	}

	/**
	 * Save settings from tool tab
	 */
	public function save_settings() {
		// Verify nonce
		if ( ! isset( $_POST['ps_privacy_nonce'] ) ) {
			return;
		}

		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['ps_privacy_nonce'] ) ), 'ps_privacy_settings' ) ) {
			return;
		}

		// Only network admins can save
		if ( ! is_super_admin() ) {
			return;
		}

		// Save available privacy levels
		if ( isset( $_POST['privacy_available'] ) ) {
			$privacy_available = array_map( function( $value ) {
				return true;
			}, (array) $_POST['privacy_available'] );

			update_site_option( 'privacy_available', $privacy_available );
		} else {
			update_site_option( 'privacy_available', array() );
		}

		// Save default privacy level
		if ( isset( $_POST['default_blog_privacy'] ) ) {
			$default = sanitize_text_field( wp_unslash( $_POST['default_blog_privacy'] ) );
			if ( in_array( $default, array( '1', '0', '-1', '-2', '-3', '-4' ), true ) ) {
				update_site_option( 'default_blog_privacy', $default );
			}
		}

		// Success message
		add_action( 'admin_notices', function() {
			echo '<div class="notice notice-success is-dismissible"><p>';
			esc_html_e( 'Privacy-Einstellungen erfolgreich gespeichert!', 'ps-manager' );
			echo '</p></div>';
		} );
	}
}
