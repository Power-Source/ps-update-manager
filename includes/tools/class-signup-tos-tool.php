<?php
/**
 * Signup TOS (Terms of Service) Tool for PS Manager
 * 
 * Allows configuring Terms of Service for user registration.
 * Can be global (network-level) with per-site overrides, or per-site only.
 * Universal tool (works with and without multisite).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

class PS_Manager_Signup_TOS_Tool extends PS_Manager_Tool {

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->id          = 'signup-tos';
		$this->name        = __( 'Nutzungsbedingungen (TOS)', 'ps-manager' );
		$this->description = __( 'Nutzungsbedingungen für Registrierung konfigurieren', 'ps-manager' );
		$this->icon        = 'admin-page';
		$this->type        = 'universal'; // Works in both network and single-site admin
		$this->capability  = is_multisite() ? 'manage_network' : 'manage_options';

		parent::__construct();
	}

	/**
	 * Initialize hooks
	 */
	public function init() {
		// Add TOS to registration form
		add_action( 'register_form', array( $this, 'show_tos_checkbox' ) );
		
		// Validate TOS acceptance
		add_filter( 'wpmu_validate_user_signup', array( $this, 'validate_tos_acceptance' ), 10, 1 );
		add_filter( 'register_post', array( $this, 'validate_tos_acceptance_single_site' ), 10, 3 );

		// Add site-level settings menu (for per-site overrides) - Hook it later to ensure proper context
		add_action( 'admin_menu', array( $this, 'maybe_add_site_settings_menu' ), 20 );
	}

	/**
	 * Render tool interface
	 */
	public function render() {
		$mode = $this->get_tos_mode();
		$tos_content = $this->get_tos_content();
		$allow_override = get_site_option( 'ps_manager_tos_allow_override', false );

		?>
		<div class="ps-manager-tool-settings">
			<p class="description">
				<?php
				if ( is_multisite() ) {
					esc_html_e( 'Konfiguriere Nutzungsbedingungen für dein Netzwerk.', 'ps-manager' );
				} else {
					esc_html_e( 'Konfiguriere Nutzungsbedingungen für deine Website.', 'ps-manager' );
				}
				?>
			</p>

			<form method="post" action="" class="ps-manager-tool-form">
				<?php wp_nonce_field( 'ps_manager_tool_save', 'ps_manager_tool_nonce' ); ?>
				<?php wp_referer_field(); ?>
				<input type="hidden" name="action" value="ps_manager_tool_save" />
				<input type="hidden" name="tool_id" value="<?php echo esc_attr( $this->id ); ?>">

				<table class="form-table">
					<?php if ( is_multisite() ) : ?>
						<tr>
							<th scope="row">
								<label for="tos_mode">
									<?php esc_html_e( 'Modus', 'ps-manager' ); ?>
								</label>
							</th>
							<td>
								<select id="tos_mode" name="tos_mode" class="regular-text">
									<option value="global" <?php selected( $mode, 'global' ); ?>>
										<?php esc_html_e( 'Global (Netzwerkweit)', 'ps-manager' ); ?>
									</option>
									<option value="global-with-override" <?php selected( $mode, 'global-with-override' ); ?>>
										<?php esc_html_e( 'Global mit Site-Überschreibung', 'ps-manager' ); ?>
									</option>
									<option value="per-site" <?php selected( $mode, 'per-site' ); ?>>
										<?php esc_html_e( 'Pro Site', 'ps-manager' ); ?>
									</option>
								</select>
								<p class="description">
									<?php esc_html_e( 'Bestimme, wie die TOS verwaltet werden.', 'ps-manager' ); ?>
								</p>
							</td>
						</tr>
					<?php endif; ?>

					<tr>
						<th scope="row">
							<label for="tos_content">
								<?php esc_html_e( 'Nutzungsbedingungen', 'ps-manager' ); ?>
							</label>
						</th>
						<td>
							<?php
							wp_editor(
								$tos_content,
								'tos_content',
								array(
									'textarea_rows' => 10,
									'media_buttons' => false,
									'teeny'         => false,
									'tinymce'       => array(
										'toolbar1' => 'formatselect,bold,italic,underline,strikethrough,blockquote,bullist,numlist,hr',
										'toolbar2' => 'link,unlink,wp_help',
									),
								)
							);
							?>
							<p class="description">
								<?php esc_html_e( 'Gib die Nutzungsbedingungen ein, die bei der Registrierung angezeigt werden.', 'ps-manager' ); ?>
							</p>
						</td>
					</tr>

					<tr>
						<th scope="row">
							<label>
								<input type="checkbox" name="tos_required" value="1" <?php checked( $this->tos_required() ); ?> />
								<?php esc_html_e( 'Akzeptanz erforderlich', 'ps-manager' ); ?>
							</label>
						</th>
						<td>
							<p class="description">
								<?php esc_html_e( 'Nutzer müssen den TOS zustimmen, um sich registrieren zu können.', 'ps-manager' ); ?>
							</p>
						</td>
					</tr>
				</table>

				<?php submit_button( __( 'Speichern', 'ps-manager' ), 'primary', 'submit', false ); ?>
			</form>
		</div>
		<?php
	}

	/**
	 * Save tool settings
	 */
	public function save_settings() {
		// Debug: Log what we're receiving
		error_log( 'TOS Tool save_settings called' );
		error_log( 'POST data: ' . print_r( $_POST, true ) );
		error_log( 'is_network_admin: ' . ( is_network_admin() ? 'yes' : 'no' ) );
		
		$tos_content = isset( $_POST['tos_content'] ) ? wp_kses_post( wp_unslash( $_POST['tos_content'] ) ) : '';
		$tos_required = isset( $_POST['tos_required'] ) ? true : false;

		// Network Admin: Save network-wide settings
		if ( is_multisite() && is_network_admin() ) {
			$mode = isset( $_POST['tos_mode'] ) ? sanitize_text_field( wp_unslash( $_POST['tos_mode'] ) ) : 'global';
			
			error_log( 'Saving mode: ' . $mode );
			
			// Validate mode
			if ( ! in_array( $mode, array( 'global', 'global-with-override', 'per-site' ), true ) ) {
				$mode = 'global';
			}
			
			update_site_option( 'ps_manager_tos_mode', $mode );
			update_site_option( 'ps_manager_tos_content', $tos_content );
			update_site_option( 'ps_manager_tos_required', $tos_required );
			
			error_log( 'Saved mode to DB: ' . get_site_option( 'ps_manager_tos_mode' ) );
		} 
		// Site Admin: Save site-level settings (only for override/per-site mode)
		elseif ( is_multisite() && ! is_network_admin() ) {
			$mode = $this->get_tos_mode();
			
			// Only allow saving if per-site or override mode
			if ( in_array( $mode, array( 'per-site', 'global-with-override' ), true ) ) {
				update_option( 'ps_manager_tos_content_override', $tos_content );
				update_option( 'ps_manager_tos_required_override', $tos_required );
			}
		}
		// Single-site: Use regular options
		else {
			update_option( 'ps_manager_tos_content', $tos_content );
			update_option( 'ps_manager_tos_required', $tos_required );
		}

		return true;
	}

	/**
	 * Get TOS mode (network-wide setting)
	 */
	private function get_tos_mode() {
		if ( ! is_multisite() ) {
			return 'per-site';
		}

		return get_site_option( 'ps_manager_tos_mode', 'global' );
	}

	/**
	 * Get TOS content
	 */
	private function get_tos_content() {
		$mode = $this->get_tos_mode();
		
		// Network Admin: Always show network-wide content
		if ( is_network_admin() ) {
			return get_site_option( 'ps_manager_tos_content', '' );
		}
		
		// Site Admin with override/per-site: Check for site-specific content
		if ( is_multisite() ) {
			if ( 'per-site' === $mode || 'global-with-override' === $mode ) {
				$site_content = get_option( 'ps_manager_tos_content_override', '' );
				
				// Per-site mode: Only site content
				if ( 'per-site' === $mode ) {
					return $site_content;
				}
				
				// Override mode: Site content if set, otherwise global
				if ( 'global-with-override' === $mode ) {
					return ! empty( $site_content ) ? $site_content : get_site_option( 'ps_manager_tos_content', '' );
				}
			}
			
			// Global mode: Network-wide content
			return get_site_option( 'ps_manager_tos_content', '' );
		}
		
		// Single-site
		return get_option( 'ps_manager_tos_content', '' );
	}

	/**
	 * Check if TOS is required
	 */
	private function tos_required() {
		$mode = $this->get_tos_mode();
		
		// Network Admin: Network-wide setting
		if ( is_network_admin() ) {
			return get_site_option( 'ps_manager_tos_required', false );
		}
		
		// Site Admin with override/per-site
		if ( is_multisite() ) {
			if ( 'per-site' === $mode || 'global-with-override' === $mode ) {
				$site_required = get_option( 'ps_manager_tos_required_override', null );
				
				// Per-site mode: Only site setting
				if ( 'per-site' === $mode ) {
					return (bool) $site_required;
				}
				
				// Override mode: Site setting if set, otherwise global
				if ( 'global-with-override' === $mode ) {
					return null !== $site_required ? (bool) $site_required : get_site_option( 'ps_manager_tos_required', false );
				}
			}
			
			// Global mode: Network-wide setting
			return get_site_option( 'ps_manager_tos_required', false );
		}
		
		// Single-site
		return get_option( 'ps_manager_tos_required', false );
	}

	/**
	 * Show TOS checkbox on registration form (multisite)
	 */
	public function show_tos_checkbox() {
		$tos_content = $this->get_tos_content();
		
		if ( empty( $tos_content ) ) {
			return;
		}

		?>
		<div class="ps-tos-wrapper">
			<div class="ps-tos-content" style="max-height: 300px; overflow-y: auto; border: 1px solid #ddd; padding: 10px; margin-bottom: 10px;">
				<?php echo wp_kses_post( $tos_content ); ?>
			</div>
			<label>
				<input type="checkbox" name="ps_tos_accept" value="1" required>
				<?php esc_html_e( 'Ich akzeptiere die Nutzungsbedingungen', 'ps-manager' ); ?>
			</label>
		</div>
		<?php
	}

	/**
	 * Validate TOS acceptance (multisite)
	 */
	public function validate_tos_acceptance( $result ) {
		if ( ! $this->tos_required() ) {
			return $result;
		}

		$accepted = isset( $_POST['ps_tos_accept'] ) && $_POST['ps_tos_accept'];

		if ( ! $accepted ) {
			$result['errors']->add( 'ps_tos_required', __( 'Bitte akzeptiere die Nutzungsbedingungen.', 'ps-manager' ) );
		}

		return $result;
	}

	/**
	 * Validate TOS acceptance (single site)
	 */
	public function validate_tos_acceptance_single_site( $user_login, $user_email, $errors ) {
		if ( is_multisite() ) {
			return; // Handled by wpmu_validate_user_signup
		}

		if ( ! $this->tos_required() ) {
			return;
		}

		$accepted = isset( $_POST['ps_tos_accept'] ) && $_POST['ps_tos_accept'];

		if ( ! $accepted ) {
			$errors->add( 'ps_tos_required', __( 'Bitte akzeptiere die Nutzungsbedingungen.', 'ps-manager' ) );
		}
	}

	/**
	 * Maybe add site-level settings menu (called on admin_menu hook)
	 */
	public function maybe_add_site_settings_menu() {
		// Only in multisite, not in network admin
		if ( ! is_multisite() || is_network_admin() ) {
			return;
		}

		$mode = $this->get_tos_mode();
		
		// Only show menu if per-site or global-with-override
		if ( ! in_array( $mode, array( 'per-site', 'global-with-override' ), true ) ) {
			return;
		}

		add_options_page(
			__( 'Nutzungsbedingungen', 'ps-manager' ),
			__( 'Nutzungsbedingungen', 'ps-manager' ),
			'manage_options',
			'ps-manager-tos-site-settings',
			array( $this, 'render_site_settings' )
		);
	}

	/**
	 * Add site-level settings menu (only for per-site or override mode)
	 * @deprecated Use maybe_add_site_settings_menu instead
	 */
	public function add_site_settings_menu() {
		// Redirected to maybe_add_site_settings_menu
		$this->maybe_add_site_settings_menu();
	}

	/**
	 * Render site-level settings page
	 */
	public function render_site_settings() {
		$mode = $this->get_tos_mode();
		
		// Handle form submission
		if ( isset( $_POST['ps_tos_site_save'] ) ) {
			check_admin_referer( 'ps_tos_site_settings' );
			
			$tos_content = isset( $_POST['tos_content'] ) ? wp_kses_post( wp_unslash( $_POST['tos_content'] ) ) : '';
			$tos_required = isset( $_POST['tos_required'] ) ? 1 : 0;
			
			update_option( 'ps_manager_tos_content_override', $tos_content );
			update_option( 'ps_manager_tos_required_override', $tos_required );
			
			echo '<div class="notice notice-success is-dismissible"><p>';
			esc_html_e( 'Nutzungsbedingungen erfolgreich gespeichert!', 'ps-manager' );
			echo '</p></div>';
		}

		$site_content = get_option( 'ps_manager_tos_content_override', '' );
		$site_required = get_option( 'ps_manager_tos_required_override', null );
		$global_content = get_site_option( 'ps_manager_tos_content', '' );
		$global_required = get_site_option( 'ps_manager_tos_required', false );

		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Nutzungsbedingungen', 'ps-manager' ); ?></h1>
			
			<?php if ( 'global-with-override' === $mode ) : ?>
				<div class="notice notice-info">
					<p>
						<strong><?php esc_html_e( 'Überschreibungs-Modus aktiv', 'ps-manager' ); ?></strong><br>
						<?php esc_html_e( 'Du kannst die netzwerkweiten Nutzungsbedingungen für diese Site überschreiben. Lasse die Felder leer, um die globalen Einstellungen zu verwenden.', 'ps-manager' ); ?>
					</p>
				</div>
			<?php endif; ?>

			<form method="post">
				<?php wp_nonce_field( 'ps_tos_site_settings' ); ?>
				
				<table class="form-table">
					<?php if ( 'global-with-override' === $mode && ! empty( $global_content ) ) : ?>
						<tr>
							<th scope="row">
								<?php esc_html_e( 'Netzwerkweite TOS (Global)', 'ps-manager' ); ?>
							</th>
							<td>
								<div style="max-height: 200px; overflow-y: auto; border: 1px solid #ddd; padding: 15px; background: #f9f9f9;">
									<?php echo wp_kses_post( $global_content ); ?>
								</div>
								<p class="description">
									<?php esc_html_e( 'Diese Nutzungsbedingungen sind netzwerkweit definiert.', 'ps-manager' ); ?>
									<?php if ( empty( $site_content ) ) : ?>
										<strong><?php esc_html_e( 'Diese werden aktuell verwendet.', 'ps-manager' ); ?></strong>
									<?php endif; ?>
								</p>
							</td>
						</tr>
					<?php endif; ?>

					<tr>
						<th scope="row">
							<label for="tos_content">
								<?php
								if ( 'per-site' === $mode ) {
									esc_html_e( 'Nutzungsbedingungen (Diese Site)', 'ps-manager' );
								} else {
									esc_html_e( 'Nutzungsbedingungen (Überschreibung)', 'ps-manager' );
								}
								?>
							</label>
						</th>
						<td>
							<?php
							wp_editor(
								$site_content,
								'tos_content',
								array(
									'textarea_rows' => 10,
									'media_buttons' => false,
									'teeny'         => false,
									'tinymce'       => array(
										'toolbar1' => 'formatselect,bold,italic,underline,strikethrough,blockquote,bullist,numlist,hr',
										'toolbar2' => 'link,unlink,wp_help',
									),
								)
							);
							?>
							<p class="description">
								<?php
								if ( 'per-site' === $mode ) {
									esc_html_e( 'Diese Nutzungsbedingungen werden nur für diese Site verwendet.', 'ps-manager' );
								} else {
									esc_html_e( 'Leer lassen, um die globalen Nutzungsbedingungen zu verwenden.', 'ps-manager' );
								}
								?>
							</p>
						</td>
					</tr>

					<tr>
						<th scope="row">
							<?php esc_html_e( 'Akzeptanz erforderlich', 'ps-manager' ); ?>
						</th>
						<td>
							<label>
								<input type="checkbox" name="tos_required" value="1" 
									<?php checked( null !== $site_required ? $site_required : ( 'global-with-override' === $mode ? $global_required : false ) ); ?> />
								<?php esc_html_e( 'Nutzer müssen die TOS akzeptieren', 'ps-manager' ); ?>
							</label>
							<?php if ( 'global-with-override' === $mode ) : ?>
								<p class="description">
									<?php
									printf(
										/* translators: %s: global required status */
										esc_html__( 'Global: %s', 'ps-manager' ),
										$global_required ? '<strong>' . esc_html__( 'Erforderlich', 'ps-manager' ) . '</strong>' : esc_html__( 'Optional', 'ps-manager' )
									);
									?>
								</p>
							<?php endif; ?>
						</td>
					</tr>
				</table>

				<?php submit_button( __( 'Speichern', 'ps-manager' ), 'primary', 'ps_tos_site_save' ); ?>
			</form>

			<?php if ( 'global-with-override' === $mode && ! empty( $site_content ) ) : ?>
				<hr>
				<h2><?php esc_html_e( 'Überschreibung zurücksetzen', 'ps-manager' ); ?></h2>
				<p><?php esc_html_e( 'Möchtest du die site-spezifischen Einstellungen löschen und wieder die globalen Einstellungen verwenden?', 'ps-manager' ); ?></p>
				<form method="post">
					<?php wp_nonce_field( 'ps_tos_site_reset' ); ?>
					<button type="submit" name="ps_tos_site_reset" class="button button-secondary" 
						onclick="return confirm('<?php esc_attr_e( 'Bist du sicher? Die site-spezifischen Einstellungen werden gelöscht.', 'ps-manager' ); ?>');">
						<?php esc_html_e( 'Überschreibung zurücksetzen', 'ps-manager' ); ?>
					</button>
				</form>
				<?php
				if ( isset( $_POST['ps_tos_site_reset'] ) ) {
					check_admin_referer( 'ps_tos_site_reset' );
					delete_option( 'ps_manager_tos_content_override' );
					delete_option( 'ps_manager_tos_required_override' );
					echo '<div class="notice notice-success is-dismissible"><p>';
					esc_html_e( 'Überschreibung wurde zurückgesetzt. Die globalen Einstellungen werden nun verwendet.', 'ps-manager' );
					echo '</p></div>';
					echo '<script>window.location.reload();</script>';
				}
				?>
			<?php endif; ?>
		</div>
		<?php
	}
}
