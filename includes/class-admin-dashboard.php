<?php
/**
 * Admin Dashboard Klasse
 * Erstellt die Verwaltungsseite für PS Update Manager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PS_Update_Manager_Admin_Dashboard {
	
	private static $instance = null;
	
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}
	
	private function __construct() {
		add_action( 'admin_menu', array( $this, 'add_menu' ) );
		add_action( 'network_admin_menu', array( $this, 'add_network_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'admin_init', array( $this, 'handle_settings_save' ) );
		
		// AJAX Handlers
		add_action( 'wp_ajax_ps_force_update_check', array( $this, 'ajax_force_update_check' ) );
	}
	
	/**
	 * Menü hinzufügen (Normal/Einzelsite)
	 */
	public function add_menu() {
		// Im Netzwerk-Modus: Menü nur im Netzwerk-Admin anzeigen
		if ( is_multisite() ) {
			return;
		}
		
		// Prüfe Zugriff
		if ( ! $this->current_user_can_access() ) {
			return;
		}
		
		add_menu_page(
			__( 'PS Update Manager', 'ps-update-manager' ),
			__( 'PS Updates', 'ps-update-manager' ),
			'manage_options',
			'ps-update-manager',
			array( $this, 'render_dashboard' ),
			'dashicons-update',
			59
		);
		
		add_submenu_page(
			'ps-update-manager',
			__( 'Dashboard', 'ps-update-manager' ),
			__( 'Dashboard', 'ps-update-manager' ),
			'manage_options',
			'ps-update-manager',
			array( $this, 'render_dashboard' )
		);
		
		add_submenu_page(
			'ps-update-manager',
			__( 'Alle Produkte', 'ps-update-manager' ),
			__( 'Alle Produkte', 'ps-update-manager' ),
			'manage_options',
			'ps-update-manager-products',
			array( $this, 'render_products' )
		);
	}
	
	/**
	 * Netzwerk-Admin-Menü hinzufügen
	 */
	public function add_network_menu() {
		// Nur im Netzwerk-Modus
		if ( ! is_multisite() ) {
			return;
		}
		
		// Nur für Netzwerk-Admins
		if ( ! is_super_admin() ) {
			return;
		}
		
		add_menu_page(
			__( 'PS Update Manager', 'ps-update-manager' ),
			__( 'PS Updates', 'ps-update-manager' ),
			'manage_network_options',
			'ps-update-manager',
			array( $this, 'render_dashboard' ),
			'dashicons-update',
			59
		);
		
		add_submenu_page(
			'ps-update-manager',
			__( 'Dashboard', 'ps-update-manager' ),
			__( 'Dashboard', 'ps-update-manager' ),
			'manage_network_options',
			'ps-update-manager',
			array( $this, 'render_dashboard' )
		);
		
		add_submenu_page(
			'ps-update-manager',
			__( 'Alle Produkte', 'ps-update-manager' ),
			__( 'Alle Produkte', 'ps-update-manager' ),
			'manage_network_options',
			'ps-update-manager-products',
			array( $this, 'render_products' )
		);
		
		add_submenu_page(
			'ps-update-manager',
			__( 'Einstellungen', 'ps-update-manager' ),
			__( 'Einstellungen', 'ps-update-manager' ),
			'manage_network_options',
			'ps-update-manager-settings',
			array( $this, 'render_settings' )
		);
	}
	
	/**
	 * Assets laden
	 */
	public function enqueue_assets( $hook ) {
		if ( strpos( $hook, 'ps-update-manager' ) === false ) {
			return;
		}
		
		wp_enqueue_style(
			'ps-update-manager-admin',
			PS_UPDATE_MANAGER_URL . 'assets/css/admin.css',
			array(),
			PS_UPDATE_MANAGER_VERSION
		);
		
		wp_enqueue_script(
			'ps-update-manager-admin',
			PS_UPDATE_MANAGER_URL . 'assets/js/admin.js',
			array( 'jquery' ),
			PS_UPDATE_MANAGER_VERSION,
			true
		);
		
		wp_localize_script( 'ps-update-manager-admin', 'psUpdateManager', array(
			'ajaxUrl' => admin_url( 'admin-ajax.php' ),
			'nonce'   => wp_create_nonce( 'ps_update_manager' ),
			'strings' => array(
				'checking' => __( 'Prüfe Updates...', 'ps-update-manager' ),
				'success'  => __( 'Update-Prüfung abgeschlossen!', 'ps-update-manager' ),
				'error'    => __( 'Fehler bei Update-Prüfung.', 'ps-update-manager' ),
			),
		) );
	}
	
	/**
	 * Prüfe ob aktueller Benutzer Zugriff auf Dashboard hat
	 */
	private function current_user_can_access() {
		return PS_Update_Manager_Settings::get_instance()->user_can_access_dashboard();
	}
	
	/**
	 * Handle-Funktion für Einstellungen-Speicherung
	 */
	public function handle_settings_save() {
		if ( ! is_multisite() || ! is_super_admin() ) {
			return;
		}
		
		if ( ! isset( $_POST['ps_update_manager_settings_nonce'] ) ) {
			return;
		}
		
		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['ps_update_manager_settings_nonce'] ) ), 'ps_update_manager_settings' ) ) {
			return;
		}
		
		if ( ! isset( $_POST['ps_update_manager_allowed_roles'] ) ) {
			return;
		}
		
		$roles = array_map( 'sanitize_key', wp_unslash( (array) $_POST['ps_update_manager_allowed_roles'] ) );
		PS_Update_Manager_Settings::get_instance()->update_setting( 'allowed_roles', $roles );
	}
	
	/**
	 * Dashboard rendern
	 */
	public function render_dashboard() {
		// Zugriffsprüfung
		if ( ! $this->current_user_can_access() ) {
			wp_die( esc_html__( 'Sie haben keine Berechtigung, um diese Seite anzuzeigen.', 'ps-update-manager' ) );
		}
		$products = PS_Update_Manager_Product_Registry::get_instance()->get_all();
		$updates_available = $this->count_available_updates( $products );
		
		?>
		<div class="wrap ps-update-manager-dashboard">
			<h1><?php esc_html_e( 'PS Update Manager', 'ps-update-manager' ); ?></h1>
			
			<div class="ps-dashboard-header">
				<div class="ps-stats">
					<div class="ps-stat-box">
						<div class="ps-stat-number"><?php echo count( $products ); ?></div>
						<div class="ps-stat-label"><?php esc_html_e( 'Registrierte Produkte', 'ps-update-manager' ); ?></div>
					</div>
					<div class="ps-stat-box">
						<div class="ps-stat-number"><?php echo $updates_available; ?></div>
						<div class="ps-stat-label"><?php esc_html_e( 'Updates verfügbar', 'ps-update-manager' ); ?></div>
					</div>
					<div class="ps-stat-box">
						<div class="ps-stat-number"><?php echo $this->count_active( $products ); ?></div>
						<div class="ps-stat-label"><?php esc_html_e( 'Aktive Produkte', 'ps-update-manager' ); ?></div>
					</div>
				</div>
				
				<div class="ps-actions">
					<button type="button" id="ps-force-check" class="button button-primary">
						<span class="dashicons dashicons-update"></span>
						<?php esc_html_e( 'Updates prüfen', 'ps-update-manager' ); ?>
					</button>
				</div>
			</div>
			
			<?php if ( $updates_available > 0 ) : ?>
				<div class="notice notice-info">
					<p>
						<strong><?php esc_html_e( 'Updates verfügbar!', 'ps-update-manager' ); ?></strong>
						<?php
						printf(
							esc_html__( 'Es sind %d Updates für deine PSource Produkte verfügbar.', 'ps-update-manager' ),
							$updates_available
						);
						?>
						<a href="<?php echo esc_url( admin_url( 'update-core.php' ) ); ?>" class="button button-small">
							<?php esc_html_e( 'Jetzt aktualisieren', 'ps-update-manager' ); ?>
						</a>
					</p>
				</div>
			<?php endif; ?>
			
			<div class="ps-products-overview">
				<h2><?php esc_html_e( 'Produkt-Übersicht', 'ps-update-manager' ); ?></h2>
				
				<div class="ps-products-grid">
					<?php foreach ( $products as $product ) : 
						$update_info = $this->get_product_update_info( $product );
						$has_update = $update_info && version_compare( $update_info['version'], $product['version'], '>' );
					?>
						<div class="ps-product-card <?php echo $has_update ? 'has-update' : ''; ?>">
							<div class="ps-product-header">
								<h3><?php echo esc_html( $product['name'] ); ?></h3>
								<span class="ps-product-version">v<?php echo esc_html( $product['version'] ); ?></span>
							</div>
							
							<div class="ps-product-meta">
								<span class="ps-product-type">
									<span class="dashicons dashicons-<?php echo 'plugin' === $product['type'] ? 'admin-plugins' : 'admin-appearance'; ?>"></span>
									<?php echo esc_html( ucfirst( $product['type'] ) ); ?>
								</span>
								<span class="ps-product-status <?php echo $product['is_active'] ? 'active' : 'inactive'; ?>">
									<?php echo $product['is_active'] ? __( 'Aktiv', 'ps-update-manager' ) : __( 'Inaktiv', 'ps-update-manager' ); ?>
								</span>
							</div>
							
							<?php if ( $has_update ) : ?>
								<div class="ps-update-badge">
									<span class="dashicons dashicons-update-alt"></span>
									<?php printf( __( 'Update auf v%s verfügbar', 'ps-update-manager' ), esc_html( $update_info['version'] ) ); ?>
								</div>
							<?php endif; ?>
							
							<div class="ps-product-links">
								<?php if ( ! empty( $product['docs_url'] ) ) : ?>
									<a href="<?php echo esc_url( $product['docs_url'] ); ?>" target="_blank" class="button button-small">
										<span class="dashicons dashicons-book"></span>
										<?php esc_html_e( 'Docs', 'ps-update-manager' ); ?>
									</a>
								<?php endif; ?>
								
								<?php if ( ! empty( $product['support_url'] ) ) : ?>
									<a href="<?php echo esc_url( $product['support_url'] ); ?>" target="_blank" class="button button-small">
										<span class="dashicons dashicons-sos"></span>
										<?php esc_html_e( 'Support', 'ps-update-manager' ); ?>
									</a>
								<?php endif; ?>
								
								<?php if ( ! empty( $product['github_repo'] ) ) : ?>
									<a href="<?php echo esc_url( 'https://github.com/' . $product['github_repo'] ); ?>" target="_blank" class="button button-small">
										<span class="dashicons dashicons-github"></span>
										<?php esc_html_e( 'GitHub', 'ps-update-manager' ); ?>
									</a>
								<?php endif; ?>
							</div>
						</div>
					<?php endforeach; ?>
				</div>
			</div>
			
			<div class="ps-info-box">
				<h3><?php esc_html_e( '💡 Über PS Update Manager', 'ps-update-manager' ); ?></h3>
				<p>
					<?php esc_html_e( 'Der PS Update Manager ist deine zentrale Anlaufstelle für alle PSource Plugins und Themes. Updates werden automatisch von GitHub oder deinem eigenen Server abgerufen.', 'ps-update-manager' ); ?>
				</p>
				<p>
					<strong><?php esc_html_e( 'Open Source & Community:', 'ps-update-manager' ); ?></strong><br>
					<?php esc_html_e( 'Alle PSource Produkte sind Open Source. Du kannst jederzeit beitragen, Issues melden oder Features vorschlagen.', 'ps-update-manager' ); ?>
				</p>
				<p>
					<a href="https://github.com/cp-psource" target="_blank" class="button">
						<?php esc_html_e( 'Auf GitHub mitarbeiten', 'ps-update-manager' ); ?>
					</a>
				</p>
			</div>
		</div>
		<?php
	}
	
	/**
	 * Produkte-Seite rendern
	 */
	public function render_products() {
		// Zugriffsprüfung
		if ( ! $this->current_user_can_access() ) {
			wp_die( esc_html__( 'Sie haben keine Berechtigung, um diese Seite anzuzeigen.', 'ps-update-manager' ) );
		}
		$products = PS_Update_Manager_Product_Registry::get_instance()->get_all();
		
		?>
		<div class="wrap ps-update-manager-products">
			<h1><?php esc_html_e( 'Alle PS Produkte', 'ps-update-manager' ); ?></h1>
			
			<table class="wp-list-table widefat fixed striped">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Name', 'ps-update-manager' ); ?></th>
						<th><?php esc_html_e( 'Version', 'ps-update-manager' ); ?></th>
						<th><?php esc_html_e( 'Typ', 'ps-update-manager' ); ?></th>
						<th><?php esc_html_e( 'Status', 'ps-update-manager' ); ?></th>
						<th><?php esc_html_e( 'Update', 'ps-update-manager' ); ?></th>
						<th><?php esc_html_e( 'Links', 'ps-update-manager' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $products as $product ) : 
						$update_info = $this->get_product_update_info( $product );
						$has_update = $update_info && version_compare( $update_info['version'], $product['version'], '>' );
					?>
						<tr>
							<td><strong><?php echo esc_html( $product['name'] ); ?></strong></td>
							<td><?php echo esc_html( $product['version'] ); ?></td>
							<td><?php echo esc_html( ucfirst( $product['type'] ) ); ?></td>
							<td>
								<?php if ( $product['is_active'] ) : ?>
									<span class="dashicons dashicons-yes-alt" style="color: #46b450;"></span> <?php esc_html_e( 'Aktiv', 'ps-update-manager' ); ?>
								<?php else : ?>
									<span class="dashicons dashicons-marker" style="color: #999;"></span> <?php esc_html_e( 'Inaktiv', 'ps-update-manager' ); ?>
								<?php endif; ?>
							</td>
							<td>
								<?php if ( $has_update ) : ?>
									<span style="color: #d63638;">
										<span class="dashicons dashicons-update-alt"></span>
										v<?php echo esc_html( $update_info['version'] ); ?>
									</span>
								<?php else : ?>
									<span style="color: #46b450;">
										<?php esc_html_e( 'Aktuell', 'ps-update-manager' ); ?>
									</span>
								<?php endif; ?>
							</td>
							<td>
								<?php if ( ! empty( $product['docs_url'] ) ) : ?>
									<a href="<?php echo esc_url( $product['docs_url'] ); ?>" target="_blank"><?php esc_html_e( 'Docs', 'ps-update-manager' ); ?></a> |
								<?php endif; ?>
								<?php if ( ! empty( $product['support_url'] ) ) : ?>
									<a href="<?php echo esc_url( $product['support_url'] ); ?>" target="_blank"><?php esc_html_e( 'Support', 'ps-update-manager' ); ?></a> |
								<?php endif; ?>
								<?php if ( ! empty( $product['github_repo'] ) ) : ?>
									<a href="<?php echo esc_url( 'https://github.com/' . $product['github_repo'] ); ?>" target="_blank">GitHub</a>
								<?php endif; ?>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
		<?php
	}
	
	/**
	 * Update-Info für Produkt abrufen
	 */
	private function get_product_update_info( $product ) {
		if ( empty( $product['github_repo'] ) ) {
			return false;
		}
		
		$github = PS_Update_Manager_GitHub_API::get_instance();
		$release = $github->get_latest_release( $product['github_repo'] );
		
		return is_wp_error( $release ) ? false : $release;
	}
	
	/**
	 * Einstellungen-Seite rendern
	 */
	public function render_settings() {
		// Zugriffsprüfung - nur Netzwerk-Admin
		if ( ! is_multisite() || ! is_super_admin() ) {
			wp_die( esc_html__( 'Sie haben keine Berechtigung, um diese Seite anzuzeigen.', 'ps-update-manager' ) );
		}
		
		$settings = PS_Update_Manager_Settings::get_instance();
		$allowed_roles = $settings->get_setting( 'allowed_roles' );
		$available_roles = $settings->get_available_roles();
		?>
		<div class="wrap ps-update-manager-settings">
			<h1><?php esc_html_e( 'PS Update Manager - Einstellungen', 'ps-update-manager' ); ?></h1>
			
			<div class="ps-settings-container">
				<form method="post" action="">
					<?php wp_nonce_field( 'ps_update_manager_settings', 'ps_update_manager_settings_nonce' ); ?>
					
					<div class="ps-settings-section">
						<h2><?php esc_html_e( 'Zugriffsrechte', 'ps-update-manager' ); ?></h2>
						<p class="description">
							<?php esc_html_e( 'Wählen Sie aus, welche Benutzerrollen das Dashboard sehen und verwenden dürfen.', 'ps-update-manager' ); ?>
						</p>
						
						<table class="ps-roles-table">
							<tbody>
								<?php foreach ( $available_roles as $role_slug => $role_data ) : ?>
									<tr>
										<td>
											<label>
												<input type="checkbox" 
													name="ps_update_manager_allowed_roles[]" 
													value="<?php echo esc_attr( $role_slug ); ?>"
													<?php checked( in_array( $role_slug, $allowed_roles, true ) ); ?>
												>
												<strong><?php echo esc_html( $role_data['name'] ); ?></strong>
											</label>
										</td>
										<td class="ps-role-caption">
											<em><?php echo esc_html( $role_slug ); ?></em>
										</td>
									</tr>
								<?php endforeach; ?>
							</tbody>
						</table>
					</div>
					
					<?php submit_button( __( 'Einstellungen speichern', 'ps-update-manager' ), 'primary', 'submit', true ); ?>
				</form>
			</div>
			
			<div class="ps-info-box">
				<h3><?php esc_html_e( '💡 Hinweise', 'ps-update-manager' ); ?></h3>
				<ul>
					<li><?php esc_html_e( 'Der Netzwerk-Administrator hat immer Zugriff auf das Dashboard.', 'ps-update-manager' ); ?></li>
					<li><?php esc_html_e( 'Wählen Sie mindestens eine Rolle aus, damit andere Benutzer Zugriff haben.', 'ps-update-manager' ); ?></li>
					<li><?php esc_html_e( 'Diese Einstellung gilt netzwerkweit für alle Seiten.', 'ps-update-manager' ); ?></li>
				</ul>
			</div>
		</div>
		
		<style>
			.ps-settings-container {
				max-width: 800px;
				margin-top: 20px;
				background: white;
				padding: 20px;
				border-radius: 4px;
				box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
			}
			
			.ps-settings-section {
				margin-bottom: 30px;
			}
			
			.ps-settings-section h2 {
				margin-top: 0;
				margin-bottom: 15px;
				font-size: 18px;
				color: #333;
			}
			
			.ps-settings-section .description {
				margin-bottom: 20px;
				color: #666;
			}
			
			.ps-roles-table {
				width: 100%;
				border-collapse: collapse;
			}
			
			.ps-roles-table td {
				padding: 12px 10px;
				border-bottom: 1px solid #eee;
			}
			
			.ps-roles-table tr:last-child td {
				border-bottom: none;
			}
			
			.ps-roles-table td:first-child {
				flex: 1;
			}
			
			.ps-role-caption {
				text-align: right;
				width: 200px;
				color: #999;
				font-size: 12px;
			}
			
			.ps-roles-table input[type="checkbox"] {
				margin-right: 8px;
			}
			
			.ps-info-box {
				margin-top: 30px;
				background: #f0f6fc;
				padding: 15px 20px;
				border-left: 4px solid #0073aa;
				border-radius: 4px;
			}
			
			.ps-info-box h3 {
				margin-top: 0;
				margin-bottom: 10px;
				color: #0073aa;
			}
			
			.ps-info-box ul {
				margin: 10px 0;
				padding-left: 20px;
			}
			
			.ps-info-box li {
				margin: 5px 0;
			}
		</style>
		<?php
	}
	
	/**
	 * Verfügbare Updates zählen
	 */
	private function count_available_updates( $products ) {
		$count = 0;
		foreach ( $products as $product ) {
			$update_info = $this->get_product_update_info( $product );
			if ( $update_info && version_compare( $update_info['version'], $product['version'], '>' ) ) {
				$count++;
			}
		}
		return $count;
	}
	
	/**
	 * Aktive Produkte zählen
	 */
	private function count_active( $products ) {
		return count( array_filter( $products, function( $p ) {
			return $p['is_active'];
		} ) );
	}
	
	/**
	 * AJAX: Update-Check erzwingen
	 */
	public function ajax_force_update_check() {
		check_ajax_referer( 'ps_update_manager', 'nonce' );
		
		if ( ! $this->current_user_can_access() ) {
			wp_send_json_error( array( 'message' => __( 'Keine Berechtigung', 'ps-update-manager' ) ) );
		}
		
		PS_Update_Manager_Update_Checker::get_instance()->force_check();
		
		wp_send_json_success( array(
			'message' => __( 'Update-Prüfung abgeschlossen!', 'ps-update-manager' ),
		) );
	}
}
