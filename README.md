# PS Update Manager

**Zentraler Update-Manager für alle PSource Plugins und Themes**

Ein leistungsstarkes WordPress-Plugin zur Verwaltung von Updates für deine eigenen Plugins und Themes direkt von GitHub oder einem eigenen Server - unabhängig vom WordPress.org Repository.

## 🎯 Features

- ✅ **Zentrales Dashboard** mit Übersicht aller registrierten Plugins/Themes
- ✅ **Automatische Updates** von GitHub Releases
- ✅ **Custom Update Server** Support
- ✅ **Update-Benachrichtigungen** im WordPress Admin
- ✅ **Plugin-Info Popup** mit Changelog
- ✅ **Multisite-kompatibel**
- ✅ **Minimale Integration** - nur wenige Zeilen Code pro Plugin
- ✅ **Links zu Docs, Support, Changelog** direkt im Dashboard
- ✅ **GitHub Integration** mit Release-Notes
- ✅ **Caching** für bessere Performance

## 🚀 Installation

1. **PS Update Manager installieren:**
   - Plugin in `/wp-content/plugins/ps-update-manager/` hochladen
   - Plugin im WordPress Admin aktivieren
   - Fertig! Dashboard ist unter "PS Updates" verfügbar

2. **In deinen Plugins/Themes integrieren:**
   - Integration-Code hinzufügen (siehe unten)

## 📦 Integration in deine Plugins

### Methode 1: Direkte Integration (empfohlen)

Füge in deiner Haupt-Plugin-Datei (z.B. `my-plugin.php`) nach dem Plugin-Header ein:

```php
/**
 * Plugin Name: My Plugin
 * Version: 1.0.0
 * ...
 */

// PS Update Manager Integration
add_action( 'plugins_loaded', function() {
    if ( function_exists( 'ps_register_product' ) ) {
        ps_register_product( array(
            'slug'          => 'my-plugin',
            'name'          => 'My Plugin',
            'version'       => '1.0.0',
            'type'          => 'plugin',
            'file'          => __FILE__,
            'github_repo'   => 'cp-psource/my-plugin', // Format: owner/repo
            'docs_url'      => 'https://docs.example.com',
            'support_url'   => 'https://github.com/cp-psource/my-plugin/issues',
            'changelog_url' => 'https://github.com/cp-psource/my-plugin/releases',
            'description'   => 'Eine kurze Beschreibung deines Plugins',
        ) );
    }
}, 5 );

// Optional: Admin Notice wenn Update Manager nicht installiert
add_action( 'admin_notices', function() {
    if ( ! function_exists( 'ps_register_product' ) && current_user_can( 'install_plugins' ) ) {
        $screen = get_current_screen();
        if ( $screen && in_array( $screen->id, array( 'plugins', 'plugins-network' ) ) ) {
            echo '<div class="notice notice-info"><p>';
            echo '<strong>My Plugin:</strong> ';
            echo 'Installiere den <a href="https://github.com/cp-psource/ps-update-manager">PS Update Manager</a> für automatische Updates.';
            echo '</p></div>';
        }
    }
});
```

### Methode 2: Mit Integration-Klasse

1. Kopiere `integration/ps-integration.php` in dein Plugin (z.B. in `psource/` Ordner)
2. In deiner Haupt-Plugin-Datei:

```php
// PS Update Manager Integration laden
require_once plugin_dir_path( __FILE__ ) . 'psource/ps-integration.php';

// Produkt registrieren
new PS_Product_Integration( __FILE__, array(
    'slug'          => 'my-plugin',
    'name'          => 'My Plugin',
    'version'       => '1.0.0',
    'type'          => 'plugin',
    'github_repo'   => 'cp-psource/my-plugin',
    'docs_url'      => 'https://docs.example.com',
    'support_url'   => 'https://github.com/cp-psource/my-plugin/issues',
) );
```

### Theme Integration

```php
// In functions.php deines Themes
add_action( 'after_setup_theme', function() {
    if ( function_exists( 'ps_register_product' ) ) {
        ps_register_product( array(
            'slug'        => 'my-theme',
            'name'        => 'My Theme',
            'version'     => '1.0.0',
            'type'        => 'theme',
            'file'        => get_stylesheet_directory() . '/style.css',
            'github_repo' => 'cp-psource/my-theme',
        ) );
    }
} );
```

## 🔧 GitHub Setup

### Release erstellen

1. Auf GitHub zu deinem Repo gehen
2. "Releases" → "Create a new release"
3. Tag erstellen (z.B. `v1.0.0` oder `1.0.0`)
4. Release-Notes hinzufügen (werden als Changelog angezeigt)
5. Optional: ZIP-File als Asset hochladen
6. "Publish release"

