# PS Update Manager

**Zentraler Update-Manager für alle PSource Plugins und Themes**

Ein leistungsstarkes WordPress-Plugin zur Verwaltung von Updates für deine eigenen Plugins und Themes direkt von GitHub - unabhängig vom WordPress.org Repository. Mit automatischer Erkennung via Manifest-System!

## 🎯 Features

- ✅ **Product Store** - Entdecke und installiere alle PSource Plugins/Themes mit 1-Click
- ✅ **Automatische Erkennung** - Keine manuelle Registrierung nötig (Manifest-basiert)
- ✅ **Zentrales Dashboard** mit Übersicht aller installierten Produkte
- ✅ **GitHub Integration** - Automatische Updates von GitHub Releases
- ✅ **Update-Benachrichtigungen** im WordPress Admin
- ✅ **1-Click Installation** direkt von GitHub
- ✅ **Plugin-Info Popup** mit Changelog und Release-Notes
- ✅ **Multisite-kompatibel** mit Netzwerk-Admin Unterstützung
- ✅ **Performance-optimiert** mit Multi-Layer Caching
- ✅ **Self-Updating** - Update Manager kann sich selbst aktualisieren

## 🚀 Installation

1. **PS Update Manager installieren:**
   ```bash
   # Download vom neuesten Release
   cd /wp-content/plugins/
   wget https://github.com/Power-Source/ps-update-manager/releases/latest/download/ps-update-manager.zip
   unzip ps-update-manager.zip
   ```

2. **Im WordPress Admin aktivieren:**
   - Plugin unter "Plugins" aktivieren
   - Dashboard verfügbar unter "PS Updates"

3. **Fertig!** 🎉
   - Alle PSource Plugins werden automatisch erkannt
   - Neue Plugins können im "Alle Produkte" Store installiert werden

## 📦 Integration in deine Plugins (v2.0)

### **Neu in v2.0: Manifest-basierte Erkennung**

Keine manuelle Registrierung mehr nötig! Der Update Manager erkennt Plugins automatisch via Manifest.

### **Schritt 1: Plugin ins Manifest eintragen**

Bearbeite `includes/products-manifest.php`:

```php
return array(
    'dein-plugin' => array(
        'type'        => 'plugin',
        'name'        => 'Dein Plugin Name',
        'repo'        => 'Power-Source/dein-plugin',
        'description' => 'Kurzbeschreibung',
        'category'    => 'development',
        'icon'        => 'dashicons-admin-plugins',
    ),
);
```

### **Schritt 2: Admin-Hinweis hinzufügen (optional)**

Nur noch ein einfacher Hinweis wenn Update Manager fehlt:

