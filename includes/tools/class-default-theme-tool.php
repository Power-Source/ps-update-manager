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
		$this->name        = __( 'Standard Theme', 'ps-manager' );
		$this->description = __( 'Standardtheme für neue Blog-Registrierungen festlegen', 'ps-manager' );
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
		$default_theme = get_site_option( 'ps_manager_default_theme' );
		if ( empty( $default_theme ) ) {
			$default_theme = 'default';
		}

		$themes = wp_get_themes();
		?>
		<div class="ps-manager-tool-settings">
			<p class="description">
				<?php esc_html_e( 'Wähle ein Theme aus, das automatisch bei neuen Blog-Registrierungen aktiviert wird.', 'ps-manager' ); ?>
			</p>

			<form method="post" class="ps-manager-tool-form">
				<?php wp_nonce_field( 'ps_manager_tool_save', 'ps_manager_tool_nonce' ); ?>
				<input type="hidden" name="tool_id" value="<?php echo esc_attr( $this->id ); ?>">

				<table class="form-table">
					<tr>
						<th scope="row">
							<label for="default_theme">
								<?php esc_html_e( 'Standard Theme', 'ps-manager' ); ?>
							</label>
						</th>
						<td>
							<select id="default_theme" name="default_theme" class="regular-text">
								<?php
								foreach ( $themes as $theme_name => $theme ) {
									$theme_slug = esc_html( $theme['Stylesheet'] );
									$selected   = selected( $default_theme, $theme_slug, false );
									echo '<option value="' . esc_attr( $theme_slug ) . '"' . $selected . '>' . esc_html( $theme_name ) . '</option>' . "\n";
								}
								?>
							</select>
							<p class="description">
								<?php esc_html_e( 'Dieses Theme wird bei neuen Blog-Registrierungen als Standard aktiviert.', 'ps-manager' ); ?>
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
