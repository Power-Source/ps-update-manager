# 🚀 Quick Start Guide - PS Update Manager

## In 5 Minuten eingerichtet!

### Schritt 1: Update Manager installieren

```bash
# Via WordPress Admin:
Plugins → Installieren → Upload → ps-update-manager.zip

# Oder manuell:
cd wp-content/plugins/
# Entpacke das ps-update-manager Verzeichnis hier
```

**Aktivieren:** Plugins → PS Update Manager → Aktivieren

### Schritt 2: In bestehende Plugins integrieren

Öffne die Haupt-PHP-Datei deines Plugins (z.B. `my-plugin.php`) und füge **nach dem Plugin-Header** ein:

```php
/**
 * Plugin Name: Mein Plugin
 * Version: 1.0.0
 * ...
 */

// ===== PS UPDATE MANAGER - START =====
add_action( 'plugins_loaded', function() {
    if ( function_exists( 'ps_register_product' ) ) {
        ps_register_product( array(
            'slug'        => 'mein-plugin',              // Plugin Slug (eindeutig!)
            'name'        => 'Mein Plugin',              // Anzeigename
            'version'     => '1.0.0',                    // Aktuelle Version
            'type'        => 'plugin',                   // 'plugin' oder 'theme'
            'file'        => __FILE__,                   // Diese Zeile nicht ändern!
            'github_repo' => 'username/mein-plugin',     // Dein GitHub Repo
        ) );
    }
}, 5 );
// ===== PS UPDATE MANAGER - ENDE =====

// Rest deines Plugin-Codes...
```

**Das war's!** Dein Plugin ist jetzt registriert. 🎉

### Schritt 3: GitHub Release erstellen

1. Gehe zu deinem GitHub Repo
2. Klicke auf "Releases" → "Create a new release"
3. Tag: `v1.0.1` (oder `1.0.1`)
4. Title: `Version 1.0.1`
5. Description: 
   ```markdown
   ## Version 1.0.1
   
   ### Neue Features
   - Feature 1
   - Feature 2
   
   ### Bugfixes
   - Fix 1
   - Fix 2
   ```
6. "Publish release"

### Schritt 4: Update testen

1. In WordPress Admin: **PS Updates** → **Dashboard**
2. Klick auf **"Updates prüfen"**
3. Dein Update sollte erscheinen! ✅
4. Zu "Dashboard" → "Aktualisierungen" gehen
5. Update installieren

---

## 📋 Kopiervorlage für alle Plugins

Hier ist eine Kopiervorlage die du in **alle** deine Plugins einfügen kannst:

```php
// ===== PS UPDATE MANAGER - START =====
add_action( 'plugins_loaded', function() {
    if ( function_exists( 'ps_register_product' ) ) {
        ps_register_product( array(
            'slug'          => 'PLUGIN-SLUG',                    // ← ANPASSEN
            'name'          => 'PLUGIN NAME',                    // ← ANPASSEN
            'version'       => '1.0.0',                          // ← ANPASSEN
            'type'          => 'plugin',
            'file'          => __FILE__,
            'github_repo'   => 'Power-Source/REPO-NAME',        // ← ANPASSEN
            'docs_url'      => 'https://deine-docs.de',         // ← Optional
            'support_url'   => 'https://github.com/Power-Source/REPO-NAME/issues',
            'description'   => 'Kurze Beschreibung',            // ← Optional
        ) );
    }
}, 5 );

// Optional: Hinweis wenn Update Manager fehlt
add_action( 'admin_notices', function() {
    if ( ! function_exists( 'ps_register_product' ) && current_user_can( 'install_plugins' ) ) {
        $screen = get_current_screen();
        if ( $screen && in_array( $screen->id, array( 'plugins', 'plugins-network' ) ) ) {
            echo '<div class="notice notice-info is-dismissible"><p>';
            echo '<strong>PLUGIN NAME:</strong> ';  // ← ANPASSEN
            echo 'Installiere den <a href="https://github.com/Power-Source/ps-update-manager">PS Update Manager</a> für Updates.';
            echo '</p></div>';
        }
    }
});
// ===== PS UPDATE MANAGER - ENDE =====
```

---

## 🔄 Batch-Integration in mehrere Plugins

Du willst das in alle Plugins gleichzeitig einbauen? Hier ist ein Shell-Script:

```bash
#!/bin/bash
# integration-script.sh

PLUGINS=(
    "default-theme"
    "events-and-bookings"
    "marketpress"
    "powerform"
    "ps-chat"
)

for plugin in "${PLUGINS[@]}"; do
    echo "Integriere $plugin..."
    
    # Backup erstellen
    cp "$plugin/$plugin.php" "$plugin/$plugin.php.backup"
    
    # Integration-Code hinzufügen
    # (Hier würdest du mit sed oder einem Editor arbeiten)
    
    echo "✓ $plugin integriert"
done

echo "Fertig! Alle Plugins integriert."
```

---

## 🎯 Checkliste

- [ ] PS Update Manager installiert & aktiviert
- [ ] In Plugins den Integration-Code eingefügt
- [ ] `slug`, `name`, `version` angepasst
- [ ] `github_repo` korrekt eingetragen
- [ ] GitHub Release erstellt (Tag v1.0.0)
- [ ] Im WordPress Admin "Updates prüfen" geklickt
- [ ] Update erscheint in der Liste
- [ ] Update funktioniert ✅

---

## ⚡ Automatisierung für Profis

### GitHub Actions für Auto-Release

Erstelle `.github/workflows/release.yml`:

```yaml
name: Create Release
on:
  push:
    tags:
      - 'v*'

jobs:
  release:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v2
      
      - name: Create Release
        uses: actions/create-release@v1
        env:
          GITHUB_TOKEN: ${{ secrets.GITHUB_TOKEN }}
        with:
          tag_name: ${{ github.ref }}
          release_name: Release ${{ github.ref }}
          draft: false
          prerelease: false
```

Jetzt erstellt GitHub automatisch ein Release wenn du einen Tag pushst:

```bash
git tag v1.0.1
git push origin v1.0.1
```

---

## 🆘 Troubleshooting

### "Keine Updates gefunden"

1. **GitHub Repo öffentlich?** Private Repos brauchen Access Token
2. **Release erstellt?** Muss ein "Latest Release" sein
3. **Tag korrekt?** Format: `v1.0.0` oder `1.0.0`
4. **Cache leeren:** PS Updates → "Updates prüfen"

### "Update schlägt fehl"

1. **Download-URL korrekt?** GitHub sollte ZIP bereitstellen
2. **Ordnername im ZIP:** Muss mit Plugin-Slug übereinstimmen
3. **Schreibrechte:** `wp-content/plugins/` muss beschreibbar sein

### "Plugin erscheint nicht im Dashboard"

1. **Integration-Code ausgeführt?** `plugins_loaded` Hook korrekt?
2. **Update Manager aktiv?** Plugin muss aktiviert sein
3. **Multisite:** Netzwerkweit aktivieren

---

## 📚 Weitere Ressourcen

- [Vollständige Dokumentation](README.md)
- [GitHub Repo](https://github.com/Power-Source/ps-update-manager)
- [Issues melden](https://github.com/Power-Source/ps-update-manager/issues)

---

**Viel Erfolg!** 🚀 Bei Fragen einfach ein Issue auf GitHub erstellen.
