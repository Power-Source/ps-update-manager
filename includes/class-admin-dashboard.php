<?php
/**
 * Admin Dashboard Klasse
 * Erstellt die Verwaltungsseite für PS Update Manager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PS_Update_Manager_Admin_Dashboard {
	/**
	 * AJAX: Produkt von GitHub installieren
	 */
	public function ajax_install_product() {
		// Sicherheits- und Parameter-Checks
		check_ajax_referer( 'ps_update_manager_nonce', 'nonce' );
		$slug = isset( $_POST['slug'] ) ? sanitize_key( $_POST['slug'] ) : '';
		$repo = isset( $_POST['repo'] ) ? sanitize_text_field( $_POST['repo'] ) : '';
		$type = isset( $_POST['type'] ) ? sanitize_key( $_POST['type'] ) : 'plugin';

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
			wp_send_json_error( array(
				'message' => $result->get_error_message(),
				'code'    => $result->get_error_code(),
			) );
		}

		wp_send_json_success( array(
			'message' => sprintf( __( '%s erfolgreich installiert!', 'ps-update-manager' ), $slug ),
		) );
	}

	private static $instance = null;

	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Prüft, ob der aktuelle Benutzer Zugriff auf die Admin-Seiten hat
	 * Handhabt Single-Site und Network-Admin korrekt.
	 *
	 * @return bool
	 */
	private function current_user_can_access() {
		if ( is_network_admin() ) {
			return current_user_can( 'manage_network_options' );
		}
		return current_user_can( 'manage_options' );
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
		add_action( 'wp_ajax_ps_test_github_api', array( $this, 'ajax_test_github_api' ) );
		add_action( 'wp_ajax_ps_load_products', array( $this, 'ajax_load_products' ) );
		add_action( 'wp_ajax_ps_get_categories', array( $this, 'ajax_get_categories' ) );
		add_action( 'wp_ajax_ps_deactivate_plugin', array( $this, 'ajax_deactivate_plugin' ) );
		add_action( 'wp_ajax_ps_activate_plugin', array( $this, 'ajax_activate_plugin' ) );
		add_action( 'wp_ajax_ps_update_product', array( $this, 'ajax_update_product' ) );
	}

	/**
	 * Admin Assets einbinden
	 *
	 * @param string $hook Aktueller Hook der Admin-Seite
	 */
	public function enqueue_assets( $hook = '' ) {
		// Nur auf unseren Plugin-Seiten laden
		$pages = array(
			'toplevel_page_ps-update-manager',
			'ps-update-manager_page_ps-update-manager',
			'ps-update-manager_page_ps-update-manager-psources',
			'ps-update-manager_page_ps-update-manager-tools',
			'ps-update-manager_page_ps-update-manager-settings',
		);

		// Fallback: prüfe ?page= Parameter, falls Hook nicht passt
		$current_page = isset( $_GET['page'] ) ? sanitize_key( $_GET['page'] ) : '';
		$our_pages    = array(
			'ps-update-manager',
			'ps-update-manager-psources',
			'ps-update-manager-tools',
			'ps-update-manager-settings',
		);

		if ( ! in_array( $hook, $pages, true ) && ! in_array( $current_page, $our_pages, true ) ) {
			return;
		}

		$base_url = plugin_dir_url( dirname( __FILE__ ) );

		// Styles
		wp_enqueue_style( 'ps-update-manager-admin', $base_url . 'assets/css/admin.css', array(), '1.0.0' );
		
		// PSources Katalog CSS (nur auf der PSources-Seite)
		if ( in_array( $current_page, array( 'ps-update-manager-psources' ), true ) ) {
			wp_enqueue_style( 'ps-catalog', $base_url . 'assets/css/psources-catalog.css', array(), '1.0.0' );
		}
		
		// Settings CSS (nur auf der Settings-Seite)
		if ( in_array( $current_page, array( 'ps-update-manager-settings' ), true ) ) {
			wp_enqueue_style( 'ps-settings', $base_url . 'assets/css/settings.css', array(), '1.0.0' );
		}

		// Scripts
		wp_enqueue_script( 'ps-update-manager-admin', $base_url . 'assets/js/admin.js', array( 'jquery' ), '1.0.0', true );
		
		// PSources Katalog Script (nur auf der PSources-Seite)
		if ( in_array( $current_page, array( 'ps-update-manager-psources' ), true ) ) {
			wp_enqueue_script( 'ps-catalog', $base_url . 'assets/js/psources-catalog.js', array( 'jquery' ), '1.0.0', true );
		}

		// Lokalisierung / Nonces für AJAX
		wp_localize_script( 'ps-update-manager-admin', 'PSUpdateManager', array(
			'ajaxUrl'          => admin_url( 'admin-ajax.php' ),
			'nonce'            => wp_create_nonce( 'ps_update_manager_nonce' ),
			'networkAdmin'     => is_network_admin(),
			'forceCheckAction' => 'ps_force_update_check',
			'installAction'    => 'ps_install_product',
			'testGithub'       => 'ps_test_github_api',
			'strings'          => array(
				'checking' => __( 'Wird geprüft...', 'ps-update-manager' ),
				'success'  => __( 'Erfolgreich!', 'ps-update-manager' ),
				'error'    => __( 'Ein Fehler ist aufgetreten', 'ps-update-manager' ),
			),
		) );
	}

	/**
	 * Netzwerk-Menü hinzufügen (Multisite)
	 */
	public function add_network_menu() {
		if ( ! is_multisite() || ! is_super_admin() ) {
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
	 * Legacy-Redirect für alte Produkte-Seite
	 */
	public function maybe_redirect_legacy_products_page() {
		if ( ! is_admin() ) {
			return;
		}
		if ( isset( $_GET['page'] ) && 'ps-update-manager-products' === sanitize_key( $_GET['page'] ) ) {
			$target = is_network_admin()
				? network_admin_url( 'admin.php?page=ps-update-manager-psources' )
				: admin_url( 'admin.php?page=ps-update-manager-psources' );
			wp_safe_redirect( $target );
			exit;
		}
	}

	/**
	 * Orphaned Produkte aus Registry bereinigen
	 */
	public function cleanup_orphaned_products() {
		$registry = PS_Update_Manager_Product_Registry::get_instance();
		$scanner  = PS_Update_Manager_Product_Scanner::get_instance();

		$official_products = $scanner->get_official_products();
		$official_slugs    = array_keys( $official_products );

		$all_products = $registry->get_all();
		foreach ( $all_products as $slug => $product ) {
			if ( ! in_array( $slug, $official_slugs, true ) && ! $product['is_active'] ) {
				$registry->unregister( $slug );
			}
		}
	}

	/**
	 * Menü hinzufügen (Normal/Einzelsite)
	 */
	public function add_menu() {
		// Nur für Single-Site Admin, Multisite nutzt network_admin_menu
		if ( is_multisite() ) {
			return;
		}

		if ( ! $this->current_user_can_access() ) {
			return;
		}

		add_menu_page(
			__( 'PSOURCE Manager', 'ps-update-manager' ),
			__( 'PS MANAGER', 'ps-update-manager' ),
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

		add_submenu_page(
			'ps-update-manager',
			__( 'Einstellungen', 'ps-update-manager' ),
			__( 'Einstellungen', 'ps-update-manager' ),
			'manage_options',
			'ps-update-manager-settings',
			array( $this, 'render_settings' )
		);
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
		
		// Scanner immer ausführen, damit Status sofort aktuell ist
		$scanner = PS_Update_Manager_Product_Scanner::get_instance();
		$scanner->scan_all();
		$last_scan = current_time( 'timestamp' );
		
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
						if ( $updates_available === 1 ) {
							esc_html_e( 'Es ist ein Update für deine PSOURCE-Installationen verfügbar.', 'ps-update-manager' );
						} else {
							printf(
								esc_html__( 'Es sind %d Updates für deine PSOURCE-Installationen verfügbar.', 'ps-update-manager' ),
								$updates_available
							);
						}
						?>
						<a href="<?php echo esc_url( admin_url( 'update-core.php' ) ); ?>" class="button button-small">
							<?php esc_html_e( 'Jetzt aktualisieren', 'ps-update-manager' ); ?>
						</a>
					</p>
				</div>
			<?php endif; ?>
			
			<div class="ps-products-overview">
				<h2><?php esc_html_e( 'Deine PSOURCE-Übersicht', 'ps-update-manager' ); ?></h2>
				<p>
					<?php esc_html_e( 'Hier findest du alle PSOURCE Plugins und Themes, die auf deiner Webseite installiert sind. Du kannst den Status, die Version und verfügbare Updates auf einen Blick sehen.', 'ps-update-manager' ); ?>
				</p>
				
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
								<?php
								$docs_url = ! empty( $product['docs_url'] ) 
									? $product['docs_url'] 
									: 'https://power-source.github.io/' . rawurlencode( $product['slug'] );
								?>
								<a href="<?php echo esc_url( $docs_url ); ?>" target="_blank" class="button button-small">
										<span class="dashicons dashicons-book"></span>
										<?php esc_html_e( 'Docs', 'ps-update-manager' ); ?>
									</a>
								
								<?php if ( ! empty( $product['support_url'] ) ) : ?>
									<a href="<?php echo esc_url( $product['support_url'] ); ?>" target="_blank" class="button button-small">
										<span class="dashicons dashicons-sos"></span>
										<?php esc_html_e( 'Support', 'ps-update-manager' ); ?>
									</a>
								<?php endif; ?>
								
								<?php if ( ! empty( $product['github_repo'] ) ) : ?>
									<a href="<?php echo esc_url( 'https://github.com/' . $product['github_repo'] ); ?>" target="_blank" class="button button-small">
										<span class="dashicons dashicons-admin-site-alt3"></span>
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
					<a href="https://github.com/power-source" target="_blank" class="button">
						<?php esc_html_e( 'Auf GitHub mitwirken um Dein Projekt zu verbessern', 'ps-update-manager' ); ?>
					</a>
				</p>
			</div>
		</div>
		<?php
	}
	
	/**
	 * Produkte-Seite rendern (Tab-basiert mit AJAX)
	 */
	public function render_products() {
		// Zugriffsprüfung
		if ( ! $this->current_user_can_access() ) {
			wp_die( esc_html__( 'Du hast keine Berechtigung, um diese Seite anzuzeigen.', 'ps-update-manager' ) );
		}
		
		?>
		<div class="wrap ps-update-manager-psources">
			<h1><?php esc_html_e( 'PSOURCE Katalog', 'ps-update-manager' ); ?></h1>
			<p class="description">
				<?php esc_html_e( 'Entdecke und installiere offizielle PSOURCE Plugins und Themes aus dem Power-Source Repository.', 'ps-update-manager' ); ?>
			</p>

			<!-- Tabs -->
			<h2 class="nav-tab-wrapper">
				<a href="#" class="nav-tab nav-tab-active ps-tab-link" data-tab="plugins">
					<span class="dashicons dashicons-admin-plugins"></span>
					<?php esc_html_e( 'Plugins', 'ps-update-manager' ); ?>
				</a>
				<a href="#" class="nav-tab ps-tab-link" data-tab="themes">
					<span class="dashicons dashicons-admin-appearance"></span>
					<?php esc_html_e( 'Themes', 'ps-update-manager' ); ?>
				</a>
			</h2>

			<!-- Filter -->
			<form id="ps-catalog-filters" style="margin: 20px 0; padding: 15px; background: #fff; border: 1px solid #ccd0d4; border-radius: 4px;">
				<div style="display: flex; gap: 10px; flex-wrap: wrap; align-items: center;">
					<input type="search" id="ps-search" class="regular-text" placeholder="<?php esc_attr_e( 'Suche nach Name oder Beschreibung…', 'ps-update-manager' ); ?>" />
					
					<select id="ps-category">
						<option value="all"><?php esc_html_e( 'Alle Kategorien', 'ps-update-manager' ); ?></option>
						<!-- Kategorien werden per AJAX geladen je nach aktivem Tab -->
					</select>
					
					<select id="ps-status">
						<option value="all"><?php esc_html_e( 'Alle', 'ps-update-manager' ); ?></option>
						<option value="installed"><?php esc_html_e( 'Installiert', 'ps-update-manager' ); ?></option>
						<option value="active"><?php esc_html_e( 'Aktiv', 'ps-update-manager' ); ?></option>
						<option value="available"><?php esc_html_e( 'Verfügbar', 'ps-update-manager' ); ?></option>
						<option value="updates"><?php esc_html_e( 'Update verfügbar', 'ps-update-manager' ); ?></option>
					</select>
					
					<button type="submit" class="button button-primary">
						<span class="dashicons dashicons-filter"></span>
						<?php esc_html_e( 'Filter anwenden', 'ps-update-manager' ); ?>
					</button>
					
					<button type="button" id="ps-reset-filters" class="button">
						<span class="dashicons dashicons-undo"></span>
						<?php esc_html_e( 'Zurücksetzen', 'ps-update-manager' ); ?>
					</button>
				</div>
			</form>

			<!-- Produkt-Grid (AJAX-Container) -->
			<div id="ps-products-grid" class="ps-products-store"></div>

			<!-- Pagination (AJAX) -->
			<div id="ps-pagination" style="margin-top: 20px;"></div>
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
	 * AJAX: Produkte laden (für Tab-System)
	 */
	public function ajax_load_products() {
		check_ajax_referer( 'ps_update_manager_nonce', 'nonce' );

		if ( ! $this->current_user_can_access() ) {
			wp_send_json_error( array( 'message' => __( 'Keine Berechtigung', 'ps-update-manager' ) ) );
		}

		$tab      = isset( $_POST['tab'] ) ? sanitize_key( $_POST['tab'] ) : 'plugins';
		$page     = isset( $_POST['page'] ) ? max( 1, intval( $_POST['page'] ) ) : 1;
		$search   = isset( $_POST['search'] ) ? sanitize_text_field( wp_unslash( $_POST['search'] ) ) : '';
		$category = isset( $_POST['category'] ) ? sanitize_key( $_POST['category'] ) : 'all';
		$status   = isset( $_POST['status'] ) ? sanitize_key( $_POST['status'] ) : 'all';

		// Produkte sammeln
		$scanner = PS_Update_Manager_Product_Scanner::get_instance();
		$scanner->scan_all();

		$official_products  = $scanner->get_official_products();
		$registry           = PS_Update_Manager_Product_Registry::get_instance();
		$installed_products = $registry->get_all();

		// Produkte vorbereiten
		$all_products = array();
		foreach ( $official_products as $slug => $manifest ) {
			$product = array(
				'slug'             => $slug,
				'name'             => $manifest['name'],
				'description'      => $manifest['description'],
				'type'             => $manifest['type'],
				'repo'             => $manifest['repo'],
				'icon'             => $manifest['icon'] ?? 'dashicons-admin-plugins',
				'category'         => $manifest['category'] ?? 'general',
				'installed'        => false,
				'active'           => false,
				'version'          => null,
				'update_available' => false,
				'new_version'      => null,
				'basename'         => null,
				'network_mode'     => 'none',
				'featured'         => $manifest['featured'] ?? false,
				'badge'            => $manifest['badge'] ?? null,
				'logo'             => $this->get_product_logo_url( $slug ),
			);

			if ( isset( $installed_products[ $slug ] ) ) {
				$installed                  = $installed_products[ $slug ];
				$product['installed']       = true;
				$product['active']          = $installed['is_active'];
				$product['version']         = $installed['version'];
				$product['basename']        = $installed['basename'] ?? null;
				$product['network_mode']    = $installed['network_mode'] ?? 'none';

				$update_info = $this->get_product_update_info( $installed );
				if ( $update_info && version_compare( $update_info['version'], $installed['version'], '>' ) ) {
					$product['update_available'] = true;
					$product['new_version']      = $update_info['version'];
				}
			}

			$all_products[ $slug ] = $product;
		}

		// Nach Typ filtern
		$items = array_filter( $all_products, function( $item ) use ( $tab ) {
			return $item['type'] === $tab || ( 'plugins' === $tab && 'plugin' === $item['type'] ) || ( 'themes' === $tab && 'theme' === $item['type'] );
		});

		// Filter anwenden
		if ( $search ) {
			$items = array_filter( $items, function( $item ) use ( $search ) {
				$hay = strtolower( $item['name'] . ' ' . $item['slug'] . ' ' . $item['description'] );
				return strpos( $hay, strtolower( $search ) ) !== false;
			});
		}

		if ( $category && 'all' !== $category ) {
			$items = array_filter( $items, function( $item ) use ( $category ) {
				return isset( $item['category'] ) && $item['category'] === $category;
			});
		}

		if ( $status && 'all' !== $status ) {
			$items = array_filter( $items, function( $item ) use ( $status ) {
				if ( 'installed' === $status ) { return $item['installed']; }
				if ( 'active' === $status ) { return $item['active']; }
				if ( 'available' === $status ) { return ! $item['installed']; }
				if ( 'updates' === $status ) { return $item['update_available']; }
				return true;
			});
		}

		// Featured-Produkte nach oben sortieren (wenn kein Filter aktiv)
		if ( empty( $search ) && 'all' === $status ) {
			uasort( $items, function( $a, $b ) {
				$a_featured = isset( $a['featured'] ) && $a['featured'];
				$b_featured = isset( $b['featured'] ) && $b['featured'];
				if ( $a_featured === $b_featured ) { return 0; }
				return $a_featured ? -1 : 1;
			});
		}

		// Pagination
		$per_page = 12;
		$total    = count( $items );
		$pages    = max( 1, ceil( $total / $per_page ) );
		$page     = min( $page, $pages );
		$offset   = ( $page - 1 ) * $per_page;
		$slice    = array_slice( $items, $offset, $per_page, true );

		// HTML rendern
		ob_start();
		if ( empty( $slice ) ) {
			echo '<div class="ps-no-results"><p>' . esc_html__( 'Keine PSOURCE gefunden.', 'ps-update-manager' ) . '</p></div>';
		} else {
			foreach ( $slice as $slug => $product ) {
				$this->render_product_card( $product, $installed_products );
			}
		}
		$html = ob_get_clean();

		// Pagination HTML
		$pagination_html = '';
		if ( $pages > 1 ) {
			$pagination_html = '<div class="ps-pagination">';
			for ( $i = 1; $i <= $pages; $i++ ) {
				$class = ( $i === $page ) ? 'button button-primary' : 'button';
				$pagination_html .= sprintf(
					'<a href="#" class="ps-pagination-link %s" data-page="%d">%d</a> ',
					esc_attr( $class ),
					intval( $i ),
					intval( $i )
				);
			}
			$pagination_html .= '</div>';
		}

		wp_send_json_success( array(
			'html'       => $html,
			'pagination' => $pagination_html,
			'total'      => $total,
			'pages'      => $pages,
		) );
	}

	/**
	 * AJAX: Kategorien für Tab abrufen
	 */
	public function ajax_get_categories() {
		check_ajax_referer( 'ps_update_manager_nonce', 'nonce' );

		if ( ! $this->current_user_can_access() ) {
			wp_send_json_error( array( 'message' => __( 'Keine Berechtigung', 'ps-update-manager' ) ) );
		}

		$tab = isset( $_POST['tab'] ) ? sanitize_key( $_POST['tab'] ) : 'plugins';

		// Kategorie-Mapping laden
		$category_map_file = PS_UPDATE_MANAGER_DIR . 'includes/category-map.php';
		$all_categories = file_exists( $category_map_file ) ? include $category_map_file : array();

		// Kategorien für den Tab
		$categories = array();
		if ( 'plugins' === $tab && isset( $all_categories['plugins'] ) ) {
			$categories = $all_categories['plugins'];
		} elseif ( 'themes' === $tab && isset( $all_categories['themes'] ) ) {
			$categories = $all_categories['themes'];
		}

		wp_send_json_success( array( 'categories' => $categories ) );
	}

	/**
	 * Einzelne Produkt-Karte rendern (für AJAX)
	 */
	private function render_product_card( $product, $installed_products = array() ) {
		$slug = $product['slug'];
		$is_network_active = false;
		if ( is_multisite() && $product['installed'] && isset( $product['basename'] ) && $product['basename'] ) {
			$is_network_active = is_plugin_active_for_network( $product['basename'] );
		}
		
		$is_featured = isset( $product['featured'] ) && $product['featured'];
		$badge_type = isset( $product['badge'] ) ? $product['badge'] : null;
		$card_class = $is_featured ? 'ps-store-card ps-store-card-featured' : 'ps-store-card';
		
		?>
		<div class="<?php echo esc_attr( $card_class ); ?>" data-slug="<?php echo esc_attr( $slug ); ?>">
			<?php if ( $is_featured ) : ?>
				<div class="ps-featured-ribbon">
					<span class="dashicons dashicons-star-filled"></span>
					<?php esc_html_e( 'Empfohlen', 'ps-update-manager' ); ?>
				</div>
			<?php endif; ?>
			<?php if ( ! empty( $product['logo'] ) ) : ?>
				<div class="ps-store-card-logo">
					<img src="<?php echo esc_url( $product['logo'] ); ?>" alt="<?php echo esc_attr( $product['name'] ); ?>" />
				</div>
			<?php endif; ?>
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
						<?php if ( $badge_type ) : ?>
							<?php if ( 'framework' === $badge_type ) : ?>
								<span class="ps-meta-badge ps-badge-framework">
									<span class="dashicons dashicons-welcome-widgets-menus"></span>
									<?php esc_html_e( 'Pagebuilder', 'ps-update-manager' ); ?>
								</span>
							<?php elseif ( 'child-theme' === $badge_type ) : ?>
								<span class="ps-meta-badge ps-badge-child">
									<span class="dashicons dashicons-admin-generic"></span>
									<?php esc_html_e( 'Child Theme', 'ps-update-manager' ); ?>
								</span>
							<?php elseif ( 'template' === $badge_type ) : ?>
								<span class="ps-meta-badge ps-badge-template">
									<span class="dashicons dashicons-admin-page"></span>
									<?php esc_html_e( 'Template', 'ps-update-manager' ); ?>
								</span>
							<?php endif; ?>
						<?php endif; ?>
					</span>
				</div>
				
				<div class="ps-store-status">
					<?php if ( ! $product['installed'] ) : ?>
						<span class="ps-badge ps-badge-not-installed"><?php esc_html_e( 'Nicht installiert', 'ps-update-manager' ); ?></span>
					<?php elseif ( $product['update_available'] ) : ?>
						<span class="ps-badge ps-badge-update"><?php printf( __( 'Update: v%s', 'ps-update-manager' ), esc_html( $product['new_version'] ) ); ?></span>
					<?php elseif ( $product['active'] ) : ?>
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
				
				<?php
				// "Erweitert durch" Banner anzeigen
				$dependency_manager = PS_Update_Manager_Dependency_Manager::get_instance();
				echo $dependency_manager->render_extends_banner( $slug );
				?>
				
				<div class="ps-store-links">
					<a href="https://github.com/<?php echo esc_attr( $product['repo'] ); ?>" target="_blank" class="ps-link">
						<span class="dashicons dashicons-admin-site"></span> GitHub
					</a>
					<a href="https://github.com/<?php echo esc_attr( $product['repo'] ); ?>/issues" target="_blank" class="ps-link">
						<span class="dashicons dashicons-sos"></span> Support
					</a>
					<?php
					$docs_url = ! empty( $product['docs_url'] ) 
						? $product['docs_url'] 
						: 'https://power-source.github.io/' . rawurlencode( $product['slug'] );
					?>
					<a href="<?php echo esc_url( $docs_url ); ?>" target="_blank" class="ps-link">
						<span class="dashicons dashicons-media-document"></span> Handbuch
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
					<button class="button button-primary ps-update-product" data-slug="<?php echo esc_attr( $slug ); ?>" data-basename="<?php echo esc_attr( $product['basename'] ); ?>" data-type="<?php echo esc_attr( $product['type'] ); ?>" data-new-version="<?php echo esc_attr( $product['new_version'] ); ?>">
						<span class="dashicons dashicons-update"></span>
						<?php esc_html_e( 'Jetzt aktualisieren', 'ps-update-manager' ); ?>
					</button>
				<?php elseif ( ! $product['active'] && 'plugin' === $product['type'] ) : ?>
					<?php
					$plugin_basename = $product['basename'] ?? $slug . '/' . $slug . '.php';
					$network_mode    = $product['network_mode'];
					
					if ( is_multisite() && is_network_admin() ) {
						if ( 'none' === $network_mode ) {
							?>
							<button class="button" disabled>
								<span class="dashicons dashicons-admin-site"></span>
								<?php esc_html_e( 'Nur Unterseiten', 'ps-update-manager' ); ?>
							</button>
							<?php
						} else {
							?>
							<button class="button button-primary ps-activate-plugin" data-slug="<?php echo esc_attr( $slug ); ?>" data-basename="<?php echo esc_attr( $plugin_basename ); ?>" data-network="true">
								<span class="dashicons dashicons-yes"></span>
								<?php esc_html_e( 'Netzwerkweit aktivieren', 'ps-update-manager' ); ?>
							</button>
							<?php
						}
					} else {
						?>
						<button class="button button-primary ps-activate-plugin" data-slug="<?php echo esc_attr( $slug ); ?>" data-basename="<?php echo esc_attr( $plugin_basename ); ?>" data-network="false">
							<span class="dashicons dashicons-yes"></span>
							<?php esc_html_e( 'Aktivieren', 'ps-update-manager' ); ?>
						</button>
					<?php } ?>
				<?php elseif ( ! $product['active'] && 'theme' === $product['type'] ) : ?>
					<?php
					$activate_url = wp_nonce_url(
						is_network_admin() 
							? network_admin_url( 'themes.php?action=enable&theme=' . urlencode( $slug ) )
							: admin_url( 'themes.php?action=activate&stylesheet=' . urlencode( $slug ) ),
						is_network_admin() ? 'enable-theme_' . $slug : 'switch-theme_' . $slug
					);
					?>
					<a href="<?php echo esc_url( $activate_url ); ?>" class="button button-primary">
						<span class="dashicons dashicons-yes"></span>
						<?php esc_html_e( 'Aktivieren', 'ps-update-manager' ); ?>
					</a>
				<?php else : ?>
					<button class="button button-secondary ps-deactivate-plugin" data-slug="<?php echo esc_attr( $slug ); ?>" data-basename="<?php echo esc_attr( $product['basename'] ); ?>" data-type="<?php echo esc_attr( $product['type'] ); ?>">
						<span class="dashicons dashicons-dismiss"></span>
						<?php esc_html_e( 'Deaktivieren', 'ps-update-manager' ); ?>
					</button>
				<?php endif; ?>
			</div>
		</div>
		<?php
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
			
			<div class="ps-settings-section" style="margin-top:20px;">
				<h3><?php esc_html_e( '💡 Hinweise', 'ps-update-manager' ); ?></h3>
				<ul>
					<li><?php esc_html_e( 'Der Netzwerk-Administrator hat immer Zugriff auf das Dashboard.', 'ps-update-manager' ); ?></li>
					<li><?php esc_html_e( 'Wähle mindestens eine Rolle aus, damit andere Benutzer Zugriff haben.', 'ps-update-manager' ); ?></li>
					<li><?php esc_html_e( 'Diese Einstellung gilt netzwerkweit für alle Seiten.', 'ps-update-manager' ); ?></li>
				</ul>
			</div>
		</div>
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
		check_ajax_referer( 'ps_update_manager_nonce', 'nonce' );

		if ( ! $this->current_user_can_access() ) {
			wp_send_json_error( __( 'Keine Berechtigung', 'ps-update-manager' ) );
		}

		$scanner = PS_Update_Manager_Product_Scanner::get_instance();
		$scanner->scan_all();

		$products = PS_Update_Manager_Product_Registry::get_instance()->get_all();
		$updates_available = $this->count_available_updates( $products );

		wp_send_json_success( array(
			'updates_available' => $updates_available,
			'total_products'    => count( $products ),
			'message'           => sprintf(
				__( 'Überprüfung abgeschlossen: %d Updates verfügbar', 'ps-update-manager' ),
				$updates_available
			),
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

		// Debug: Fehler spezifisch handeln
		if ( is_wp_error( $release ) ) {
			// Preserve the actual error from GitHub API
			return $release;
		}

		if ( ! $release || ! is_array( $release ) ) {
			return new WP_Error( 'invalid_release', sprintf(
				__( 'Ungültiges Release-Format für "%s"', 'ps-update-manager' ),
				esc_html( $repo )
			) );
		}

		// ZIP-Download-URL (korrekter Array-Key)
		$download_url = $release['download_url'] ?? '';

		if ( empty( $download_url ) ) {
			return new WP_Error( 'no_download_url', sprintf(
				__( 'Keine Download-URL in GitHub Release "%s" gefunden', 'ps-update-manager' ),
				esc_html( $repo )
			) );
		}

		// Zielverzeichnis und Zielordner vorbereiten
		$destination = ( 'theme' === $type ) ? WP_CONTENT_DIR . '/themes/' : WP_PLUGIN_DIR . '/';
		$slug_safe = sanitize_file_name( $slug );
		$target_dir = trailingslashit( $destination ) . $slug_safe;
		// Vorhandenen Zielordner vorab löschen
		if ( file_exists( $target_dir ) ) {
			$this->delete_directory_recursive( $target_dir );
		}
		
		// Temporäres Verzeichnis
		$temp_file = download_url( $download_url );
		
		if ( is_wp_error( $temp_file ) ) {
			return new WP_Error( 'download_failed', sprintf(
				__( 'Download fehlgeschlagen: %s', 'ps-update-manager' ),
				$temp_file->get_error_message()
			) );
		}
		
		if ( ! file_exists( $temp_file ) ) {
			return new WP_Error( 'temp_file_not_exists', __( 'Temporäre Datei konnte nicht erstellt werden', 'ps-update-manager' ) );
		}
		
		// Zielverzeichnis ist oben bereits gesetzt
		
		// Entpacken - WP_Filesystem initialisieren
		if ( ! function_exists( 'WP_Filesystem' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}
		
		$wp_filesystem_ok = WP_Filesystem();
		
		if ( ! $wp_filesystem_ok ) {
			if ( file_exists( $temp_file ) ) {
				wp_delete_file( $temp_file );
			}
			return new WP_Error( 'wp_filesystem_error', __( 'ClassicPress Dateisystem konnte nicht initialisiert werden. Bitte prüfe die Dateisystem-Berechtigungen.', 'ps-update-manager' ) );
		}
		
		global $wp_filesystem;
		
		$unzip_result = unzip_file( $temp_file, $destination );
		
		// Temp-Datei löschen (proper cleanup ohne error suppression)
		if ( file_exists( $temp_file ) ) {
			wp_delete_file( $temp_file );
		}
		
		if ( is_wp_error( $unzip_result ) ) {
			return new WP_Error( 'unzip_failed', sprintf(
				__( 'Entpacken fehlgeschlagen: %s', 'ps-update-manager' ),
				$unzip_result->get_error_message()
			) );
		}
		
		// GitHub ZIP hat Ordner wie "Power-Source-ps-chat-abc123"
		// Umbenennen zu "ps-chat"
		$extracted_dir = $this->find_extracted_directory( $destination, $repo );
		
		if ( $extracted_dir ) {
			// SICHERHEIT: Path Traversal Prevention
			// $slug_safe und $target_dir sind oben bereits gesetzt
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
			// Nach erfolgreichem Umbenennen: Ursprünglichen extrahierten Ordner löschen, falls noch vorhanden und nicht identisch mit Ziel
			if ( file_exists( $extracted_dir ) && $extracted_dir !== $target_dir ) {
				$this->delete_directory_recursive( $extracted_dir );
			}
		}

		return true;
	}

	/**
	 * Hilfsfunktion: Verzeichnis rekursiv löschen
	 */
	private function delete_directory_recursive( $dir ) {
		if ( ! file_exists( $dir ) ) {
			return;
		}
		if ( is_file( $dir ) || is_link( $dir ) ) {
			@unlink( $dir );
			return;
		}
		$files = array_diff( scandir( $dir ), array( '.', '..' ) );
		foreach ( $files as $file ) {
			$this->delete_directory_recursive( $dir . DIRECTORY_SEPARATOR . $file );
		}
		@rmdir( $dir );
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
	
	/**
	 * Test GitHub API - Debug-Funktion
	 */
	public function ajax_test_github_api() {
		check_ajax_referer( 'ps_update_manager', 'nonce' );
		
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'Keine Berechtigung', 'ps-update-manager' ) );
		}
		
		$repo = isset( $_POST['repo'] ) ? sanitize_text_field( wp_unslash( $_POST['repo'] ) ) : '';
		
		if ( empty( $repo ) ) {
			wp_send_json_error( __( 'Repository-Name erforderlich', 'ps-update-manager' ) );
		}
		
		$api = PS_Update_Manager_GitHub_API::get_instance();
		$release = $api->get_latest_release( $repo );
		
		if ( is_wp_error( $release ) ) {
			wp_send_json_error( array(
				'code'    => $release->get_error_code(),
				'message' => $release->get_error_message(),
			) );
		}
		
		wp_send_json_success( array(
			'repo'           => $repo,
			'version'        => $release['version'] ?? 'N/A',
			'tag_name'       => $release['tag_name'] ?? 'N/A',
			'download_url'   => $release['download_url'] ?? 'N/A',
			'has_zip'        => ! empty( $release['download_url'] ),
		) );
	}

	/**
	 * Produkt-Logo URL auflösen
	 * Prüft ob Logo.png im Plugin-Verzeichnis existiert, sonst psource-logo.png Fallback
	 *
	 * @param string $slug Plugin/Theme Slug
	 * @return string Logo URL
	 */
	private function get_product_logo_url( $slug ) {
		// Prüfe zunächst ob das Plugin/Theme lokal installiert ist
		$plugin_dir = WP_PLUGIN_DIR . '/' . $slug;
		$theme_dir  = WP_CONTENT_DIR . '/themes/' . $slug;

		// Logo.png lokal prüfen
		if ( file_exists( $plugin_dir . '/Logo.png' ) ) {
			return plugins_url( 'Logo.png', $plugin_dir . '/' . $slug . '.php' );
		} elseif ( file_exists( $theme_dir . '/Logo.png' ) ) {
			return get_theme_file_uri( $slug . '/Logo.png' );
		}

		// Fallback auf psource-logo.png lokal
		if ( file_exists( $plugin_dir . '/psource-logo.png' ) ) {
			return plugins_url( 'psource-logo.png', $plugin_dir . '/' . $slug . '.php' );
		} elseif ( file_exists( $theme_dir . '/psource-logo.png' ) ) {
			return get_theme_file_uri( $slug . '/psource-logo.png' );
		}

		// Ultimativer Fallback: psource-logo.png aus PS Update Manager Plugin
		return PS_UPDATE_MANAGER_URL . 'psource-logo.png';
	}

	/**
	 * AJAX: Plugin deaktivieren
	 */
	public function ajax_deactivate_plugin() {
		check_ajax_referer( 'ps_update_manager_nonce', 'nonce' );

		if ( ! $this->current_user_can_access() ) {
			wp_send_json_error( array( 'message' => __( 'Keine Berechtigung', 'ps-update-manager' ) ) );
		}

		$slug = isset( $_POST['slug'] ) ? sanitize_key( $_POST['slug'] ) : '';
		$basename = isset( $_POST['basename'] ) ? sanitize_text_field( $_POST['basename'] ) : '';

		if ( empty( $slug ) || empty( $basename ) ) {
			wp_send_json_error( array( 'message' => __( 'Ungültige Parameter', 'ps-update-manager' ) ) );
		}

		// ClassicPress Plugin-Funktionen laden
		if ( ! function_exists( 'deactivate_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		// Plugin deaktivieren
		if ( is_multisite() && is_network_admin() ) {
			deactivate_plugins( $basename, false, true ); // Network-wide deactivation
		} else {
			deactivate_plugins( $basename );
		}

		wp_send_json_success( array(
			'message' => sprintf( __( '%s wurde erfolgreich deaktiviert.', 'ps-update-manager' ), $slug ),
		) );
	}

	/**
	 * AJAX: Plugin aktivieren
	 */
	public function ajax_activate_plugin() {
		check_ajax_referer( 'ps_update_manager_nonce', 'nonce' );

		if ( ! $this->current_user_can_access() ) {
			wp_send_json_error( array( 'message' => __( 'Keine Berechtigung', 'ps-update-manager' ) ) );
		}

		$slug = isset( $_POST['slug'] ) ? sanitize_key( $_POST['slug'] ) : '';
		$basename = isset( $_POST['basename'] ) ? sanitize_text_field( $_POST['basename'] ) : '';
		$network = isset( $_POST['network'] ) && $_POST['network'] === 'true';

		if ( empty( $slug ) || empty( $basename ) ) {
			wp_send_json_error( array( 'message' => __( 'Ungültige Parameter', 'ps-update-manager' ) ) );
		}

		// ClassicPress Plugin-Funktionen laden
		if ( ! function_exists( 'activate_plugin' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		// Plugin aktivieren
		$result = activate_plugin( $basename, '', $network );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array(
				'message' => $result->get_error_message(),
			) );
		}

		wp_send_json_success( array(
			'message' => sprintf( __( '%s wurde erfolgreich aktiviert.', 'ps-update-manager' ), $slug ),
		) );
	}

	/**
	 * AJAX: Produkt aktualisieren
	 */
	public function ajax_update_product() {
		check_ajax_referer( 'ps_update_manager_nonce', 'nonce' );

		if ( ! $this->current_user_can_access() ) {
			wp_send_json_error( array( 'message' => __( 'Keine Berechtigung', 'ps-update-manager' ) ) );
		}

		$slug = isset( $_POST['slug'] ) ? sanitize_key( $_POST['slug'] ) : '';
		$basename = isset( $_POST['basename'] ) ? sanitize_text_field( $_POST['basename'] ) : '';
		$type = isset( $_POST['type'] ) ? sanitize_key( $_POST['type'] ) : 'plugin';

		if ( empty( $slug ) || empty( $basename ) ) {
			wp_send_json_error( array( 'message' => __( 'Ungültige Parameter', 'ps-update-manager' ) ) );
		}

		// WordPress Update-Funktionen laden
		if ( ! function_exists( 'request_filesystem_credentials' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}
		if ( ! class_exists( 'Plugin_Upgrader' ) ) {
			require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
		}

		if ( 'plugin' === $type ) {
			// Plugin-Update
			$upgrader = new Plugin_Upgrader( new WP_Ajax_Upgrader_Skin() );
			$result = $upgrader->upgrade( $basename );
		} else {
			// Theme-Update
			if ( ! class_exists( 'Theme_Upgrader' ) ) {
				require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
			}
			$upgrader = new Theme_Upgrader( new WP_Ajax_Upgrader_Skin() );
			$result = $upgrader->upgrade( $slug );
		}

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array(
				'message' => $result->get_error_message(),
			) );
		}

		if ( $result === false ) {
			wp_send_json_error( array(
				'message' => __( 'Update fehlgeschlagen', 'ps-update-manager' ),
			) );
		}

		wp_send_json_success( array(
			'message' => sprintf( __( '%s wurde erfolgreich aktualisiert.', 'ps-update-manager' ), $slug ),
		) );
	}
}

