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
            echo '<strong>Bekomme Updates und mehr PSOURCE mit dem PSOURCE Manager:</strong> ';
            
            if ( $is_installed ) {
                // Aktivierungs-Link
                $activate_url = wp_nonce_url(
                    admin_url( 'plugins.php?action=activate&plugin=' . urlencode( $plugin_file ) ),
                    'activate-plugin_' . $plugin_file
                );
                echo sprintf(
                    __( 'Aktiviere den <a href="%s">PSOURCE Manager</a> für automatische Updates.', 'textdomain' ),
                    esc_url( $activate_url )
                );
            } else {
                // Download-Link
                echo sprintf(
                    __( 'Installiere den <a href="%s" target="_blank">PSOURCE Manager</a> für automatische Updates.', 'textdomain' ),
                    'https://github.com/Power-Source/ps-update-manager/releases/latest'
                );
            }
            
            echo '</p></div>';
        }
    }
});
```

**Fertig!**


## � Plugin-Kompatibilität definieren (Optional)

Du kannst definieren, welche Plugins **optional** mit deinem Plugin zusammenarbeiten können. Das System zeigt dann automatisch hilfreiche Hinweise im PSOURCES-Katalog.

### **Kompatibilität im Manifest definieren**

Füge `compatible_with` in deine Plugin-Definition ein:

```php
// includes/products-manifest.php

'ps-mitgliedschaften' => array(
    'type'        => 'plugin',
    'name'        => 'PS Mitgliedschaften',
    'repo'        => 'Power-Source/ps-mitgliedschaften',
    'description' => '...',
    'category'    => 'community',
    'icon'        => 'dashicons-groups',
    'compatible_with' => array(
        'marketpress' => 'Verwalte Zahlungen & Abrechnungen für Mitgliedschaften',
        'ps-dsgvo'    => 'DSGVO-konforme Mitgliedschaftsverwaltung',
    ),
),
```

### **Wie es funktioniert**

Das System **prüft automatisch** und zeigt **intelligente Banner** im PSOURCES-Katalog:

| Situation | Banner | Farbe |
|-----------|--------|-------|
| Plugin X ist **aktiv** | ✅ "Funktioniert mit: MarketPress" | 🟢 Grün |
| Plugin X ist **installiert, aber inaktiv** | ℹ️ "Kann mit folgendem zusammenarbeiten..." | 🟡 Orange |
| Plugin X ist **nicht installiert** | 💡 "Tipp: Nutze mit: MarketPress" | 🔵 Blau |

### **Technische Details**

- Verwendete Klasse: `PS_Update_Manager_Dependency_Manager`
- Banner-Template: `render_compatibility_banner()`
- **Wichtig:** Kompatibilität ist OPTIONAL - kein erzwungenes Installieren!
- Das System funktioniert **bidirektional**: Wenn A mit B kompatibel ist, wird das bei beiden angezeigt

### **Beispiele**

```php
// e-Commerce-Kombination
'marketpress' => array(
    'compatible_with' => array(
        'ps-mitgliedschaften' => 'Integriere Mitgliedschaften direkt in deinen Shop',
        'ps-smart-crm'        => 'Verwende CRM für Kundenverwaltung',
    ),
),

// Datenschutz-Integration
'ps-dsgvo' => array(
    'compatible_with' => array(
        'marketpress'      => 'Datenschutzerklärungen für E-Commerce',
        'ps-bloghosting'   => 'DSGVO-Compliance für Bloghosting',
    ),
),
```

---

## �📦 GitHub Release erstellen

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