```php
// PS Update Manager - Hinweis wenn nicht installiert
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
                // Aktivierungs-Link wenn installiert aber inaktiv
                $activate_url = wp_nonce_url(
                    admin_url( 'plugins.php?action=activate&plugin=' . urlencode( $plugin_file ) ),
                    'activate-plugin_' . $plugin_file
                );
                echo sprintf(
                    __( 'Aktiviere den <a href="%s">PS Update Manager</a> für automatische Updates.', 'textdomain' ),
                    esc_url( $activate_url )
                );
            } else {
                // Download-Link wenn nicht installiert
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

**Fertig!** 🎉 Der Scanner erkennt dein Plugin automatisch.

---

## 🔄 Migration von v1.0 zu v2.0

Die alte `ps_register_product()` Methode funktioniert weiterhin, ist aber nicht mehr nötig:

### **ALT (v1.0) - Kann entfernt werden:**
```php
add_action( 'plugins_loaded', function() {
    if ( function_exists( 'ps_register_product' ) ) {
        ps_register_product( array(
            'slug'          => 'my-plugin',
            // ... 15+ Zeilen Code
        ) );
    }
}, 5 );
```

### **NEU (v2.0) - Einfach löschen!**
Der gesamte Registrierungs-Code kann entfernt werden. Trage das Plugin nur ins Manifest ein.

---

## 📚 Dokumentation

- **[Plugin Integration Guide](docs/PLUGIN-INTEGRATION.md)** - Detaillierte Anleitung
- **[Developer Documentation](docs/dev.md)** - Manifest-System & API
- **[Quickstart Guide](QUICKSTART.md)** - Schnelleinstieg
- **[Project Overview](PROJECT-OVERVIEW.md)** - Architektur & Features

---

## 🏪 Product Store

Der "Alle Produkte" Bereich zeigt alle im Manifest eingetragenen Plugins/Themes:

- **Nicht installiert** → "Installieren"-Button (Download von GitHub)
- **Installiert & Inaktiv** → "Aktivieren"-Button
- **Aktiv & Aktuell** → Grüner Badge
- **Update verfügbar** → "Jetzt aktualisieren"-Button

---

## 🔧 Multisite Support

### Netzwerk-Modus:
- Dashboard nur im Netzwerk-Admin sichtbar
- Settings-Seite für rollenbasierte Zugriffskontrolle
- Netzwerkadmin hat immer vollen Zugriff

### Einstellungen:
Unter "Einstellungen" können Network-Admins festlegen, welche Rollen Zugriff haben:
- ☑️ Administrator
- ☑️ Editor  
- ☐ Author
- ☐ Contributor

---

## 🎨 Dashboard Features

### **PS Updates Dashboard:**
- Übersicht aller installierten Plugins/Themes
- Update-Status mit Icons (✓ Aktuell / ⚠️ Update verfügbar)
- Auto-Discovery Badge für gescannte Plugins
- Links zu GitHub, Support, Docs

### **Alle Produkte:**
- Store-Interface mit Product Cards
- Status-Badges (Nicht installiert / Inaktiv / Aktiv / Update)
- 1-Click Installation von GitHub
- Direktlinks zu Changelog, Issues, Repository

---

## ⚡ Performance

- **Multi-Layer Caching:**
  - Products: 1 Woche
  - Update Info: 6 Stunden  
  - Status: 1 Minute
  - GitHub API: 12 Stunden
- **Lazy Loading:** Settings nur in `is_admin()` Kontext
- **WP-Cron:** Täglicher Scan für neue Plugins

---

## 🔐 Sicherheit

- **Manifest-basierte Authentifizierung** - Nur gelistete Repos erlaubt
- **Nonce-Prüfung** bei allen AJAX-Requests
- **Capability-Checks** für alle Admin-Aktionen
- **Sanitized Input** bei Installation/Updates

---

## 📋 GitHub Release Format

Für automatische Updates muss dein Plugin GitHub Releases nutzen:

1. **Tag-Format:** `v1.0.0` (mit "v" Präfix)
2. **Release Title:** Version + Beschreibung
3. **Release Notes:** Changelog im Markdown-Format
4. **Assets:** Optional - ZIP wird automatisch von GitHub erstellt

Beispiel-Release-URL:
```
https://github.com/Power-Source/ps-chat/releases/tag/v1.2.0
```

---

## 🛠️ Entwicklung

```bash
# Repository clonen
git clone https://github.com/Power-Source/ps-update-manager.git

# Verzeichnisstruktur
ps-update-manager/
├── includes/              # Core-Klassen
│   ├── class-admin-dashboard.php
│   ├── class-github-api.php
│   ├── class-product-registry.php
│   ├── class-product-scanner.php
│   ├── class-settings.php
│   ├── class-update-checker.php
│   └── products-manifest.php  # Single Source of Truth
├── assets/                # CSS & JS
├── docs/                  # Dokumentation
├── examples/              # Integration-Beispiele
└── ps-update-manager.php  # Main Plugin File
```

---

## 🤝 Contributing

1. Fork das Repository
2. Feature-Branch erstellen (`git checkout -b feature/AmazingFeature`)
3. Änderungen committen (`git commit -m 'Add AmazingFeature'`)
4. Branch pushen (`git push origin feature/AmazingFeature`)
5. Pull Request öffnen

---

## 📜 Lizenz

GPL v2 oder höher

---

## 🔗 Links

- **GitHub Repository:** https://github.com/Power-Source/ps-update-manager
- **Issues & Support:** https://github.com/Power-Source/ps-update-manager/issues
- **PSource Organization:** https://github.com/Power-Source
- **Legacy Repos:** https://github.com/cp-psource

---

## 📝 Changelog

### v2.0.0 (2025-12-07)
- ✨ **Product Store** mit 1-Click Installation
- ✨ **Manifest-basierte Auto-Discovery** (keine manuelle Registrierung)
- ✨ **Network-Admin Settings** mit rollenbasierter Zugriffskontrolle
- 🚀 **Performance-Optimierung** mit Multi-Layer Caching
- 🔐 **Manifest-Authentifizierung** (nur Power-Source Repos)
- 🎨 **Neues Store-Design** mit Product Cards
- 📦 **Self-Update** Fähigkeit

### v1.0.0
- 🎉 Initial Release
- ✅ GitHub Updates
- ✅ Product Registry
- ✅ Basic Dashboard

