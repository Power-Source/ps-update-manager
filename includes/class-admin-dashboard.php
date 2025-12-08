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
		add_action( 'admin_init', array( $this, 'maybe_redirect_legacy_products_page' ) );
		add_action( 'admin_init', array( $this, 'cleanup_orphaned_products' ) );
		
		// AJAX Handlers
		add_action( 'wp_ajax_ps_force_update_check', array( $this, 'ajax_force_update_check' ) );
		add_action( 'wp_ajax_ps_install_product', array( $this, 'ajax_install_product' ) );
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
			__( 'PSOURCE Manager', 'ps-update-manager' ),
			__( 'PS Manager', 'ps-update-manager' ),
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
			__( 'PSOURCE Katalog', 'ps-update-manager' ),
			__( 'PSOURCE', 'ps-update-manager' ),
			'manage_options',
			'ps-update-manager-psources',
			array( $this, 'render_products' )
		);
		
		add_submenu_page(
			'ps-update-manager',
			__( 'Tools', 'ps-update-manager' ),
			__( 'Tools', 'ps-update-manager' ),
			'manage_options',
			'ps-update-manager-tools',
			array( $this, 'render_tools' )
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
			__( 'PSOURCE Manager', 'ps-update-manager' ),
			__( 'PS MANAGER', 'ps-update-manager' ),
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
			__( 'PSOURCE Katalog', 'ps-update-manager' ),
			__( 'PSOURCE', 'ps-update-manager' ),
			'manage_network_options',
			'ps-update-manager-psources',
			array( $this, 'render_products' )
		);
		
		add_submenu_page(
			'ps-update-manager',
			__( 'Tools', 'ps-update-manager' ),
			__( 'Tools', 'ps-update-manager' ),
			'manage_network_options',
			'ps-update-manager-tools',
			array( $this, 'render_tools' )
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
	 * Weiterleitung alter Produkte-URL auf neuen PSOURCE-Slug
	 */
	public function maybe_redirect_legacy_products_page() {
		if ( ! isset( $_GET['page'] ) ) {
			return;
		}

		$page = sanitize_key( wp_unslash( $_GET['page'] ) );

		if ( 'ps-update-manager-products' !== $page ) {
			return;
		}

		$target = is_network_admin()
			? network_admin_url( 'admin.php?page=ps-update-manager-psources' )
			: admin_url( 'admin.php?page=ps-update-manager-psources' );

		wp_safe_redirect( $target );
		exit;
	}
	
	/**
	 * Bereinige verwaiste Produkte die nicht mehr im Manifest sind
	 */
	public function cleanup_orphaned_products() {
		if ( ! is_multisite() || ! is_super_admin() ) {
			return;
		}
		
		$registry = PS_Update_Manager_Product_Registry::get_instance();
		$scanner = PS_Update_Manager_Product_Scanner::get_instance();
		
		// Offizielle Produkte aus Manifest abrufen
		$official_products = $scanner->get_official_products();
		$official_slugs = array_keys( $official_products );
		
		// Alle registrierten Produkte durchgehen
		$all_products = $registry->get_all();
		
		foreach ( $all_products as $slug => $product ) {
			// Wenn Produkt nicht im Manifest und nicht installiert, löschen
			if ( ! in_array( $slug, $official_slugs, true ) && ! $product['is_active'] ) {
				$registry->unregister( $slug );
			}
		}
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
		
		// Scanner-Info
		$scanner = PS_Update_Manager_Product_Scanner::get_instance();
		
		// PERFORMANCE: Nur alle 5 Minuten neu scannen (verhindert Slowdown bei häufigen Page-Loads)
		$last_scan_time = get_transient( 'ps_last_scan_time' );
		$current_time = current_time( 'timestamp' );
		
		if ( ! $last_scan_time || ( $current_time - $last_scan_time ) > 300 ) {
			$scanner->scan_all();
			$last_scan = $current_time;
		} else {
			$last_scan = $last_scan_time;
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
						<div class="ps-stat-label"><?php esc_html_e( 'Gefundene PSOURCE', 'ps-update-manager' ); ?></div>
					</div>
					<div class="ps-stat-box">
						<div class="ps-stat-number"><?php echo $updates_available; ?></div>
						<div class="ps-stat-label"><?php esc_html_e( 'Updates verfügbar', 'ps-update-manager' ); ?></div>
					</div>
					<div class="ps-stat-box">
						<div class="ps-stat-number"><?php echo $this->count_active( $products ); ?></div>
						<div class="ps-stat-label"><?php esc_html_e( 'Aktive PSOURCE', 'ps-update-manager' ); ?></div>
					</div>
				</div>
				
				<div class="ps-actions">
					<button type="button" id="ps-force-check" class="button button-primary">
						<span class="dashicons dashicons-update"></span>
						<?php esc_html_e( 'Updates prüfen', 'ps-update-manager' ); ?>
					</button>
				</div>
			</div>
			
			<?php if ( $last_scan ) : ?>
				<p class="ps-scan-info">
					<?php
					printf(
						esc_html__( 'Letzter Scan: %s', 'ps-update-manager' ),
						human_time_diff( $last_scan, current_time( 'timestamp' ) ) . ' ' . __( 'ago', 'ps-update-manager' )
					);
					?>
				</p>
			<?php endif; ?>
			
			<?php if ( $updates_available > 0 ) : ?>
				<div class="notice notice-info">
					<p>
						<strong><?php esc_html_e( 'Updates verfügbar!', 'ps-update-manager' ); ?></strong>
						<?php
						printf(
							esc_html__( 'Es sind %d Updates für deine PSOURCE-Installationen verfügbar.', 'ps-update-manager' ),
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
				<h2><?php esc_html_e( 'PSOURCE-Übersicht', 'ps-update-manager' ); ?></h2>
				
				<div class="ps-products-grid">
					<?php foreach ( $products as $product ) : 
						$update_info = $this->get_product_update_info( $product );
						$has_update = $update_info && version_compare( $update_info['version'], $product['version'], '>' );
					?>
						<div class="ps-product-card <?php echo $has_update ? 'has-update' : ''; ?>">
							<div class="ps-product-header">
								<h3>
									<?php echo esc_html( $product['name'] ); ?>
									<?php if ( isset( $product['discovered'] ) && $product['discovered'] ) : ?>
										<span class="ps-badge-discovered" title="<?php esc_attr_e( 'Automatisch erkannt', 'ps-update-manager' ); ?>">Auto</span>
									<?php endif; ?>
								</h3>
								<span class="ps-product-version">v<?php echo esc_html( $product['version'] ); ?></span>
							</div>
							
							<div class="ps-product-meta">
								<span class="ps-product-type">
									<span class="dashicons dashicons-<?php echo 'plugin' === $product['type'] ? 'admin-plugins' : 'admin-appearance'; ?>"></span>
									<?php echo esc_html( ucfirst( $product['type'] ) ); ?>
								</span>
								<span class="ps-product-status <?php echo $product['is_active'] ? 'active' : 'inactive'; ?>">
									<?php 
									// Multisite: Zeige ob netzwerkweit aktiv
									if ( is_multisite() && $product['is_active'] && isset( $product['basename'] ) && is_plugin_active_for_network( $product['basename'] ) ) {
										echo '<span class="dashicons dashicons-networking" style="font-size: 14px; width: 14px; height: 14px;"></span> ';
										esc_html_e( 'Netzwerkweit aktiv', 'ps-update-manager' );
									} else {
										echo $product['is_active'] ? __( 'Aktiv', 'ps-update-manager' ) : __( 'Inaktiv', 'ps-update-manager' );
									}
									?>
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
					<?php esc_html_e( 'Alle PSOURCE Projekte sind Open Source. Du kannst jederzeit beitragen, Issues melden oder Features vorschlagen.', 'ps-update-manager' ); ?>
				</p>
				<p>
					<a href="https://github.com/Power-Source" target="_blank" class="button">
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
		
		// Scanner: PERFORMANCE - Nur alle 5 Minuten neu scannen
		$scanner = PS_Update_Manager_Product_Scanner::get_instance();
		$last_scan_time = get_transient( 'ps_last_scan_time' );
		$current_time = current_time( 'timestamp' );
		
		// Force-Scan wenn ?force_scan=1 im URL
		$force_scan = isset( $_GET['force_scan'] ) && '1' === sanitize_key( $_GET['force_scan'] );
		
		if ( $force_scan || ! $last_scan_time || ( $current_time - $last_scan_time ) > 300 ) {
			if ( $force_scan ) {
				// Alle Transients löschen für kompletten Neustart
				delete_transient( 'ps_last_scan_time' );
				delete_transient( 'ps_discovered_products' );
				delete_transient( 'ps_update_manager_products_cache' );
				delete_transient( 'ps_update_manager_status_cache' );
			}
			$scanner->scan_all();
		}
		
		// Alle offiziellen Produkte aus Manifest
		$official_products = $scanner->get_official_products();
		
		// Registrierte/installierte Produkte
		$registry = PS_Update_Manager_Product_Registry::get_instance();
		$installed_products = $registry->get_all();
		
		// Zusammenführen: Manifest + Installation Status
		$all_products = array();
		
		foreach ( $official_products as $slug => $manifest ) {
			$product = array(
				'slug'        => $slug,
				'name'        => $manifest['name'],
				'description' => $manifest['description'],
				'type'        => $manifest['type'],
				'repo'        => $manifest['repo'],
				'icon'        => $manifest['icon'] ?? 'dashicons-admin-plugins',
				'category'    => $manifest['category'] ?? 'general',
				'installed'   => false,
				'active'      => false,
				'version'     => null,
				'update_available' => false,
				'new_version' => null,
			);
			
			// Prüfe ob installiert
			if ( isset( $installed_products[ $slug ] ) ) {
				$installed = $installed_products[ $slug ];
				$product['installed'] = true;
				$product['active'] = $installed['is_active'];
				$product['version'] = $installed['version'];
				
				// Update verfügbar?
				$update_info = $this->get_product_update_info( $installed );
				if ( $update_info && version_compare( $update_info['version'], $installed['version'], '>' ) ) {
					$product['update_available'] = true;
					$product['new_version'] = $update_info['version'];
				}
			}
			
			$all_products[ $slug ] = $product;
		}
		
		?>
		<div class="wrap ps-update-manager-psources">
			<h1><?php esc_html_e( 'PSOURCE Katalog', 'ps-update-manager' ); ?></h1>
			<p class="description">
				<?php esc_html_e( 'Entdecke und installiere offizielle PSOURCE Plugins und Themes aus dem Power-Source Repository.', 'ps-update-manager' ); ?>
			</p>
			
			<div class="ps-products-store">
				<?php foreach ( $all_products as $slug => $product ) : ?>
					<div class="ps-store-card" data-slug="<?php echo esc_attr( $slug ); ?>">
						<div class="ps-store-card-header">
							<span class="ps-store-icon">
								<span class="dashicons <?php echo esc_attr( $product['icon'] ); ?>"></span>
							</span>
							<div class="ps-store-title">
								<h3><?php echo esc_html( $product['name'] ); ?></h3>
								<span class="ps-store-meta">
									<?php echo esc_html( ucfirst( $product['type'] ) ); ?> 
									<?php if ( $product['version'] ) : ?>
										· v<?php echo esc_html( $product['version'] ); ?>
									<?php endif; ?>
								</span>
							</div>
							
							<div class="ps-store-status">
								<?php if ( ! $product['installed'] ) : ?>
									<span class="ps-badge ps-badge-not-installed"><?php esc_html_e( 'Nicht installiert', 'ps-update-manager' ); ?></span>
								<?php elseif ( $product['update_available'] ) : ?>
									<span class="ps-badge ps-badge-update"><?php printf( __( 'Update: v%s', 'ps-update-manager' ), esc_html( $product['new_version'] ) ); ?></span>
								<?php elseif ( $product['active'] ) : ?>
									<?php
									// Multisite: Prüfe ob netzwerkweit aktiv
									$is_network_active = false;
									if ( is_multisite() && isset( $installed_products[ $slug ]['basename'] ) ) {
										$is_network_active = is_plugin_active_for_network( $installed_products[ $slug ]['basename'] );
									}
									?>
									<?php if ( $is_network_active ) : ?>
										<span class="ps-badge ps-badge-network-active" title="<?php esc_attr_e( 'Netzwerkweit aktiviert', 'ps-update-manager' ); ?>">
											<span class="dashicons dashicons-networking" style="font-size: 14px; width: 14px; height: 14px; vertical-align: middle;"></span>
											<?php esc_html_e( 'Netzwerkweit', 'ps-update-manager' ); ?>
										</span>
									<?php else : ?>
										<span class="ps-badge ps-badge-active"><?php esc_html_e( 'Aktiv', 'ps-update-manager' ); ?></span>
									<?php endif; ?>
								<?php else : ?>
									<span class="ps-badge ps-badge-inactive"><?php esc_html_e( 'Inaktiv', 'ps-update-manager' ); ?></span>
								<?php endif; ?>
							</div>
						</div>
						
						<div class="ps-store-card-body">
							<p class="ps-store-description"><?php echo esc_html( $product['description'] ); ?></p>
							
							<div class="ps-store-links">
								<a href="https://github.com/<?php echo esc_attr( $product['repo'] ); ?>" target="_blank" class="ps-link">
									<span class="dashicons dashicons-admin-site"></span> GitHub
								</a>
								<a href="https://github.com/<?php echo esc_attr( $product['repo'] ); ?>/issues" target="_blank" class="ps-link">
									<span class="dashicons dashicons-sos"></span> Support
								</a>
								<a href="https://github.com/<?php echo esc_attr( $product['repo'] ); ?>/releases" target="_blank" class="ps-link">
									<span class="dashicons dashicons-media-document"></span> Changelog
								</a>
							</div>
						</div>
						
						<div class="ps-store-card-footer">
							<?php if ( ! $product['installed'] ) : ?>
								<button class="button button-primary ps-install-product" data-slug="<?php echo esc_attr( $slug ); ?>" data-repo="<?php echo esc_attr( $product['repo'] ); ?>" data-type="<?php echo esc_attr( $product['type'] ); ?>">
									<span class="dashicons dashicons-download"></span>
									<?php esc_html_e( 'Installieren', 'ps-update-manager' ); ?>
								</button>
							<?php elseif ( $product['update_available'] ) : ?>
								<a href="<?php echo esc_url( admin_url( 'update-core.php' ) ); ?>" class="button button-primary">
									<span class="dashicons dashicons-update"></span>
									<?php esc_html_e( 'Jetzt aktualisieren', 'ps-update-manager' ); ?>
								</a>
							<?php elseif ( ! $product['active'] && 'plugin' === $product['type'] ) : ?>
								<?php
								// Basename aus Registry holen (z.B. ps-chat/psource-chat.php)
								$plugin_basename = isset( $installed_products[ $slug ]['basename'] ) 
									? $installed_products[ $slug ]['basename'] 
									: $slug . '/' . $slug . '.php'; // Fallback
								
								// Network-Modus aus Registry holen
								$network_only = isset( $installed_products[ $slug ]['network_only'] ) && $installed_products[ $slug ]['network_only'];
								$network_mode = isset( $installed_products[ $slug ]['network_mode'] ) ? $installed_products[ $slug ]['network_mode'] : 'none';
								
								// Multisite: Prüfe ob wir im Network Admin sind
								if ( is_multisite() && is_network_admin() ) {
									// Prüfe ob Plugin netzwerkweit aktivierbar ist
									if ( 'none' === $network_mode ) {
										// Site-Only Plugin - nicht im Netzwerk-Admin aktivierbar
										?>
										<button class="button" disabled>
											<span class="dashicons dashicons-admin-site"></span>
											<?php esc_html_e( 'Nur Unterseiten', 'ps-update-manager' ); ?>
										</button>
										<p class="description" style="margin-top: 8px; font-size: 12px; color: #2271b1;">
											<span class="dashicons dashicons-info" style="font-size: 14px;"></span>
											<?php esc_html_e( 'Nur auf Unterseiten-Ebene aktivierbar (nicht netzwerkweit).', 'ps-update-manager' ); ?>
										</p>
										<?php
									} else {
										// Netzwerkweite Aktivierung möglich
										$activate_url = wp_nonce_url(
											network_admin_url( 'plugins.php?action=activate&plugin=' . urlencode( $plugin_basename ) ),
											'activate-plugin_' . $plugin_basename
										);
										?>
										<a href="<?php echo esc_url( $activate_url ); ?>" class="button button-primary">
											<span class="dashicons dashicons-yes"></span>
											<?php esc_html_e( 'Netzwerkweit aktivieren', 'ps-update-manager' ); ?>
										</a>
										<?php if ( 'wordpress-network' === $network_mode ) : ?>
											<p class="description" style="margin-top: 8px; font-size: 12px; color: #d63638;">
												<span class="dashicons dashicons-info" style="font-size: 14px;"></span>
												<?php esc_html_e( 'Dieses Plugin kann nur netzwerkweit aktiviert werden.', 'ps-update-manager' ); ?>
											</p>
										<?php elseif ( 'multisite-required' === $network_mode ) : ?>
											<p class="description" style="margin-top: 8px; font-size: 12px; color: #2271b1;">
												<span class="dashicons dashicons-info" style="font-size: 14px;"></span>
												<?php esc_html_e( 'Auf Multisite nur netzwerkweit aktivierbar. Auf Single-Sites normal nutzbar.', 'ps-update-manager' ); ?>
											</p>
										<?php elseif ( 'flexible' === $network_mode ) : ?>
											<p class="description" style="margin-top: 8px; font-size: 12px;">
												<?php esc_html_e( 'Kann auch site-by-site im Site-Admin aktiviert werden.', 'ps-update-manager' ); ?>
											</p>
										<?php endif; ?>
									<?php
									}
								} elseif ( is_multisite() && ! $network_only ) {
									// Site-Admin auf Multisite: Nur wenn Plugin nicht network-only ist
									$activate_url = wp_nonce_url(
										admin_url( 'plugins.php?action=activate&plugin=' . urlencode( $plugin_basename ) ),
										'activate-plugin_' . $plugin_basename
									);
									?>
									<a href="<?php echo esc_url( $activate_url ); ?>" class="button button-primary">
										<span class="dashicons dashicons-yes"></span>
										<?php esc_html_e( 'Aktivieren', 'ps-update-manager' ); ?>
									</a>
									<p class="description" style="margin-top: 8px; font-size: 12px;">
										<?php esc_html_e( 'Aktiviert das Plugin nur für diese Site.', 'ps-update-manager' ); ?>
									</p>
								<?php } elseif ( is_multisite() && $network_only ) {
									// Site-Admin, aber Plugin ist network-only
									?>
									<button class="button" disabled>
										<span class="dashicons dashicons-lock"></span>
										<?php esc_html_e( 'Nur Netzwerk-Admin', 'ps-update-manager' ); ?>
									</button>
									<p class="description" style="margin-top: 8px; font-size: 12px; color: #d63638;">
										<span class="dashicons dashicons-info" style="font-size: 14px;"></span>
										<?php esc_html_e( 'Dieses Plugin kann nur im Netzwerk-Admin aktiviert werden.', 'ps-update-manager' ); ?>
									</p>
								<?php } else {
									// Single-Site Standard-Aktivierung
									$activate_url = wp_nonce_url(
										admin_url( 'plugins.php?action=activate&plugin=' . urlencode( $plugin_basename ) ),
										'activate-plugin_' . $plugin_basename
									);
									?>
									<a href="<?php echo esc_url( $activate_url ); ?>" class="button button-primary">
										<span class="dashicons dashicons-yes"></span>
										<?php esc_html_e( 'Aktivieren', 'ps-update-manager' ); ?>
									</a>
								<?php } ?>
							<?php elseif ( $product['active'] && 'plugin' === $product['type'] ) : ?>
								<?php
								// Basename aus Registry holen
								$plugin_basename = isset( $installed_products[ $slug ]['basename'] ) 
									? $installed_products[ $slug ]['basename'] 
									: $slug . '/' . $slug . '.php';
								
								// Prüfe ob netzwerkweit aktiv
								$is_network_active = is_multisite() && is_plugin_active_for_network( $plugin_basename );
								
								// Deaktivierungs-URLs
								if ( is_multisite() && is_network_admin() && $is_network_active ) {
									// Netzwerkweite Deaktivierung
									$deactivate_url = wp_nonce_url(
										network_admin_url( 'plugins.php?action=deactivate&plugin=' . urlencode( $plugin_basename ) ),
										'deactivate-plugin_' . $plugin_basename
									);
									?>
									<a href="<?php echo esc_url( $deactivate_url ); ?>" class="button">
										<span class="dashicons dashicons-dismiss"></span>
										<?php esc_html_e( 'Netzwerkweit deaktivieren', 'ps-update-manager' ); ?>
									</a>
								<?php } elseif ( is_multisite() && ! is_network_admin() && ! $is_network_active ) {
									// Site-spezifische Deaktivierung
									$deactivate_url = wp_nonce_url(
										admin_url( 'plugins.php?action=deactivate&plugin=' . urlencode( $plugin_basename ) ),
										'deactivate-plugin_' . $plugin_basename
									);
									?>
									<a href="<?php echo esc_url( $deactivate_url ); ?>" class="button">
										<span class="dashicons dashicons-dismiss"></span>
										<?php esc_html_e( 'Deaktivieren', 'ps-update-manager' ); ?>
									</a>
								<?php } elseif ( is_multisite() && ! is_network_admin() && $is_network_active ) {
									// Im Site-Admin, aber netzwerkweit aktiv - kann hier nicht deaktiviert werden
									?>
									<button class="button" disabled>
										<span class="dashicons dashicons-admin-network"></span>
										<?php esc_html_e( 'Netzwerkweit aktiv', 'ps-update-manager' ); ?>
									</button>
									<p class="description" style="margin-top: 8px; font-size: 12px; color: #2271b1;">
										<span class="dashicons dashicons-info" style="font-size: 14px;"></span>
										<?php esc_html_e( 'Nur im Netzwerk-Admin deaktivierbar.', 'ps-update-manager' ); ?>
									</p>
								<?php } else {
									// Single-Site Deaktivierung
									$deactivate_url = wp_nonce_url(
										admin_url( 'plugins.php?action=deactivate&plugin=' . urlencode( $plugin_basename ) ),
										'deactivate-plugin_' . $plugin_basename
									);
									?>
									<a href="<?php echo esc_url( $deactivate_url ); ?>" class="button">
										<span class="dashicons dashicons-dismiss"></span>
										<?php esc_html_e( 'Deaktivieren', 'ps-update-manager' ); ?>
									</a>
								<?php } ?>
							<?php elseif ( $product['active'] ) : ?>
								<button class="button" disabled>
									<span class="dashicons dashicons-yes-alt"></span>
									<?php esc_html_e( 'Aktiv & Aktuell', 'ps-update-manager' ); ?>
								</button>
							<?php endif; ?>
						</div>
					</div>
				<?php endforeach; ?>
			</div>
		</div>
		
		<style>
			.ps-products-store {
				display: grid;
				grid-template-columns: repeat(auto-fill, minmax(400px, 1fr));
				gap: 20px;
				margin-top: 20px;
			}
			
			.ps-store-card {
				background: #fff;
				border: 1px solid #ccd0d4;
				border-radius: 4px;
				box-shadow: 0 1px 1px rgba(0,0,0,.04);
				display: flex;
				flex-direction: column;
			}
			
			.ps-store-card-header {
				padding: 20px;
				border-bottom: 1px solid #f0f0f1;
				display: flex;
				align-items: flex-start;
				gap: 15px;
			}
			
			.ps-store-icon {
				flex-shrink: 0;
				width: 48px;
				height: 48px;
				background: #f0f0f1;
				border-radius: 4px;
				display: flex;
				align-items: center;
				justify-content: center;
			}
			
			.ps-store-icon .dashicons {
				font-size: 28px;
				width: 28px;
				height: 28px;
				color: #2271b1;
			}
			
			.ps-store-title {
				flex: 1;
			}
			
			.ps-store-title h3 {
				margin: 0 0 5px;
				font-size: 16px;
				font-weight: 600;
			}
			
			.ps-store-meta {
				font-size: 13px;
				color: #646970;
			}
			
			.ps-store-status {
				flex-shrink: 0;
			}
			
			.ps-badge {
				display: inline-block;
				padding: 4px 10px;
				font-size: 12px;
				font-weight: 500;
				border-radius: 3px;
				white-space: nowrap;
			}
			
			.ps-badge-not-installed {
				background: #f0f0f1;
				color: #646970;
			}
			
			.ps-badge-inactive {
				background: #fcf9e8;
				color: #8a6d3b;
			}
			
			.ps-badge-active {
				background: #d4edda;
				color: #155724;
			}
			
			.ps-badge-network-active {
				background: #cfe2ff;
				color: #084298;
				display: inline-flex;
				align-items: center;
				gap: 4px;
			}
			
			.ps-badge-update {
				background: #fff3cd;
				color: #856404;
			}
			
			.ps-store-card-body {
				padding: 20px;
				flex: 1;
			}
			
			.ps-store-description {
				margin: 0 0 15px;
				color: #50575e;
				line-height: 1.6;
			}
			
			.ps-store-links {
				display: flex;
				gap: 15px;
			}
			
			.ps-link {
				display: inline-flex;
				align-items: center;
				gap: 5px;
				font-size: 13px;
				color: #2271b1;
				text-decoration: none;
			}
			
			.ps-link:hover {
				color: #135e96;
			}
			
			.ps-link .dashicons {
				font-size: 16px;
				width: 16px;
				height: 16px;
			}
			
			.ps-store-card-footer {
				padding: 15px 20px;
				border-top: 1px solid #f0f0f1;
				background: #f6f7f7;
			}
			
			.ps-store-card-footer .button {
				width: 100%;
				justify-content: center;
				display: flex;
				align-items: center;
				gap: 5px;
			}
		</style>
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
	
	/**
	 * AJAX Handler: Produkt von GitHub installieren
	 */
	public function ajax_install_product() {
		check_ajax_referer( 'ps_update_manager', 'nonce' );
		
		if ( ! $this->current_user_can_access() ) {
			wp_send_json_error( __( 'Keine Berechtigung', 'ps-update-manager' ) );
		}
		
		// Zusätzlicher Capability-Check für Installation
		if ( ! current_user_can( 'install_plugins' ) ) {
			wp_send_json_error( __( 'Fehlende Berechtigung zum Installieren von Plugins', 'ps-update-manager' ) );
		}
		
		$slug = isset( $_POST['slug'] ) ? sanitize_text_field( wp_unslash( $_POST['slug'] ) ) : '';
		$repo = isset( $_POST['repo'] ) ? sanitize_text_field( wp_unslash( $_POST['repo'] ) ) : '';
		$type = isset( $_POST['type'] ) ? sanitize_text_field( wp_unslash( $_POST['type'] ) ) : 'plugin';
		
		if ( empty( $slug ) || empty( $repo ) ) {
			wp_send_json_error( __( 'Ungültige Parameter', 'ps-update-manager' ) );
		}
		
		// SICHERHEIT: Manifest-Validierung - nur erlaubte Repos dürfen installiert werden
		$scanner = PS_Update_Manager_Product_Scanner::get_instance();
		$official_product = $scanner->get_official_product( $slug );
		
		if ( ! $official_product || $official_product['repo'] !== $repo ) {
			wp_send_json_error( __( 'Sicherheitsfehler: Produkt nicht im offiziellen Manifest', 'ps-update-manager' ) );
		}
		
		// Type-Validierung
		if ( ! in_array( $type, array( 'plugin', 'theme' ), true ) ) {
			wp_send_json_error( __( 'Ungültiger Produkttyp', 'ps-update-manager' ) );
		}
		
		// Installationslogik
		$result = $this->install_from_github( $slug, $repo, $type );
		
		if ( is_wp_error( $result ) ) {
			wp_send_json_error( $result->get_error_message() );
		}
		
		wp_send_json_success( array(
			'message' => sprintf( __( '%s erfolgreich installiert!', 'ps-update-manager' ), $slug ),
		) );
	}
	
	/**
	 * Produkt von GitHub installieren
	 */
	private function install_from_github( $slug, $repo, $type = 'plugin' ) {
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/plugin-install.php';
		require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
		
		// GitHub API: Latest Release holen
		$api = PS_Update_Manager_GitHub_API::get_instance();
		$release = $api->get_latest_release( $repo );
		
		if ( ! $release || is_wp_error( $release ) ) {
			return new WP_Error( 'no_release', __( 'Kein Release auf GitHub gefunden', 'ps-update-manager' ) );
		}
		
		// ZIP-Download-URL (korrekter Array-Key)
		$download_url = $release['download_url'];
		
		if ( empty( $download_url ) ) {
			return new WP_Error( 'no_download_url', __( 'Keine Download-URL in GitHub Release gefunden', 'ps-update-manager' ) );
		}
		
		// Temporäres Verzeichnis
		$temp_file = download_url( $download_url );
		
		if ( is_wp_error( $temp_file ) ) {
			return $temp_file;
		}
		
		// Zielverzeichnis
		$destination = ( 'theme' === $type ) ? WP_CONTENT_DIR . '/themes/' : WP_PLUGIN_DIR . '/';
		
		// Entpacken - WP_Filesystem initialisieren
		if ( ! function_exists( 'WP_Filesystem' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}
		
		$wp_filesystem_ok = WP_Filesystem();
		
		if ( ! $wp_filesystem_ok ) {
			if ( file_exists( $temp_file ) ) {
				wp_delete_file( $temp_file );
			}
			return new WP_Error( 'wp_filesystem_error', __( 'Dateisystem konnte nicht initialisiert werden', 'ps-update-manager' ) );
		}
		
		global $wp_filesystem;
		
		$unzip_result = unzip_file( $temp_file, $destination );
		
		// Temp-Datei löschen (proper cleanup ohne error suppression)
		if ( file_exists( $temp_file ) ) {
			wp_delete_file( $temp_file );
		}
		
		if ( is_wp_error( $unzip_result ) ) {
			return $unzip_result;
		}
		
		// GitHub ZIP hat Ordner wie "Power-Source-ps-chat-abc123"
		// Umbenennen zu "ps-chat"
		$extracted_dir = $this->find_extracted_directory( $destination, $repo );
		
		if ( $extracted_dir ) {
			// SICHERHEIT: Path Traversal Prevention
			$slug_safe = sanitize_file_name( $slug );
			$target_dir = trailingslashit( $destination ) . $slug_safe;
			
			// Prüfe ob Destination existiert
			if ( ! file_exists( $destination ) ) {
				return new WP_Error( 'destination_not_exists', __( 'Zielverzeichnis existiert nicht', 'ps-update-manager' ) );
			}
			
			$destination_real = realpath( $destination );
			if ( ! $destination_real ) {
				return new WP_Error( 'invalid_destination', __( 'Ungültiges Zielverzeichnis', 'ps-update-manager' ) );
			}
			
			$target_real = realpath( dirname( $target_dir ) );
			if ( ! $target_real || 0 !== strpos( $target_real, $destination_real ) ) {
				return new WP_Error( 'security_error', __( 'Sicherheitsfehler: Ungültiger Zielpfad', 'ps-update-manager' ) );
			}
			
			if ( ! file_exists( $target_dir ) ) {
				$rename_result = rename( $extracted_dir, $target_dir );
				if ( ! $rename_result ) {
					return new WP_Error( 'rename_failed', __( 'Umbenennen des Verzeichnisses fehlgeschlagen', 'ps-update-manager' ) );
				}
			}
		}
		
		return true;
	}
	
	/**
	 * Extrahierten Ordner finden (GitHub ZIP hat Hash im Namen)
	 * GitHub erstellt Ordner wie: "Power-Source-repo-name-abc123def456"
	 */
	private function find_extracted_directory( $destination, $repo ) {
		// GitHub ZIP-Format: {owner}-{repo}-{hash}
		$repo_name = basename( $repo );
		
		// Versuche direkten Match mit Glob
		$pattern = $destination . '*' . $repo_name . '*';
		$files = @glob( $pattern, GLOB_ONLYDIR );
		
		if ( ! empty( $files ) && is_array( $files ) ) {
			// Filtere nur echte Verzeichnisse
			foreach ( $files as $file ) {
				if ( is_dir( $file ) ) {
					return $file;
				}
			}
		}
		
		// Fallback: Scanne das Verzeichnis manuell
		if ( is_dir( $destination ) ) {
			$handle = @opendir( $destination );
			if ( $handle ) {
				while ( false !== ( $file = readdir( $handle ) ) ) {
					if ( '.' !== $file && '..' !== $file ) {
						$path = $destination . $file;
						if ( is_dir( $path ) && strpos( $file, $repo_name ) !== false ) {
							closedir( $handle );
							return $path;
						}
					}
				}
				closedir( $handle );
			}
		}
		
		return false;
	}
	
	/**
	 * Tools-Seite rendern
	 */
	public function render_tools() {
		// Zugriffsprüfung
		if ( ! $this->current_user_can_access() ) {
			wp_die( esc_html__( 'Sie haben keine Berechtigung, um diese Seite anzuzeigen.', 'ps-update-manager' ) );
		}
		
		// Tools Manager laden
		$tool_manager = PS_Manager_Tool_Manager::get_instance();
		$available_tools = $tool_manager->get_available_tools();
		
		?>
		<div class="wrap ps-manager-wrap">
			<h1><?php esc_html_e( 'PS Manager Tools', 'ps-update-manager' ); ?></h1>
			
			<?php if ( empty( $available_tools ) ) : ?>
				<div class="notice notice-info"><p>
					<?php esc_html_e( 'Keine Tools verfügbar.', 'ps-update-manager' ); ?>
				</p></div>
			<?php else : ?>
				<div class="ps-manager-container">
					<?php $tool_manager->render_tabs(); ?>
					<div class="ps-manager-panels">
						<?php $tool_manager->render_panels(); ?>
					</div>
				</div>
			<?php endif; ?>
		</div>
		<?php
	}
}

