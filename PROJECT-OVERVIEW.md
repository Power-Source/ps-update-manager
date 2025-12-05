# 📊 PS Update Manager - Projekt-Übersicht

## ✅ Was wurde erstellt?

Ein **vollständiges, produktionsreifes Update-Management-System** für WordPress Plugins und Themes.

### 🏗️ Struktur

```
ps-update-manager/
├── ps-update-manager.php           # Hauptplugin mit Singleton-Pattern
├── README.md                        # Vollständige Dokumentation
├── QUICKSTART.md                    # 5-Minuten Schnellstart
├── CHANGELOG.md                     # Versionshistorie
├── readme.txt                       # WordPress.org Format
├── .gitignore                       # Git-Konfiguration
│
├── includes/
│   ├── class-product-registry.php   # Produkt-Verwaltung & Persistenz
│   ├── class-update-checker.php     # WordPress Update-API Integration
│   ├── class-github-api.php         # GitHub API mit Caching
│   └── class-admin-dashboard.php    # Admin-UI & AJAX
│
├── assets/
│   ├── css/
│   │   └── admin.css                # Dashboard-Styling (responsive)
│   └── js/
│       └── admin.js                 # AJAX Update-Check
│
├── integration/
│   └── ps-integration.php           # Kopiervorlage für Plugins
│
├── examples/
│   └── default-theme-integration-example.php  # Praxis-Beispiel
│
└── scripts/
    └── batch-integrate.sh           # Automatische Integration in mehrere Plugins
```

---

## 🎯 Hauptfunktionen

### 1. **Zentrales Plugin** (`ps-update-manager.php`)
- ✅ Singleton-Pattern für globalen Zugriff
- ✅ Hook-basierte Architektur
- ✅ Multisite-Unterstützung
- ✅ Textdomain für Übersetzungen
- ✅ Globale Helper-Funktion `ps_register_product()`

### 2. **Produkt-Registry** (`class-product-registry.php`)
- ✅ Registrierung von Plugins/Themes
- ✅ Persistenz in WordPress Options
- ✅ Status-Tracking (aktiv/inaktiv)
- ✅ Validierung und Defaults
- ✅ Filterung nach Typ

### 3. **Update-Checker** (`class-update-checker.php`)
- ✅ Integration in WordPress Update-System
- ✅ Plugin-Updates via `pre_set_site_transient_update_plugins`
- ✅ Theme-Updates via `pre_set_site_transient_update_themes`
- ✅ Plugin-Info Popup mit Changelog
- ✅ Custom Links im Plugin-Row
- ✅ Force-Check Funktion

### 4. **GitHub API** (`class-github-api.php`)
- ✅ Latest Release abrufen
- ✅ Repository-Info abrufen
- ✅ Transient-Caching (12h/24h)
- ✅ Error-Handling
- ✅ Versionsnummer-Normalisierung
- ✅ ZIP-Download URL (Asset oder Zipball)
- ✅ Changelog aus Release-Body

### 5. **Admin Dashboard** (`class-admin-dashboard.php`)
- ✅ Übersichts-Dashboard mit Statistiken
- ✅ Produkt-Tabelle mit allen Details
- ✅ AJAX Update-Check Button
- ✅ Responsive Card-Layout
- ✅ Status-Badges (aktiv/inaktiv, update verfügbar)
- ✅ Direktlinks zu Docs, Support, GitHub
- ✅ Multisite Netzwerk-Admin Integration

### 6. **Assets**
- ✅ **CSS:** Modernes Dashboard-Design, responsive, WordPress-konform
- ✅ **JavaScript:** AJAX-Handler, Loading-States, Error-Handling

### 7. **Integration** (`ps-integration.php`)
- ✅ Kopiervorlage für Plugins
- ✅ Admin Notice wenn Update Manager fehlt
- ✅ Aktivierungslink wenn installiert aber inaktiv
- ✅ Zwei Varianten: Klassen-basiert & minimal

### 8. **Dokumentation**
- ✅ **README.md:** Vollständige Feature-Liste, Integration, API
- ✅ **QUICKSTART.md:** 5-Minuten Setup-Guide
- ✅ **CHANGELOG.md:** Versionshistorie & Roadmap
- ✅ **readme.txt:** WordPress.org Format

### 9. **Automatisierung** (`batch-integrate.sh`)
- ✅ Shell-Script für Batch-Integration
- ✅ Automatisches Backup
- ✅ Alte Updater entfernen (optional)
- ✅ Farbiger Output
- ✅ Fehlerbehandlung

---

## 💡 Wie es funktioniert

### Flow: Plugin → Update Manager → GitHub → WordPress

```
┌─────────────────┐
│  Dein Plugin    │
│  (5 Zeilen Code)│
└────────┬────────┘
         │ ps_register_product()
         ↓
┌─────────────────┐
│ Product Registry│
│  (Persistenz)   │
└────────┬────────┘
         │
         ↓
┌─────────────────┐
│ Update Checker  │
│ (WordPress API) │
└────────┬────────┘
         │ Check Updates
         ↓
┌─────────────────┐
│   GitHub API    │
│  (mit Cache)    │
└────────┬────────┘
         │ Latest Release
         ↓
┌─────────────────┐
│ WordPress Admin │
│ (Update Notice) │
└─────────────────┘
```

### Integration in bestehende Plugins

**Vorher (Yanis Updater):**
```php
require 'psource/psource-plugin-update/plugin-update-checker.php';
use YahnisElsts\PluginUpdateChecker\v5\PucFactory;
 
$myUpdateChecker = PucFactory::buildUpdateChecker(
    'https://github.com/cp-psource/default-theme',
    __FILE__,
    'default-theme'
);
$myUpdateChecker->setBranch('master');
```
- ❌ Funktioniert nur wenn Plugin aktiv
- ❌ Große Abhängigkeit (ganze Library)
- ❌ Keine zentrale Verwaltung
- ❌ Keine Dashboard-Integration

