<?php
/**
 * Default Theme Tool for PS Manager
 * 
 * Allows network admin to set a default theme for new blog registrations.
 * Network-only tool (multisite required).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

class PS_Manager_Default_Theme_Tool extends PS_Manager_Tool {

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->id          = 'default-theme';
		$this->name        = __( 'Standard-Theme', 'ps-update-manager' );
		$this->description = __( 'Standard-Theme für neue Blog-Registrierungen festlegen', 'ps-update-manager' );
		$this->icon        = 'admin-appearance';
		$this->type        = 'network-only'; // Only available in network admin
		$this->capability  = 'manage_network';

		parent::__construct();
	}

	/**
	 * Initialize hooks
	 */
	public function init() {
		// Auto-activate default theme when new blog is created
		add_action( 'wpmu_new_blog', array( $this, 'activate_default_theme_for_new_blog' ), 10, 1 );
	}

	/**
	 * Render tool interface
	 */
	public function render() {
		// Enqueue assets for this tool (settings.css is already loaded via admin-dashboard)
		wp_enqueue_script( 'ps-default-theme-tool', PS_UPDATE_MANAGER_URL . 'includes/tools/assets/js/default-theme-tool.js', array( 'jquery' ), PS_UPDATE_MANAGER_VERSION, true );
		
		$default_theme = get_site_option( 'ps_manager_default_theme' );
		
		// Set ps-padma-child as default if it's installed and no default is set
		if ( empty( $default_theme ) ) {
			$padma_child = wp_get_theme( 'ps-padma-child' );
			if ( $padma_child->exists() ) {
				$default_theme = 'ps-padma-child';
			} else {
				// Fallback to first available theme
				$themes = wp_get_themes();
				if ( ! empty( $themes ) ) {
					$first_theme = reset( $themes );
					$default_theme = $first_theme->get_stylesheet();
				}
			}
		}

		$themes = wp_get_themes();
		
		// Check if recommended themes are installed
		$padma_installed = wp_get_theme( 'ps-padma' )->exists();
		$padma_child_installed = wp_get_theme( 'ps-padma-child' )->exists();
		
		?>
		<div class="ps-manager-tool-settings">
			<p class="description">
				<?php esc_html_e( 'Wähle ein Theme aus, das automatisch bei neuen Blog-Registrierungen aktiviert wird.', 'ps-update-manager' ); ?>
			</p>

			<!-- Empfohlene Themes Section -->
			<div class="ps-recommended-themes-section">
				<h3>
				<span class="icon">★</span>
				<?php esc_html_e( 'Empfohlene Themes', 'ps-update-manager' ); ?>
			</h3>
			<p class="description">
				<?php esc_html_e( 'Diese Themes sind speziell für PSOURCE-Websites optimiert und werden für neue Blog-Registrierungen empfohlen.', 'ps-update-manager' ); ?>
			</p>

			<div class="ps-recommended-themes-grid">
				<div class="ps-theme-card <?php echo $padma_installed ? 'installed' : ''; ?>">
					<h4>
						<span class="icon">📐</span>
						PS Padma
						<?php if ( $padma_installed ) : ?>
							<span class="ps-status-badge">✓ Installiert</span>
						<?php endif; ?>
					</h4>
					<p class="ps-theme-description">
						<?php esc_html_e( 'Leistungsstarker Drag & Drop Pagebuilder für ClassicPress. Erstelle professionelle Websites mit visuellem Editor.', 'ps-update-manager' ); ?>
					</p>
					<?php if ( ! $padma_installed ) : ?>
						<button type="button" class="ps-install-btn" data-slug="ps-padma" data-repo="Power-Source/ps-padma">
							<span class="icon">⬇</span>
							<?php esc_html_e( 'Jetzt installieren', 'ps-update-manager' ); ?>
						</button>
					<?php else : ?>
						<button type="button" class="ps-install-btn" disabled>
							<span class="icon">✓</span>
							<?php esc_html_e( 'Installiert', 'ps-update-manager' ); ?>
						</button>
					<?php endif; ?>
				</div>

				<!-- PS Padma Child Card -->
				<div class="ps-theme-card <?php echo $padma_child_installed ? 'installed' : ''; ?>">
					<h4>
						<span class="icon">🎨</span>
						PS Padma Child
						<?php if ( $padma_child_installed ) : ?>
							<span class="ps-status-badge">✓ Installiert</span>
						<?php endif; ?>
					</h4>
					<p class="ps-theme-description">
						<?php esc_html_e( 'Sicheres Child-Theme für PS Padma. Perfekt für eigene Anpassungen und Custom Code, ohne Updates des Parent-Themes zu verlieren.', 'ps-update-manager' ); ?>
					</p>
					<?php if ( ! $padma_child_installed ) : ?>
						<button type="button" class="ps-install-btn" data-slug="ps-padma-child" data-repo="Power-Source/ps-padma-child">
							<span class="icon">⬇</span>
							<?php esc_html_e( 'Jetzt installieren', 'ps-update-manager' ); ?>
						</button>
					<?php else : ?>
						<button type="button" class="ps-install-btn" disabled>
							<span class="icon">✓</span>
							<?php esc_html_e( 'Installiert', 'ps-update-manager' ); ?>
						</button>
					<?php endif; ?>
				</div>
			</div>
		</div>

		<!-- Theme Selection Form -->
			<div class="ps-theme-selection-section">
				<h3><?php esc_html_e( 'Standard-Theme Auswahl', 'ps-update-manager' ); ?></h3>
				
				<form method="post" class="ps-manager-tool-form">
					<?php wp_nonce_field( 'ps_manager_tool_save', 'ps_manager_tool_nonce' ); ?>
					<input type="hidden" name="tool_id" value="<?php echo esc_attr( $this->id ); ?>">

					<table class="form-table">
						<tr>
							<th scope="row">
								<label for="default_theme">
									<?php esc_html_e( 'Standard-Theme', 'ps-update-manager' ); ?>
								</label>
							</th>
							<td>
								<select id="default_theme" name="default_theme" class="regular-text">
									<?php
									// Sort themes: ps-padma-child first, then others alphabetically
									$sorted_themes = array();
									$other_themes = array();
									
									foreach ( $themes as $theme_name => $theme ) {
										$theme_slug = $theme->get_stylesheet();
										if ( 'ps-padma-child' === $theme_slug ) {
											// Add ps-padma-child as first option
											$sorted_themes[$theme_name] = $theme;
										} else {
											$other_themes[$theme_name] = $theme;
										}
									}
									
									// Merge arrays: ps-padma-child first, then the rest
									$sorted_themes = $sorted_themes + $other_themes;
									
									foreach ( $sorted_themes as $theme_name => $theme ) {
										$theme_slug = $theme->get_stylesheet();
										$selected   = selected( $default_theme, $theme_slug, false );
										$is_recommended = ( 'ps-padma-child' === $theme_slug );
										
										echo '<option value="' . esc_attr( $theme_slug ) . '"' . $selected . '>';
										echo esc_html( $theme_name );
										if ( $is_recommended ) {
											echo ' [' . esc_html__( 'Empfohlen', 'ps-update-manager' ) . ']';
										}
										echo '</option>' . "\n";
									}
									?>
								</select>
								<p class="description">
									<?php esc_html_e( 'Dieses Theme wird bei neuen Blog-Registrierungen als Standard aktiviert.', 'ps-update-manager' ); ?>
								</p>
							</td>
						</tr>
					</table>

					<?php submit_button( __( 'Speichern', 'ps-update-manager' ), 'primary', 'submit', false ); ?>
				</form>
			</div>
		</div>
		<?php
	}

	/**
	 * Save tool settings
	 */
	public function save_settings() {
		if ( ! isset( $_POST['default_theme'] ) ) {
			return false;
		}

		$theme_slug = sanitize_text_field( $_POST['default_theme'] );
		$themes     = wp_get_themes();

		// Validate theme exists
		$valid = false;
		foreach ( $themes as $theme ) {
			if ( esc_html( $theme['Stylesheet'] ) === $theme_slug ) {
				$valid = true;
				break;
			}
		}

		if ( ! $valid ) {
			return false;
		}

		return update_site_option( 'ps_manager_default_theme', $theme_slug );
	}

	/**
	 * Activate default theme for new blog
	 */
	public function activate_default_theme_for_new_blog( $blog_id ) {
		$default_theme = get_site_option( 'ps_manager_default_theme' );

		if ( empty( $default_theme ) ) {
			return;
		}

		$themes = wp_get_themes();

		// Find theme by stylesheet
		foreach ( $themes as $theme ) {
			$stylesheet = esc_html( $theme['Stylesheet'] );
			$template   = esc_html( $theme['Template'] );

			if ( $default_theme === $stylesheet || $default_theme === $template ) {
				// Switch to blog and activate theme
				switch_to_blog( $blog_id );
				switch_theme( $template, $stylesheet );
				restore_current_blog();
				break;
			}
		}
	}

	/**
	 * Check if tool is available
	 */
	public function is_available() {
		// Only available in multisite
		if ( ! is_multisite() ) {
			return false;
		}

		return parent::is_available();
	}
}
