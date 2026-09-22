# PS Update Manager - Developer Documentation

## PSOURCE Manifest (products-manifest.php)

### Übersicht

Das **PSOURCE Manifest** (`includes/products-manifest.php`) ist die zentrale und einzige Quelle für alle offiziellen PSOURCE-Einträge. Nur Einträge, die in dieser Datei definiert sind, werden vom Update Manager erkannt und verwaltet.

### Sicherheitskonzept

Der manifest-basierte Ansatz bietet mehrere Sicherheitsvorteile:

- ✅ **Manipulationssicher**: Nur explizit gelistete Produkte werden erkannt
- ✅ **Keine Pattern-Matching-Schwächen**: Kein unsicheres Durchsuchen von Plugin-Headers
- ✅ **Zentrale Kontrolle**: Ein Repository (`Power-Source`) als Quelle der Wahrheit
- ✅ **Versionskontrolle**: Änderungen am Manifest sind nachvollziehbar über Git
- ✅ **Konsistenz**: Alle Metadaten (Icons, Links, Beschreibungen) sind einheitlich

### Struktur

Die Manifest-Datei gibt ein assoziatives Array zurück:

```php
return array(
    'plugin-slug' => array(
        'type'        => 'plugin',           // 'plugin' oder 'theme'
        'name'        => 'Plugin Name',      // Anzeigename
        'repo'        => 'Power-Source/repo-name', // GitHub Repo
        'description' => 'Beschreibung',     // Kurzbeschreibung
        'category'    => 'category',         // Kategorie für UI
        'icon'        => 'dashicons-name',   // ClassicPress Dashicon
    ),
);
```

### Pflichtfelder

| Feld | Typ | Beschreibung | Beispiel |
|------|-----|--------------|----------|
| `type` | string | Produkttyp | `'plugin'` oder `'theme'` |
| `name` | string | Anzeigename | `'PS Update Manager'` |
| `repo` | string | GitHub Repository | `'Power-Source/ps-update-manager'` |
| `description` | string | Kurzbeschreibung | `'Zentraler Update-Manager...'` |

### Optionale Felder

| Feld | Typ | Standard | Beschreibung |
|------|-----|----------|--------------|
| `category` | string | `'general'` | Kategorie für Sortierung/Filterung |
| `icon` | string | `'dashicons-admin-plugins'` | ClassicPress Dashicon-Name |

### Kategorien

Empfohlene Kategorien für bessere Organisation:

- `development` - Entwicklungs-Tools
- `multisite` - Multisite-spezifische Plugins
- `community` - Community/Social Features
- `ecommerce` - E-Commerce Plugins
- `content` - Content-Management
- `theme` - Themes
- `general` - Allgemeine Plugins

## Neuen PSOURCE-Eintrag hinzufügen

### Schritt 1: Repository erstellen

Stelle sicher, dass das Repository in der GitHub-Organisation existiert:
- **Power-Source** (Organisation für alle PSOURCE-Projekte)

### Schritt 2: Manifest aktualisieren

Füge das neue Produkt in `includes/products-manifest.php` hinzu:

```php
'mein-neues-plugin' => array(
    'type'        => 'plugin',
    'name'        => 'Mein Neues Plugin',
    'repo'        => 'Power-Source/mein-neues-plugin',
    'description' => 'Ein tolles neues Plugin für ClassicPress.',
    'category'    => 'content',
    'icon'        => 'dashicons-admin-post',
),
```

### Schritt 3: Plugin-Header vorbereiten

Das Plugin selbst sollte diese Header-Informationen haben:

```php
/**
 * Plugin Name: Mein Neues Plugin
 * Plugin URI: https://github.com/Power-Source/mein-neues-plugin
 * Description: Ein tolles neues Plugin für ClassicPress.
 * Version: 1.0.0
 * Author: PSource
 * Author URI: https://github.com/Power-Source
 * Text Domain: mein-neues-plugin
 * GitHub Plugin URI: Power-Source/mein-neues-plugin
 */
```