**Nachher (PS Update Manager):**
```php
add_action( 'plugins_loaded', function() {
    if ( function_exists( 'ps_register_product' ) ) {
        ps_register_product( array(
            'slug'        => 'default-theme',
            'name'        => 'Standard Theme',
            'version'     => '1.0.5',
            'type'        => 'plugin',
            'file'        => __FILE__,
            'github_repo' => 'cp-psource/default-theme',
        ) );
    }
}, 5 );
```
- ✅ Nur 10 Zeilen Code
- ✅ Keine externe Abhängigkeit
- ✅ Zentrale Verwaltung
- ✅ Dashboard mit allen Infos
- ✅ Funktioniert auch wenn Plugin inaktiv

---

## 🚀 Nächste Schritte für dich

### 1. **Update Manager testen**
```bash
cd wp-content/plugins/ps-update-manager
# Plugin in WordPress aktivieren
# Dashboard unter "PS Updates" öffnen
```

### 2. **In ein Plugin integrieren (Test)**
Wähle ein Plugin aus (z.B. `default-theme`):

```bash
# Backup erstellen
cp default-theme/default-theme.php default-theme/default-theme.php.backup

# Integration-Code hinzufügen (manuell oder mit Script)
nano default-theme/default-theme.php
```

Füge nach dem Plugin-Header ein:
```php
add_action( 'plugins_loaded', function() {
    if ( function_exists( 'ps_register_product' ) ) {
        ps_register_product( array(
            'slug'        => 'default-theme',
            'name'        => 'Standard Theme',
            'version'     => '1.0.5',
            'type'        => 'plugin',
            'file'        => __FILE__,
            'github_repo' => 'cp-psource/default-theme',
        ) );
    }
}, 5 );
```

### 3. **Dashboard prüfen**
- WordPress Admin → PS Updates
- Plugin sollte dort erscheinen
- Status: Aktiv/Inaktiv
- Links zu GitHub, etc.

### 4. **Update testen**
- Auf GitHub: Release v1.0.6 erstellen
- Im Dashboard: "Updates prüfen" klicken
- Update sollte erscheinen
- Installation testen

### 5. **Batch-Integration für alle Plugins**
```bash
cd ps-update-manager/scripts
chmod +x batch-integrate.sh

# Script anpassen (Plugin-Liste)
nano batch-integrate.sh

# Ausführen
./batch-integrate.sh
```

### 6. **Auf GitHub pushen**
```bash
cd ps-update-manager
git init
git add .
git commit -m "Initial release v1.0.0"
git remote add origin git@github.com:cp-psource/ps-update-manager.git
git push -u origin master

# Release erstellen
git tag v1.0.0
git push origin v1.0.0
# Dann auf GitHub: Release aus Tag erstellen
```

---

## 📋 Vorteile deines neuen Systems

### Verglichen mit Yanis Updater:

| Feature | Yanis Updater | PS Update Manager |
|---------|---------------|-------------------|
| Code pro Plugin | ~15 Zeilen + Library | ~10 Zeilen |
| Zentrale Verwaltung | ❌ Nein | ✅ Ja |
| Dashboard | ❌ Nein | ✅ Ja |
| Funktioniert wenn inaktiv | ❌ Nein | ✅ Ja |
| Multisite-Admin | ❌ Nein | ✅ Ja |
| Links zu Docs/Support | ❌ Nein | ✅ Ja |
| Update-Statistiken | ❌ Nein | ✅ Ja |
| Maintenance | ❌ Jedes Plugin | ✅ Ein Plugin |

---

## 🎨 Was Nutzer sehen

### 1. **Dashboard** (`/wp-admin/admin.php?page=ps-update-manager`)
- Schöne Statistiken (Anzahl Produkte, Updates, Aktive)
- Produkt-Karten mit Status und Links
- Update-Button
- Info-Box über Open Source

### 2. **Updates-Seite** (`/wp-admin/update-core.php`)
- PSource-Produkte erscheinen in der Update-Liste
- Changelog-Link
- Standard WordPress Update-Prozess

### 3. **Plugins-Seite** (`/wp-admin/plugins.php`)
- Zusätzliche Links: Dokumentation, Support, Changelog
- Update-Badge wenn verfügbar

---

## 🔐 Sicherheit & Best Practices

✅ **Implementiert:**
- Nonces für AJAX
- Capability Checks (manage_options)
- Input Sanitization
- Output Escaping
- Prepared Statements (WordPress API)
- Kein direkter File-Access

✅ **Performance:**
- Transient Caching (12h/24h)
- Lazy Loading
- Conditional Asset Loading
- Efficient Database Queries

---

## 🛠️ Erweiterbar für später

Das System ist so designed, dass du einfach erweitern kannst:

- **Private Repos:** GitHub Token Support hinzufügen
- **Beta Channels:** `ps_register_product(['channel' => 'beta'])`
- **Email Notifications:** Hook in Update-Checker
- **Analytics:** Tracking welche Plugins Updates bekommen
- **White-Label:** Logo/Branding anpassen
- **REST API:** Externe Abfragen ermöglichen

---

## 📞 Support & Contribution

- **Issues:** https://github.com/cp-psource/ps-update-manager/issues
- **Pull Requests:** Immer willkommen!
- **Diskussionen:** GitHub Discussions

---

**Viel Erfolg mit deinem neuen Update-System!** 🚀

Bei Fragen oder Problemen einfach melden.
