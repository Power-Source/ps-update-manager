<?php
/**
 * PS Update Manager - Plugin Integration Template
 * 
 * Füge diesen Code in die Haupt-Plugin-Datei ein (direkt nach dem Plugin-Header).
 * Der Update Manager erkennt dein Plugin automatisch via Manifest - keine manuelle
 * Registrierung mehr nötig!
 * 
 * @package PS_Update_Manager
 * @version 2.0.0
 */

// PS Update Manager - Hinweis wenn nicht installiert/aktiviert
add_action( 'admin_notices', function() {
    // Prüfe ob Update Manager aktiv ist
    if ( ! function_exists( 'ps_register_product' ) && current_user_can( 'install_plugins' ) ) {
        $screen = get_current_screen();
        
        // Nur auf Plugin-Seiten anzeigen
        if ( $screen && in_array( $screen->id, array( 'plugins', 'plugins-network' ) ) ) {
            
            // Prüfe ob Update Manager bereits installiert aber inaktiv ist
            $plugin_file = 'ps-update-manager/ps-update-manager.php';
            $all_plugins = get_plugins();
            $is_installed = isset( $all_plugins[ $plugin_file ] );
            
            echo '<div class="notice notice-warning is-dismissible"><p>';
            echo '<strong>' . esc_html__( 'Dein Plugin Name', 'dein-textdomain' ) . ':</strong> ';
            
            if ( $is_installed ) {
                // Installiert aber inaktiv - Aktivierungs-Link anzeigen
                $activate_url = wp_nonce_url(
                    admin_url( 'plugins.php?action=activate&plugin=' . urlencode( $plugin_file ) ),
                    'activate-plugin_' . $plugin_file
                );
                echo sprintf(
                    __( 'Aktiviere den <a href="%s">PS Update Manager</a> für automatische Updates von GitHub.', 'dein-textdomain' ),
                    esc_url( $activate_url )
                );
            } else {
                // Nicht installiert - Download-Link zum neuesten Release
                echo sprintf(
                    __( 'Installiere den <a href="%s" target="_blank">PS Update Manager</a> für automatische Updates aller PSource Plugins & Themes.', 'dein-textdomain' ),
                    'https://github.com/Power-Source/ps-update-manager/releases/latest'
                );
            }
            
            echo '</p></div>';
        }
    }
});