### Schritt 4: Slug-Konsistenz

**Wichtig**: Der Slug im Manifest muss mit dem Plugin-Verzeichnis-Namen übereinstimmen!

- ✅ Manifest-Slug: `'mein-neues-plugin'`
- ✅ Verzeichnis: `wp-content/plugins/mein-neues-plugin/`
- ✅ Hauptdatei: `wp-content/plugins/mein-neues-plugin/mein-neues-plugin.php`

### Schritt 5: Testen

1. Plugin im ClassicPress-Verzeichnis installieren
2. PS Update Manager → "PSOURCE scannen" klicken
3. Neues Plugin sollte in der Liste erscheinen mit "Auto"-Badge

## Scanner-Funktionsweise

### Auto-Discovery

Der Product Scanner (`class-product-scanner.php`) durchsucht automatisch:

1. **Plugin-Verzeichnis**: `wp-content/plugins/`
2. **Theme-Verzeichnis**: `wp-content/themes/`

Für jedes gefundene Plugin/Theme:
1. Extrahiert den Slug (Verzeichnisname)
2. Prüft, ob Slug im Manifest existiert
3. Verifiziert den Typ (Plugin vs. Theme)
4. Registriert das Produkt mit Manifest-Metadaten

### Scan-Zeitpunkte

- **Initial**: Beim ersten Laden des Update Managers
- **Täglich**: Via WP-Cron automatisch
- **Manuell**: Via "Produkte scannen" Button im Dashboard

### Caching

Scan-Ergebnisse werden gecacht:
- **PSOURCE Katalog**: 1 Woche (Transient)
- **Scan-Zeitpunkt**: 1 Woche
- **Cache löschen**: Automatisch bei neuem Scan

## Metadaten-Handling

### Automatisch generierte URLs

`docs_url` kommt aus dem `Plugin URI` / `Theme URI` Header des jeweiligen Produkts (kein separates
GitHub-Pages-Hosting nötig). Nur falls der Header leer ist, wird auf das GitHub-Repo verlinkt:

```php
'docs_url'      => ! empty( $plugin_data['PluginURI'] ) ? $plugin_data['PluginURI'] : 'https://github.com/' . $manifest['repo'],
'support_url'   => 'https://github.com/' . $manifest['repo'] . '/issues',
'changelog_url' => 'https://github.com/' . $manifest['repo'] . '/releases',
'author_url'    => 'https://github.com/Power-Source',
```

### Icon-System

