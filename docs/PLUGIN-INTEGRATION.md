# Plugin Integration Guide (v2.0)

## 🎯 Neue Strategie: Manifest-basiert (KEINE manuelle Registrierung!)

Mit Version 2.0 des PS Update Managers erfolgt die **automatische Erkennung** aller PSource-Plugins via **Manifest-Datei**. Das bedeutet:

✅ **KEINE** `ps_register_product()` Aufrufe mehr nötig
✅ **KEINE** manuelle Registrierung im Plugin-Code
✅ **NUR** ein einfacher Admin-Hinweis wenn Update Manager fehlt

---

## 📋 Integration in 2 Schritten

### **Schritt 1: Plugin ins Manifest eintragen**

Trage dein Plugin in die zentrale Manifest-Datei ein:

```php
// includes/products-manifest.php

return array(
    'dein-plugin-slug' => array(
        'type'        => 'plugin',
        'name'        => 'Dein Plugin Name',
        'repo'        => 'Power-Source/dein-plugin-slug',  // GitHub Repo
        'description' => 'Kurze Beschreibung des Plugins',
        'category'    => 'development',  // development, communication, media, etc.
        'icon'        => 'dashicons-admin-plugins',
    ),
);
```

### **Schritt 2: Admin-Hinweis im Plugin**

Füge folgenden Code in deine Haupt-Plugin-Datei ein (direkt nach dem Plugin-Header):

```php
// PS Update Manager - Hinweis wenn nicht installiert/aktiviert
add_action( 'admin_notices', function() {
    if ( ! function_exists( 'ps_register_product' ) && current_user_can( 'install_plugins' ) ) {
        $screen = get_current_screen();
        if ( $screen && in_array( $screen->id, array( 'plugins', 'plugins-network' ) ) ) {
            $plugin_file = 'ps-update-manager/ps-update-manager.php';
            $all_plugins = get_plugins();
            $is_installed = isset( $all_plugins[ $plugin_file ] );
            
            echo '<div class="notice notice-warning is-dismissible"><p>';
            echo '<strong>Dein Plugin:</strong> ';
            
            if ( $is_installed ) {
                // Aktivierungs-Link
                $activate_url = wp_nonce_url(
                    admin_url( 'plugins.php?action=activate&plugin=' . urlencode( $plugin_file ) ),
                    'activate-plugin_' . $plugin_file
                );
                echo sprintf(
                    __( 'Aktiviere den <a href="%s">PS Update Manager</a> für automatische Updates.', 'textdomain' ),
                    esc_url( $activate_url )
                );
            } else {
                // Download-Link
                echo sprintf(
                    __( 'Installiere den <a href="%s" target="_blank">PS Update Manager</a> für automatische Updates.', 'textdomain' ),
                    'https://github.com/Power-Source/ps-update-manager/releases/latest'
                );
            }
            
            echo '</p></div>';
        }
    }
});
```

**Fertig!** 🎉

---

## 🔄 Migration von v1.0

Falls dein Plugin noch die alte `ps_register_product()` Methode nutzt:

### **ALT (v1.0) - NICHT MEHR NÖTIG:**
```php
add_action( 'plugins_loaded', function() {
    if ( function_exists( 'ps_register_product' ) ) {
        ps_register_product( array(
            'slug'          => 'mein-plugin',
            'name'          => 'Mein Plugin',
            'version'       => '1.0.0',
            'type'          => 'plugin',
            'file'          => __FILE__,
            'github_repo'   => 'Power-Source/mein-plugin',
            // ... viele weitere Felder
        ) );
    }
}, 5 );
```

### **NEU (v2.0) - Einfach löschen!**

Der gesamte Registrierungs-Code kann entfernt werden. Der Scanner findet dein Plugin automatisch, solange es im Manifest eingetragen ist.

---

## 🎁 Vorteile der neuen Methode

| Vorher (v1.0) | Jetzt (v2.0) |
|---------------|--------------|
| ❌ Manuelle Registrierung in jedem Plugin | ✅ Automatische Erkennung |
| ❌ 20+ Zeilen Boilerplate-Code | ✅ Nur Admin-Hinweis |
| ❌ Plugin muss aktiv sein für Updates | ✅ Updates auch wenn inaktiv |
| ❌ Doppelte Datenpflege (Code + Manifest) | ✅ Single Source of Truth (Manifest) |

---

## 📦 GitHub Release erstellen

Damit Updates funktionieren, muss dein Plugin **GitHub Releases** nutzen:

1. Pushe deinen Code nach GitHub
2. Erstelle einen neuen Release: `https://github.com/Power-Source/dein-plugin/releases/new`
3. Tag-Format: `v1.0.0` (mit vorangestelltem "v")
4. Titel: Version + Kurzbeschreibung
5. Beschreibung: Changelog

Der Update Manager prüft automatisch alle 12 Stunden nach neuen Releases.

---

## 🛠️ Beispiel-Dateien

- `examples/plugin-integration-template.php` - Kopiervorlage für neue Plugins
- `examples/default-theme-integration-example.php` - Theme-Integration

---

## 🔗 Links

- **GitHub:** https://github.com/Power-Source/ps-update-manager
- **Support:** https://github.com/Power-Source/ps-update-manager/issues
- **Manifest-Datei:** `includes/products-manifest.php`
