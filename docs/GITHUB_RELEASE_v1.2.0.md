## 🎨 Große UI-Überarbeitung: PSOURCE Katalog

### Tab-basierte Navigation
- ✨ **Moderne Tab-UI** – Wechsel zwischen Plugins und Themes ohne Reload
- ⚡ **Vollständig AJAX-basiert** – Filter, Suche und Pagination ohne Seiten-Reload
- 🎯 **Cleaner Code** – 330 Zeilen chaotischer Code entfernt, saubere neue Architektur

### Featured Pagebuilder System
- 🌟 **PS Padma & Child hervorgehoben** – Visuell hervorgehobene Featured-Karten
- 🏆 **Ribbon-Banner** – "Empfohlen" Banner mit Stern-Icon
- 🎨 **Visual Enhancements:**
  - Größere Icons (56px statt 48px) mit Gradient-Hintergrund
  - Blauer Rand (2px) und Gradient-Hintergrund für Featured
  - Stärkerer Schatten und Hover-Animationen (translateY)
  - Blaue Überschriften für Featured-Produkte

### Badge-System
- 🔷 **"PAGEBUILDER"** Badge – Blauer Gradient für Framework
- 🟢 **"CHILD THEME"** Badge – Grüner Gradient für Child Themes
- 🟣 **"TEMPLATE"** Badge – Violetter Gradient für Templates (vorbereitet)
- 💎 Alle Badges mit Icons und Uppercase-Styling

### Kategorien-System
- 📂 **Getrennte Kategorien** – Plugins und Themes haben eigene Kategorielisten
- 🔄 **Dynamischer Filter** – Kategorie-Dropdown lädt automatisch per AJAX beim Tab-Wechsel
- ⚡ **Neue Pagebuilder-Kategorie** – Eigene Kategorie für Framework-Produkte
- 📋 Category-Map erweitert: Plugins (8 Kategorien), Themes (7 Kategorien)

### Technische Verbesserungen
- 🚀 **AJAX-Handler** `ajax_load_products()` – Lädt Produkte gefiltert und paginiert
- 🎯 **AJAX-Handler** `ajax_get_categories()` – Lädt Kategorien dynamisch pro Tab
- 🔧 **Neue JS-Datei** `psources-catalog.js` – Komplettes Client-Side Management
- 📦 **Automatische Sortierung** – Featured-Produkte immer zuerst (nur Themes)
- 🎨 **Responsives Grid** – CSS Grid mit minmax(380px, 1fr)

### UX-Verbesserungen
- 🔍 **Verbesserte Suche** – Durchsucht Name, Slug und Beschreibung
- 🏷️ **Status-Filter** – Alle, Installiert, Aktiv, Verfügbar, Updates
- 🔄 **Reset-Button** – Filter schnell zurücksetzen
- 📄 **Pagination** – 12 Produkte pro Seite mit Seitennummern
- ⚡ **Loading-States** – Spinner während AJAX-Requests
- 📭 **Empty States** – "Keine Produkte gefunden" Nachricht

### Manifest-Updates
- 📝 **PS Padma** – Als Featured Pagebuilder mit verbesserter Beschreibung
- 📝 **PS Padma Child** – Als Featured Child Theme markiert

---

**Vollständiger Changelog:** [CHANGELOG.md](https://github.com/Power-Source/ps-update-manager/blob/main/CHANGELOG.md)
