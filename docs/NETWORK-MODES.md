# Network-Modi für PSource Plugins

## Übersicht

PSource Plugins können verschiedene Network-Modi unterstützen, die steuern, wie sie auf ClassicPress Single-Site und Multisite-Installationen aktiviert werden können.

## Unterstützte Modi

### 1. ClassicPress Network Only (Immer nur Netzwerkweit)

**Verwendung:** ClassicPress Standard für Plugins die **NUR** auf Multisite funktionieren und dort nur netzwerkweit aktivierbar sein sollen.

**Plugin-Header:**
```php
/*
Plugin Name: Mein Plugin
Network: true
*/
```

**Verhalten:**
- ❌ Auf Single-Sites: Nicht installierbar/aktivierbar
- ✅ Auf Multisite: Nur netzwerkweit aktivierbar
- 🔒 Striktester Modus - ClassicPress Standard

**Beispiele:**
- Domain Mapping Plugins
- Netzwerk-spezifische Tools die keinen Sinn auf Single-Sites machen

**⚠️ Nicht verwenden für:** Plugins die auch auf Single-Sites funktionieren sollen!

### 2. Multisite Network Required (Multisite-Aware)

**Verwendung:** Plugins die auf Single-Sites normal laufen, aber auf Multisite nur netzwerkweit aktiviert werden sollen.

**Plugin-Header:**
```php
/*
Plugin Name: Mein Plugin
PS Network: required
*/
```

**Verhalten:**
- ✅ Auf Single-Sites: Normal aktivierbar
- ✅ Auf Multisite: Nur netzwerkweit aktivierbar (nicht site-by-site)
- 🎯 Intelligent - passt sich der Installation an

**Beispiele:**
- `ps-update-manager` - Zentrale Verwaltung für Multisite, normal auf Single-Sites
- `default-theme` - Netzwerk-Theme-Manager für Multisite
- Zentrale Management-Tools die Multisite-aware sein müssen

**✅ Empfohlen für:** Die meisten PSource Plugins mit Multisite-Support

### 3. Network Flexible (Beide Modi)

**Verwendung:** Plugins die sowohl netzwerkweit als auch site-by-site aktiviert werden können.

**Plugin-Header:**
```php
/*
Plugin Name: Mein Plugin
PS Network: flexible
*/
```

**Verhalten:**
- ✅ Kann im Netzwerk-Admin netzwerkweit aktiviert werden
- ✅ Kann im Site-Admin für einzelne Sites aktiviert werden
- 📝 Zeigt Hinweis "Kann auch site-by-site im Site-Admin aktiviert werden"

**Beispiele:**
- `ps-chat` - Kann für alle Sites oder nur bestimmte aktiviert werden
- Content-Plugins die optional netzwerkweit sein sollen

### 4. Site-Only (Standard)

**Verwendung:** Normale Plugins die nur auf Unterseiten-Ebene aktivierbar sind (nicht netzwerkweit).

**Plugin-Header:**
```php
/*
Plugin Name: Mein Plugin
// Kein Network-Header
*/
```

**Verhalten:**
- ✅ Auf Single-Sites: Normal aktivierbar
- ✅ Auf Multisite: Nur auf einzelnen Sites aktivierbar (nicht netzwerkweit)
- ❌ Kann NICHT netzwerkweit aktiviert werden
- 📝 Für Plugins die site-spezifisch sind

**Beispiele:**
- Content-spezifische Plugins ohne zentrale Verwaltung
- Site-individuelle Features
- Plugins ohne Multisite-Koordination

## Empfehlungen

### Wann `Network: true` verwenden?

⚠️ **Vorsicht:** Dieser Modus macht das Plugin auf Single-Sites unnutzbar!

Nutze `Network: true` nur wenn:
- Das Plugin **ausschließlich** auf Multisite funktioniert
- Das Plugin auf Single-Sites keinen Sinn macht
- Das Plugin Multisite-Core-Funktionen nutzt die auf Single-Sites nicht existieren

**Beispiele:**
```php
// Domain Mapping - nur Multisite-Konzept
Network: true

// Netzwerk-Site-Kloner - nur auf Multisite sinnvoll
Network: true
```

### Wann `PS Network: required` verwenden? ⭐ EMPFOHLEN

✅ **Am häufigsten verwendet für PSource Plugins**

Nutze `PS Network: required` wenn:
- Das Plugin auf Single-Sites UND Multisite funktioniert
- Das Plugin auf Multisite zentral verwaltet werden soll
- Das Plugin auf Multisite nur netzwerkweit Sinn macht
- Du willst dass es Single-Site-kompatibel bleibt

**Beispiele:**
```php
// Update Manager - Single-Site OK, Multisite zentral
PS Network: required

// Standard Theme - Single-Site OK, Multisite netzwerkweit
PS Network: required

// Zentrale Settings - Single-Site OK, Multisite nur netzwerkweit
PS Network: required
```

### Wann `PS Network: flexible` verwenden?

Nutze `PS Network: flexible` wenn:
- Das Plugin sowohl netzwerkweit als auch pro Site nützlich ist
- Admins selbst entscheiden sollen wo es aktiviert wird
- Das Plugin verschiedene Anwendungsfälle hat

**Beispiele:**
```php
// Chat Plugin - optional für einzelne oder alle Sites
PS Network: flexible

// Analytics Plugin - je nach Bedarf
PS Network: flexible

// Social Media Integration - Site-spezifisch oder netzwerkweit
PS Network: flexible
```

### Wann keinen Header verwenden?