Icons werden aus ClassicPress Dashicons ausgewählt:
- [Dashicons Übersicht](https://developer.wordpress.org/resource/dashicons/)

Beispiele:
- `dashicons-update` - Update Manager
- `dashicons-format-chat` - Chat
- `dashicons-admin-appearance` - Themes
- `dashicons-admin-plugins` - Plugins

## API für Entwickler

### Manifest abfragen

```php
$scanner = PS_Update_Manager_Product_Scanner::get_instance();

// Alle offiziellen Produkte
$products = $scanner->get_official_products();

// Einzelnes Produkt
$product = $scanner->get_official_product( 'ps-chat' );

if ( $product ) {
    echo $product['name']; // "PS Chat"
    echo $product['repo']; // "Power-Source/ps-chat"
}
```

### Manuellen Scan auslösen

```php
$scanner = PS_Update_Manager_Product_Scanner::get_instance();
$discovered = $scanner->scan_all();

// Gibt Array der entdeckten Produkte zurück
foreach ( $discovered as $slug => $product ) {
    echo $product['name'] . ' - ' . $product['version'];
}
```

### Letzte Scan-Zeit

```php
$scanner = PS_Update_Manager_Product_Scanner::get_instance();
$last_scan = $scanner->get_last_scan_time();

if ( $last_scan ) {
    echo 'Letzter Scan: ' . human_time_diff( $last_scan ) . ' ago';
}
```

## Migration bestehender Plugins

### Von manueller Registrierung

**Alt** (in jedem Plugin):
```php
add_action( 'plugins_loaded', function() {
    if ( function_exists( 'ps_register_product' ) ) {
        ps_register_product( array(
            'slug' => 'mein-plugin',
            'name' => 'Mein Plugin',
            // ... viele Zeilen Code
        ) );
    }
}, 5 );
```

**Neu** (nur im Manifest):
```php
'mein-plugin' => array(
    'type' => 'plugin',
    'name' => 'Mein Plugin',
    'repo' => 'Power-Source/mein-plugin',
    'description' => '...',
),
```

### Vorteile

- ⚡ **Weniger Code** in jedem Plugin
- 🔧 **Zentrale Wartung** aller Metadaten
- 🎨 **Konsistentes Branding**
- 🔒 **Mehr Sicherheit**

## Best Practices

### 1. Repository-Naming

- Verwende Kebab-Case: `mein-super-plugin`
- Präfix für PSource-Plugins: `ps-` (optional)
- Keine Großbuchstaben
- Keine Underscores

### 2. Slug-Konsistenz

Der Slug im Manifest **muss** identisch sein mit:
- Plugin-Verzeichnis-Name
- Theme-Stylesheet-Name
- Text Domain

### 3. GitHub Releases

Stelle sicher, dass dein Repository:
- ✅ Releases mit Tags hat (`v1.0.0`)
- ✅ Release-Zipfile enthält (automatisch von GitHub)
- ✅ Changelog im Release-Body hat

### 4. Versionierung

Folge [Semantic Versioning](https://semver.org/):
- `1.0.0` - Major Release
- `1.1.0` - Minor Update (neue Features)
- `1.0.1` - Patch (Bugfixes)

### 5. Manifest-Pflege

- Commit-Messages bei Manifest-Änderungen: `Add: Produkt XYZ zum Manifest`
- Review-Prozess für neue Einträge
- Regelmäßige Überprüfung auf veraltete Produkte

## Sicherheitshinweise

### Was NICHT im Manifest

- ❌ Keine API-Keys oder Secrets
- ❌ Keine sensiblen Daten
- ❌ Keine Download-URLs (werden automatisch generiert)

### Manifest-Validierung

Der Scanner validiert:
- ✅ Typ muss 'plugin' oder 'theme' sein
- ✅ Repository muss existieren
- ✅ Slug muss mit installiertem Produkt übereinstimmen

### Manipulation verhindern

Da nur Produkte im Manifest erkannt werden:
- ✅ Gefälschte Plugins werden ignoriert
- ✅ Manipulierte Plugin-Header sind wirkungslos
- ✅ Nur authentische PSource-Repos werden verwendet

## Troubleshooting

### Plugin wird nicht erkannt

**Checkliste:**
1. ✅ Ist das Plugin im Manifest eingetragen?
2. ✅ Stimmt der Slug mit dem Verzeichnisnamen überein?
3. ✅ Ist der Typ korrekt gesetzt (`plugin` vs. `theme`)?
4. ✅ "Produkte scannen" Button geklickt?

### Update wird nicht angezeigt

**Checkliste:**
1. ✅ GitHub Release erstellt?
2. ✅ Tag-Format korrekt (`v1.0.0`)?
3. ✅ Version höher als installierte Version?
4. ✅ Cache gelöscht? (6 Stunden Transient)

### Performance-Probleme

**Optimierungen:**
- ✅ Scan-Cache läuft 1 Woche
- ✅ Update-Info-Cache läuft 6 Stunden
- ✅ Status-Cache läuft 1 Minute
- ✅ Keine API-Calls bei gecachten Daten

## Support & Fragen

- 📚 Dokumentation: `/docs/`
- 🐛 Issues: [GitHub Issues](https://github.com/Power-Source/ps-update-manager/issues)
- 💬 Diskussionen: [GitHub Discussions](https://github.com/Power-Source/ps-update-manager/discussions)

---

**Letzte Aktualisierung:** 7. Dezember 2025  
**Version:** 1.0.0