Der Update Manager prüft automatisch:
- **Latest Release** über GitHub API
- **Tag Name** wird als Version verwendet (v wird entfernt)
- **Release Body** wird als Changelog angezeigt
- **Zipball** oder Asset-ZIP als Download

### Private Repositories

Für private Repos kannst du ein GitHub Personal Access Token nutzen:

```php
// In wp-config.php oder Plugin
define( 'PS_GITHUB_TOKEN', 'ghp_your_token_here' );
```

## 🎨 Dashboard Features

Das PS Update Manager Dashboard bietet:

- **Statistiken:** Anzahl Produkte, verfügbare Updates, aktive Produkte
- **Produkt-Karten:** Übersicht mit Status, Version, Update-Info
- **Schnelllinks:** Direkte Links zu Docs, Support, GitHub
- **Update-Check:** Manuell Updates prüfen
- **Multisite:** Funktioniert im Netzwerk-Admin

## 📋 Verfügbare Parameter

```php
ps_register_product( array(
    // Erforderlich
    'slug'          => 'plugin-slug',          // Eindeutiger Slug
    'name'          => 'Plugin Name',          // Anzeigename
    'version'       => '1.0.0',                // Aktuelle Version
    'type'          => 'plugin',               // 'plugin' oder 'theme'
    'file'          => __FILE__,               // Haupt-Plugin-Datei
    
    // Update Quelle (mindestens eine)
    'github_repo'   => 'owner/repo',           // GitHub Repository
    'update_url'    => 'https://...',          // Custom Update URL
    
    // Optional
    'docs_url'      => 'https://...',          // Dokumentation
    'support_url'   => 'https://...',          // Support/Issues
    'changelog_url' => 'https://...',          // Changelog
    'description'   => 'Beschreibung...',      // Plugin-Beschreibung
    'author'        => 'Dein Name',            // Autor
    'author_url'    => 'https://...',          // Autor URL
) );
```

## 🌐 Custom Update Server

Statt GitHub kannst du auch einen eigenen Update-Server nutzen:

```php
ps_register_product( array(
    'slug'       => 'my-plugin',
    'name'       => 'My Plugin',
    'version'    => '1.0.0',
    'type'       => 'plugin',
    'file'       => __FILE__,
    'update_url' => 'https://dein-server.de/updates/my-plugin.json',
) );
```

Die JSON-Datei sollte folgendes Format haben:

```json
{
    "version": "1.2.3",
    "download_url": "https://dein-server.de/downloads/my-plugin-1.2.3.zip",
    "changelog": "## Version 1.2.3\n* Feature: Neues Feature\n* Fix: Bug behoben",
    "html_url": "https://dein-server.de/changelog"
}
```

## 🔒 Sicherheit

- ✅ Alle AJAX-Requests sind mit Nonces gesichert
- ✅ Capability-Checks (nur Admins können Updates verwalten)
- ✅ Input-Sanitization und Output-Escaping
- ✅ Keine direkten Datei-Zugriffe möglich

## 🐛 Debugging

Debug-Modus aktivieren in `wp-config.php`:

```php
define( 'WP_DEBUG', true );
define( 'WP_DEBUG_LOG', true );
```

Transients manuell löschen:

```php
// Im WordPress Admin → Werkzeuge → PS Updates → "Cache leeren"
// Oder via PHP:
PS_Update_Manager_GitHub_API::get_instance()->clear_cache();
```

## 📝 Lizenz

GPL v2 or later

## 🤝 Contributing

Contributions sind willkommen! Bitte erstelle Issues oder Pull Requests auf GitHub.

## 💡 Beispiel-Workflow

1. **Update Manager installieren** in deiner WordPress-Installation
2. **In jedem Plugin** 5-10 Zeilen Integration-Code hinzufügen
3. **GitHub Release** erstellen wenn neue Version fertig ist
4. **Automatisch** erscheint Update in WordPress Admin
5. **Ein Klick** und alle Plugins/Themes sind aktuell

## 🎯 Use Cases

- **Plugin-Entwickler:** Verteile Updates an Kunden ohne WordPress.org
- **Agency:** Verwalte Custom Plugins für mehrere Kunden
- **Multisite:** Updates für alle Sites im Netzwerk
- **Private Plugins:** Updates für geschlossene Benutzergruppen
- **GitHub-First:** Entwickle auf GitHub, updates automatisch

---

**Erstellt von PSource** | [GitHub](https://github.com/cp-psource) | [Support](https://github.com/cp-psource/ps-update-manager/issues)