Nutze keinen Network-Header wenn:
- Das Plugin site-spezifisch ist (z.B. Shop für eine Site)
- Das Plugin keine netzwerkweite Koordination benötigt
- Jede Site ihre eigenen Einstellungen haben soll
- Das Plugin NICHT netzwerkweit aktiviert werden soll

## UI-Verhalten

### Im Netzwerk-Admin (Multisite)

**ClassicPress Network (`Network: true`):**
```
[Netzwerkweit aktivieren]
⚠️ Dieses Plugin kann nur netzwerkweit aktiviert werden.
```

**Multisite Required (`PS Network: required`):**
```
[Netzwerkweit aktivieren]
ℹ️ Auf Multisite nur netzwerkweit aktivierbar. Auf Single-Sites normal nutzbar.
```

**Network Flexible (`PS Network: flexible`):**
```
[Netzwerkweit aktivieren]
ℹ️ Kann auch site-by-site im Site-Admin aktiviert werden.
```

**Site-Only (kein Header):**
```
[Aktivieren]
ℹ️ Nur auf Unterseiten-Ebene aktivierbar (nicht netzwerkweit).
```

### Im Site-Admin (Multisite)

**ClassicPress Network oder Multisite Required:**
```
[🔒 Nur Netzwerk-Admin] (deaktiviert)
⚠️ Dieses Plugin kann nur im Netzwerk-Admin aktiviert werden.
```

**Network Flexible oder Site-Only:**
```
[Aktivieren]
ℹ️ Aktiviert das Plugin nur für diese Site.
```

**Site-Only im Netzwerk-Admin:**
```
[Button nicht verfügbar]
ℹ️ Nur auf Unterseiten-Ebene aktivierbar.
```

### Auf Single-Sites

**ClassicPress Network (`Network: true`):**
```
❌ Plugin kann nicht aktiviert werden
```

**Alle anderen Modi:**
```
[Aktivieren]
✅ Normal aktivierbar
```

## Migration bestehender Plugins

### Von `Network: true` zu `PS Network: required` ⭐ EMPFOHLEN

Wenn dein Plugin auch auf Single-Sites funktionieren soll:

```php
// Vorher - blockiert Single-Sites
/*
Plugin Name: Mein Plugin
Network: true
*/

// Nachher - Single-Sites OK, Multisite nur netzwerkweit
/*
Plugin Name: Mein Plugin
PS Network: required
*/
```

### Von Standard zu Multisite Required

```php
// Vorher
/*
Plugin Name: Mein Plugin
*/

// Nachher - Multisite-aware
/*
Plugin Name: Mein Plugin
PS Network: required
*/
```

### Von Multisite Required zu Flexible

```php
// Vorher
/*
Plugin Name: Mein Plugin
PS Network: required
*/

// Nachher - beide Modi erlaubt
/*
Plugin Name: Mein Plugin
PS Network: flexible
*/
```

## Scanner-Integration

Der PS Update Manager Scanner erkennt automatisch:

1. **PSource Spezifisch:** `PS Network: required|flexible` Header (hat Vorrang)
2. **ClassicPress Standard:** `Network: true` Header
3. **Fallback:** Kein Header = Site-Only

Die erkannten Werte werden in der Registry gespeichert:
```php
array(
    'slug' => 'ps-update-manager',
    'network_only' => true,              // true auf Multisite wenn multisite-required
    'network_mode' => 'multisite-required', // Modi: 'wordpress-network', 'multisite-required', 'flexible', 'none'
    // ...
)
```

## Best Practices

### ✅ DO

- Wähle den restriktivsten Modus der noch funktioniert
- Dokumentiere im Plugin warum ein bestimmter Modus gewählt wurde
- Teste beide Aktivierungsmethoden bei `flexible`
- Prüfe `is_plugin_active_for_network()` im Code wenn nötig

### ❌ DON'T

- Nutze nicht `Network: true` nur weil es ein Multisite gibt
- Ändere nicht den Modus ohne Rücksprache mit Admins
- Verlasse dich nicht darauf dass `flexible` immer netzwerkweit aktiviert ist
- Vergiss nicht zu testen was passiert bei verschiedenen Aktivierungen

## Technische Details

### Header-Priorität

1. `PS Network` Header (PSource-spezifisch)
2. `Network` Header (ClassicPress Standard)
3. Kein Header = Site-Only

### get_file_data() Implementation

```php
$file_data = get_file_data( $plugin_file, array(
    'Network' => 'Network',
    'PSNetwork' => 'PS Network',
) );
```

### Erkannte Werte

| Header-Wert | Single-Site | Multisite Netzwerk | Multisite Site | network_mode |
|-------------|-------------|--------------------|----------------|--------------|
| `Network: true` | ❌ Blockiert | Nur netzwerkweit | ❌ Blockiert | `wordpress-network` |
| `PS Network: required` | ✅ Normal | Nur netzwerkweit | ❌ Blockiert | `multisite-required` |
| `PS Network: flexible` | ✅ Normal | Beide Modi | Beide Modi | `flexible` |
| Kein Header | ✅ Normal | ❌ Nicht verfügbar | Nur Site-Ebene | `none` |

## Siehe auch

- [ClassicPress Multisite Network Plugin](https://developer.wordpress.org/plugins/wordpress-org/plugin-developer-faq/#what-does-network-true-mean)
- [Plugin Integration Guide](PLUGIN-INTEGRATION.md)
- [Security & Performance Guide](SECURITY-PERFORMANCE.md)
