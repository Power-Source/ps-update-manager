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

		// Admin-post handler for Sync All Sites action
		add_action( 'admin_post_ps_privacy_sync_all', array( $this, 'handle_sync_all_sites' ) );

		// AJAX: batch sync for smoother UX
		add_action( 'wp_ajax_ps_privacy_sync_batch', array( $this, 'ajax_sync_batch' ) );

		// Add privacy selector on site settings (Unterseiten)
		add_action( 'blog_privacy_selector', array( $this, 'render_site_privacy_selector' ) );

		// Signup form integration for new sites
		add_action( 'signup_blogform', array( $this, 'render_signup_privacy_options' ) );
		add_filter( 'add_signup_meta', array( $this, 'add_signup_privacy_meta' ) );
		add_action( 'wpmu_activate_blog', array( $this, 'apply_signup_privacy' ), 10, 5 );

		// Set default privacy for new blogs
		add_action( 'wpmu_new_blog', array( $this, 'set_default_privacy' ), 10, 6 );

		// Enqueue JS for password field toggle
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );

		// Sync blog_public when blog_privacy changes
		add_action( 'update_option_blog_privacy', array( $this, 'on_blog_privacy_updated' ), 10, 2 );

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

		<h3><?php esc_html_e( 'Site Overrides', 'ps-manager' ); ?></h3>
		<table class="form-table" role="presentation">
			<tr valign="top">
				<th scope="row">
					<label for="privacy-override">
						<?php esc_html_e( 'Allow sites to override network privacy', 'ps-manager' ); ?>
					</label>
				</th>
				<td>
					<?php $override = get_site_option( 'privacy_override', 'no' ); ?>
					<label>
						<input type="checkbox" name="privacy_override" value="yes" <?php checked( $override, 'yes' ); ?> />
						<?php esc_html_e( 'Sites can choose their own privacy level in Settings', 'ps-manager' ); ?>
					</label>
				</td>
			</tr>
		</table>

		<h3><?php esc_html_e( 'Netzwerk Synchronisierung', 'ps-manager' ); ?></h3>
		<p class="description">
			<?php esc_html_e( 'Wendet den oben gesetzten Standard-Privacy-Level auf alle Sites im Netzwerk an und synchronisiert blog_public entsprechend.', 'ps-manager' ); ?>
		</p>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<?php wp_nonce_field( 'ps_privacy_sync_all', 'ps_privacy_sync_nonce' ); ?>
			<input type="hidden" name="action" value="ps_privacy_sync_all" />
			<?php submit_button( __( 'Alle Sites synchronisieren', 'ps-manager' ), 'secondary' ); ?>
		</form>
		<?php
	}

	/**
	 * Handle Sync All Sites button: apply network default privacy to all sites
	 */
	public function handle_sync_all_sites() {
		if ( ! current_user_can( 'manage_network_options' ) ) {
			wp_die( esc_html__( 'Keine Berechtigung.', 'ps-manager' ) );
		}
		if ( ! isset( $_POST['ps_privacy_sync_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['ps_privacy_sync_nonce'] ) ), 'ps_privacy_sync_all' ) ) {
			wp_die( esc_html__( 'Ungültige Anfrage.', 'ps-manager' ) );
		}

		$default = get_site_option( 'default_blog_privacy', '1' );
		$sites = get_sites( array( 'fields' => 'ids' ) );
		$fail = 0;
		$failed_ids = array();
		foreach ( $sites as $blog_id ) {
			$ok = true;
			if ( false === update_blog_option( $blog_id, 'blog_privacy', $default ) ) {
				$ok = false;
			}
			if ( false === update_blog_option( $blog_id, 'blog_public', $default === '1' ? 1 : 0 ) ) {
				$ok = false;
			}
			if ( ! $ok ) {
				$fail++;
				$failed_ids[] = (int) $blog_id;
			}
		}

		$redirect = wp_get_referer() ? wp_get_referer() : network_admin_url( 'settings.php' );
		if ( $fail > 0 ) {
			$ids = implode( ',', $failed_ids );
			wp_safe_redirect( add_query_arg( array( 'ps_sync' => 'partial', 'fail' => $fail, 'fail_ids' => $ids ), $redirect ) );
		} else {
			wp_safe_redirect( add_query_arg( array( 'ps_sync' => 'done' ), $redirect ) );
		}
		exit;
	}

	/**
	 * AJAX: Batch sync handler
	 */
	public function ajax_sync_batch() {
		if ( ! current_user_can( 'manage_network_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Keine Berechtigung.', 'ps-manager' ) ), 403 );
		}
		check_ajax_referer( 'ps_privacy_sync_all' );

		$cmd = isset( $_POST['cmd'] ) ? sanitize_text_field( wp_unslash( $_POST['cmd'] ) ) : '';
		if ( $cmd === 'list' ) {
			$ids = get_sites( array( 'fields' => 'ids' ) );
			wp_send_json_success( array( 'ids' => array_map( 'intval', $ids ) ) );
		}

		if ( $cmd === 'apply' ) {
			$ids = isset( $_POST['ids'] ) ? sanitize_text_field( wp_unslash( $_POST['ids'] ) ) : '';
			$ids_arr = array_filter( array_map( 'intval', explode( ',', $ids ) ) );
			$default = get_site_option( 'default_blog_privacy', '1' );
			$failed = array();
			foreach ( $ids_arr as $blog_id ) {
				$ok = true;
				if ( false === update_blog_option( $blog_id, 'blog_privacy', $default ) ) {
					$ok = false;
				}
				if ( false === update_blog_option( $blog_id, 'blog_public', $default === '1' ? 1 : 0 ) ) {
					$ok = false;
				}
				if ( ! $ok ) {
					$failed[] = (int) $blog_id;
				}
			}
			wp_send_json_success( array( 'failed' => $failed ) );
		}

		wp_send_json_error( array( 'message' => __( 'Ungültiger Befehl.', 'ps-manager' ) ), 400 );
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
			$old_available = get_site_option( 'privacy_available', array() );
			$privacy_available = array_map( function( $value ) {
				return true;
			}, (array) $_POST['privacy_available'] );

			update_site_option( 'privacy_available', $privacy_available );

			// If availability changed, suggest running network sync
			if ( wp_json_encode( $old_available ) !== wp_json_encode( $privacy_available ) ) {
				add_action( 'admin_notices', function() {
					echo '<div class="notice notice-warning is-dismissible"><p>';
					esc_html_e( 'Die verfügbaren Privacy-Level wurden geändert.', 'ps-manager' );
					echo ' <a href="#ps-privacy-maintenance" class="button button-secondary">' . esc_html__( 'Jetzt synchronisieren', 'ps-manager' ) . '</a>';
					echo '</p></div>';
				} );
			}
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

		// Allow site override
		$override = ( isset( $_POST['privacy_override'] ) && 'yes' === sanitize_text_field( wp_unslash( $_POST['privacy_override'] ) ) ) ? 'yes' : 'no';
		update_site_option( 'privacy_override', $override );

		// If enabling override, ensure all sites' blog_public is aligned with current privacy
		if ( 'yes' === $override ) {
			$sites = get_sites( array( 'fields' => 'ids' ) );
			foreach ( $sites as $blog_id ) {
				$privacy = get_blog_option( $blog_id, 'blog_privacy', get_site_option( 'default_blog_privacy', '1' ) );
				update_blog_option( $blog_id, 'blog_public', $privacy === '1' ? 1 : 0 );
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
		// Sync blog_public to reflect privacy
		update_blog_option( $blog_id, 'blog_public', $default === '1' ? 1 : 0 );

		// If password-protected, set initial password
		if ( $default === '-4' ) {
			update_blog_option( $blog_id, 'blog_privacy_password', wp_generate_password( 12, false ) );
		}
	}

	/**
	 * Render privacy selector in site settings (Unterseiten)
	 */
	public function render_site_privacy_selector() {
		// Only if override allowed
		if ( get_site_option( 'privacy_override', 'no' ) !== 'yes' ) {
			return;
		}

		$levels = array(
			'1'  => __( 'Öffentlich (für alle sichtbar)', 'ps-manager' ),
			'0'  => __( 'Öffentlich, aber Suchmaschinen blockieren', 'ps-manager' ),
			'-1' => __( 'Nur Netzwerk-Mitglieder', 'ps-manager' ),
			'-2' => __( 'Nur Mitglieder dieser Seite', 'ps-manager' ),
			'-3' => __( 'Nur Administratoren', 'ps-manager' ),
			'-4' => __( 'Passwortgeschützt', 'ps-manager' ),
		);

		// Filter by network-available privacy levels
		$available = get_site_option( 'privacy_available', array() );
		$levels = array_filter( $levels, function( $label, $value ) use ( $available ) {
			return isset( $available[ (string) $value ] ) ? (bool) $available[ (string) $value ] : false;
		}, ARRAY_FILTER_USE_BOTH );

		$current = get_option( 'blog_privacy', get_site_option( 'default_blog_privacy', '1' ) );
		$auto_corrected = false;
		// If current value is no longer allowed, reset to network default or first allowed option
		if ( ! array_key_exists( (string) $current, $levels ) ) {
			$default = get_site_option( 'default_blog_privacy', '1' );
			$replacement = array_key_exists( (string) $default, $levels ) ? (string) $default : (string) key( $levels );
			update_option( 'blog_privacy', $replacement );
			update_option( 'blog_public', $replacement === '1' ? 1 : 0 );
			$current = $replacement;
			$auto_corrected = true;
		}

		echo '<p class="description">' . esc_html__( 'Datenschutzstufe festlegen:', 'ps-manager' ) . '</p>';
		echo '<fieldset>';
		foreach ( $levels as $value => $label ) {
			printf(
				'<label><input type="radio" name="blog_privacy" value="%1$s" %2$s /> %3$s</label><br/>',
				esc_attr( $value ),
				checked( $current, $value, false ),
				esc_html( $label )
			);
		}
		// Password field when -4 selected (JS toggled)
		$pwd = get_option( 'blog_privacy_password', '' );
		echo '<div id="blog-privacy-password-wrap" style="margin-top:8px;' . ( isset( $levels['-4'] ) ? '' : 'display:none;' ) . '">';
		echo '<input type="password" name="blog_privacy_password" value="' . esc_attr( $pwd ) . '" placeholder="' . esc_attr__( 'Passwort für Zugriff', 'ps-manager' ) . '" />';
		echo '</div>';
		echo '</fieldset>';

		if ( $auto_corrected ) {
			echo '<div class="notice notice-warning" style="margin-top:10px;"><p>';
			echo esc_html__( 'Hinweis: Ihre bisherige Datenschutzstufe ist im Netzwerk nicht mehr erlaubt und wurde automatisch auf einen zulässigen Wert angepasst.', 'ps-manager' );
			echo '</p></div>';
		}
	}

	/**
	 * Signup form: render privacy options
	 */
	public function render_signup_privacy_options() {
		if ( get_site_option( 'privacy_override', 'no' ) !== 'yes' ) {
			return;
		}
		$levels = array(
			'1'  => __( 'Öffentlich', 'ps-manager' ),
			'0'  => __( 'Öffentlich mit Suchmaschinen-Block', 'ps-manager' ),
			'-1' => __( 'Nur Netzwerk', 'ps-manager' ),
			'-2' => __( 'Nur Seiten-Mitglieder', 'ps-manager' ),
			'-3' => __( 'Nur Administratoren', 'ps-manager' ),
			'-4' => __( 'Passwortgeschützt', 'ps-manager' ),
		);
		$available = get_site_option( 'privacy_available', array() );
		$levels = array_filter( $levels, function( $label, $value ) use ( $available ) {
			return isset( $available[ (string) $value ] ) ? (bool) $available[ (string) $value ] : false;
		}, ARRAY_FILTER_USE_BOTH );
		$current = get_site_option( 'default_blog_privacy', '1' );
		if ( ! array_key_exists( (string) $current, $levels ) ) {
			$current = (string) key( $levels );
		}
		echo '<h3>' . esc_html__( 'Datenschutz der neuen Seite', 'ps-manager' ) . '</h3>';
		echo '<fieldset>';
		foreach ( $levels as $value => $label ) {
			printf('<label><input type="radio" name="signup_blog_privacy" value="%1$s" %2$s /> %3$s</label><br/>', esc_attr( $value ), checked( $current, $value, false ), esc_html( $label ));
		}
		echo '</fieldset>';
	}

	/**
	 * Signup: add selected privacy to meta
	 */
	public function add_signup_privacy_meta( $meta ) {
		if ( isset( $_POST['signup_blog_privacy'] ) ) {
			$meta['signup_blog_privacy'] = sanitize_text_field( wp_unslash( $_POST['signup_blog_privacy'] ) );
		}
		return $meta;
	}

	/**
	 * Apply privacy on blog activation
	 */
	public function apply_signup_privacy( $blog_id, $user_id, $password, $signup_title, $meta ) {
		$privacy = isset( $meta['signup_blog_privacy'] ) ? $meta['signup_blog_privacy'] : get_site_option( 'default_blog_privacy', '1' );
		update_blog_option( $blog_id, 'blog_privacy', $privacy );
		// Sync blog_public to reflect privacy
		update_blog_option( $blog_id, 'blog_public', $privacy === '1' ? 1 : 0 );
		if ( '-4' === $privacy ) {
			update_blog_option( $blog_id, 'blog_privacy_password', wp_generate_password( 12, false ) );
		}
	}

	/**
	 * When a site admin updates privacy, ensure blog_public reflects it.
	 *
	 * @param mixed $old Old value
	 * @param mixed $value New value
	 */
	public function on_blog_privacy_updated( $old, $value ) {
		$privacy = is_string( $value ) ? $value : (string) $value;
		update_option( 'blog_public', $privacy === '1' ? 1 : 0 );
	}

	/**
	 * Enqueue assets
	 *
	 * @param string $hook Current admin page
	 */
	public function enqueue_assets( $hook ) {
		// Network admin: enqueue for wpmu_options
		if ( is_network_admin() ) {
			wp_enqueue_script(
				'ps-manager-privacy',
				plugins_url( 'includes/tools/assets/js/privacy-admin.js', dirname( dirname( __FILE__ ) ) ),
				array( 'jquery' ),
				PS_UPDATE_MANAGER_VERSION,
				true
			);
		}

		// Site admin: enqueue on Reading settings page where blog_privacy_selector renders
		if ( ! is_network_admin() && 'options-reading.php' === $hook ) {
			// Only enqueue if override is allowed so the UI stays consistent
			if ( get_site_option( 'privacy_override', 'no' ) !== 'yes' ) {
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
			wp_redirect( wp_login_url( $_SERVER['REQUEST_URI'] ) );
			exit;
		}

		// Handle admin-only
		if ( $privacy === '-3' ) {
			if ( ! is_user_logged_in() || ! current_user_can( 'manage_options' ) ) {
				wp_redirect( wp_login_url( $_SERVER['REQUEST_URI'] ) );
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

				<h3><?php esc_html_e( 'Site Overrides', 'ps-manager' ); ?></h3>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row">
							<label for="privacy_override"><?php esc_html_e( 'Unterseiten dürfen Einstellungen überschreiben', 'ps-manager' ); ?></label>
						</th>
						<td>
							<?php $override = get_site_option( 'privacy_override', 'no' ); ?>
							<label>
								<input type="checkbox" name="privacy_override" value="yes" <?php checked( $override, 'yes' ); ?> />
								<?php esc_html_e( 'Wenn aktiviert, erscheinen die Privacy-Optionen in den Unterseiten (Einstellungen → Lesen).', 'ps-manager' ); ?>
							</label>
						</td>
					</tr>
				</table>

				<?php submit_button( __( 'Einstellungen speichern', 'ps-manager' ) ); ?>
			</form>
		</div>

			<div id="ps-privacy-maintenance" class="ps-privacy-maintenance" style="background:#fff3cd;border:1px solid #ffeeba;padding:16px;border-radius:4px;margin-top:16px;">
				<h2 style="margin-top:0;"><?php esc_html_e( 'Notfall-Wartung: Netzwerk-Synchronisierung', 'ps-manager' ); ?></h2>
				<p class="description" style="margin-bottom:12px;">
					<?php esc_html_e( 'Warnung: Dies setzt alle Privacy-Einstellungen der Unterseiten auf den oben konfigurierten Standard zurück und synchronisiert die Sichtbarkeit (blog_public).', 'ps-manager' ); ?>
				</p>
				<p class="description" style="margin-bottom:16px;">
					<?php esc_html_e( 'Hinweis: Der Vorgang kann je nach Anzahl der Sites einige Sekunden dauern. Bitte nicht abbrechen.', 'ps-manager' ); ?>
				</p>
				<form id="ps-privacy-sync-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<?php wp_nonce_field( 'ps_privacy_sync_all', 'ps_privacy_sync_nonce' ); ?>
					<input type="hidden" name="action" value="ps_privacy_sync_all" />
					<?php submit_button( __( 'Alle Sites jetzt synchronisieren', 'ps-manager' ), 'delete', 'ps-privacy-sync-submit', false ); ?>
					<span id="ps-privacy-sync-progress" style="display:none;margin-left:8px;vertical-align:middle;">
						<?php esc_html_e( 'Synchronisierung läuft…', 'ps-manager' ); ?>
					</span>
				</form>
				<script>
				(function(){
					var form = document.getElementById('ps-privacy-sync-form');
					var btn = document.getElementById('ps-privacy-sync-submit');
					var prog = document.getElementById('ps-privacy-sync-progress');
					var nonce = form.querySelector('input[name="ps_privacy_sync_nonce"]').value;
					if(form && btn && prog){
						form.addEventListener('submit', function(e){
							e.preventDefault();
							var ok = confirm('<?php echo esc_js( __( 'Dies setzt alle Privacy-Einstellungen der Unterwebseiten auf die hier angegebenen Einstellungen. FORTFAHREN?', 'ps-manager' ) ); ?>');
							if(!ok){ return false; }
							btn.setAttribute('disabled', 'disabled');
							prog.style.display = 'inline-block';
							// Fetch site IDs via AJAX (use batch endpoint with special command)
							fetch(ajaxurl, {
								method: 'POST',
								headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
								body: new URLSearchParams({ action: 'ps_privacy_sync_batch', cmd: 'list', _ajax_nonce: nonce })
							}).then(function(r){ return r.json(); }).then(function(resp){
								if(!resp.success){ alert(resp.data && resp.data.message ? resp.data.message : 'Fehler beim Laden der Sites'); btn.removeAttribute('disabled'); prog.style.display = 'none'; return; }
								var ids = resp.data.ids || [];
								var index = 0; var batch = 20; var fails = [];
								function step(){
									if(index >= ids.length){
										// Done: redirect with summary
										var url = new URL(window.location.href);
										url.searchParams.set('ps_sync', fails.length ? 'partial' : 'done');
										if(fails.length){ url.searchParams.set('fail', fails.length); url.searchParams.set('fail_ids', fails.join(',')); }
										window.location.href = url.toString();
										return;
									}
									var chunk = ids.slice(index, index+batch);
									prog.textContent = '<?php echo esc_js( __( 'Synchronisierung läuft…', 'ps-manager' ) ); ?> (' + Math.min(index+batch, ids.length) + '/' + ids.length + ')';
									fetch(ajaxurl, {
										method: 'POST',
										headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
										body: new URLSearchParams({ action: 'ps_privacy_sync_batch', cmd: 'apply', _ajax_nonce: nonce, ids: chunk.join(',') })
									}).then(function(r){ return r.json(); }).then(function(resp){
										if(!resp.success){
											fails = fails.concat(chunk);
										}else{
											if(resp.data && resp.data.failed){ fails = fails.concat(resp.data.failed); }
										}
										index += batch;
										setTimeout(step, 50);
									}).catch(function(){ fails = fails.concat(chunk); index += batch; setTimeout(step, 50); });
								}
								step();
							}).catch(function(){ alert('Fehler beim Starten der Synchronisierung'); btn.removeAttribute('disabled'); prog.style.display = 'none'; });
						});
					}
				})();
				</script>

				<?php if ( isset( $_GET['ps_sync'] ) ) :
					$code = sanitize_text_field( wp_unslash( $_GET['ps_sync'] ) );
					if ( 'done' === $code ) : ?>
						<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Alle Sites wurden erfolgreich synchronisiert.', 'ps-manager' ); ?></p></div>
					<?php elseif ( 'partial' === $code ) :
						$fail = isset( $_GET['fail'] ) ? intval( $_GET['fail'] ) : 0;
						$ids = isset( $_GET['fail_ids'] ) ? sanitize_text_field( wp_unslash( $_GET['fail_ids'] ) ) : ''; ?>
						<div class="notice notice-warning is-dismissible"><p><?php printf( esc_html__( 'Synchronisierung abgeschlossen, aber %d Sites konnten nicht aktualisiert werden.', 'ps-manager' ), $fail ); ?>
						<?php if ( ! empty( $ids ) ) : ?>
							<br><?php echo esc_html__( 'Betroffene Site-IDs:', 'ps-manager' ); ?> <?php echo esc_html( $ids ); ?>
						<?php endif; ?></p></div>
					<?php elseif ( 'error' === $code ) : ?>
						<div class="notice notice-error is-dismissible"><p><?php esc_html_e( 'Synchronisierung fehlgeschlagen.', 'ps-manager' ); ?></p></div>
					<?php endif; endif; ?>
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
			$old_available = get_site_option( 'privacy_available', array() );
			$privacy_available = array_map( function( $value ) {
				return true;
			}, (array) $_POST['privacy_available'] );

			update_site_option( 'privacy_available', $privacy_available );

			// If availability changed, suggest running network sync
			if ( wp_json_encode( $old_available ) !== wp_json_encode( $privacy_available ) ) {
				add_action( 'admin_notices', function() {
					echo '<div class="notice notice-warning is-dismissible"><p>';
					esc_html_e( 'Die verfügbaren Privacy-Level wurden geändert. Tipp: Nutze die Notfall-Wartungsbox unten, um alle Sites mit den neuen Netzwerk-Einstellungen zu synchronisieren.', 'ps-manager' );
					echo '</p></div>';
				} );
			}
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

		// Allow site override
		$override = ( isset( $_POST['privacy_override'] ) && 'yes' === sanitize_text_field( wp_unslash( $_POST['privacy_override'] ) ) ) ? 'yes' : 'no';
		update_site_option( 'privacy_override', $override );

		// Success message
		add_action( 'admin_notices', function() {
			echo '<div class="notice notice-success is-dismissible"><p>';
			esc_html_e( 'Privacy-Einstellungen erfolgreich gespeichert!', 'ps-manager' );
			echo '</p></div>';
		} );
	}
}
