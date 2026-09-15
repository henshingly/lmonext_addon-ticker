# Changelog: ticker-Addon (LMOnext)

Newsticker: scrollendes Laufband (Marquee) oberhalb der Tabs auf der Liga-
Seite - entweder ein frei eingegebener Text oder die letzten Ergebnisse der
Liga als durchlaufende Liste. Enthält außerdem einen eigenständigen, extern
einbettbaren Ticker (ticker.php) für iframe/include-Einbindung auf beliebigen
Seiten, mit Unterstützung für mehrere Ligen gleichzeitig.

Auf Wunsch als eigenständiges Addon ausgegliedert, nachdem der Nutzer daran
erinnerte, dass der Newsticker im alten LMO4 ebenfalls im addon/-Ordner lag,
nicht im Core - siehe Auszug aus der alten LMO4-Hilfe-Seite unten. Vorher
(kurzzeitig) fest im Core integriert (siehe CHANGELOG.md des Hauptprojekts,
Abschnitte src/Liga/RenderViewsTrait.php zu "renderTickerBlock").

## Version 1.1.1

- KRITISCHER Bugfix (vom Nutzer entdeckt): ticker.php war für den iframe-
  Einbindungsweg von Anfang an nicht erreichbar. Dateien unter addon/{name}/
  sind per addon/.htaccess grundsätzlich vor direkter HTTP-Ausführung
  gesperrt (Sicherheitsmaßnahme des Addon-Managers - siehe addon-run.php im
  Hauptprojekt-Root) - der Kommentar in ticker.php verwies aber auf einen
  direkten iframe-Aufruf dieser Datei per URL, der so nie funktioniert hätte.
  Behoben:
  - addon.json deklariert jetzt "standalone_entrypoints": ["ticker.php"],
    damit addon-run.php diese Datei als erlaubten Aufrufweg akzeptiert.
  - Die korrekte iframe-Einbindung läuft jetzt über
    addon-run.php?addon=ticker&file=ticker.php&... (Dokumentation korrigiert).
  - Frame-Schutz-Header (X-Frame-Options/CSP) werden jetzt entfernt, sonst
    hätte selbst der korrigierte Aufruf jede Einbettung geblockt - gleiches,
    bereits etabliertes Vorgehen wie im mini-tabelle-Addon.
  - Zusätzlicher, beim Beheben entdeckter Bug: die Unterscheidung "per
    include() eingebunden" vs. "per iframe/addon-run.php aufgerufen" beruhte
    vorher fälschlich auf isset($ticker_ligen) - ein include()-Aufruf OHNE
    vorher gesetzte $ticker_*-Variable wäre dadurch fälschlich als iframe-
    Fall behandelt worden. Jetzt korrekt über
    !defined('LMO_ADDON_STANDALONE_CALL') erkannt (dieselbe von addon-run.php
    gesetzte Konstante, die auch das mini-tabelle-Addon bereits nutzt). Mit
    beiden Fällen einzeln verifiziert.
  - Der PHP-include()-Weg selbst war nie betroffen (reiner Dateisystem-
    zugriff, .htaccess wirkt nur auf direkte HTTP-Anfragen).

## Version 1.1.0

- Feature (auf Wunsch, alle drei bisher bewusst zurückgestellten Punkte aus Version 1.0.0 nachgeliefert):
  - tickerart="ergebnisse_favorit" (altes tickerart=3): Ergebnisticker, gefiltert auf die als "Lieblingsmannschaft" der Liga hinterlegte Mannschaft (liga_options "favTeam", über resolveTeamNumberToId() aufgelöst und validiert). Ist keine Lieblingsmannschaft hinterlegt, bleibt der Ticker für diese Liga bewusst leer statt ungefiltert auf alle Ergebnisse zurückzufallen.
  - Spielnotizen im Ergebnisticker (neue Einstellung "Spielnotizen anzeigen?"): hängt eine hinterlegte Spielnotiz in Klammern hinter das Ergebnis an, nur bei den beiden Ergebnisticker-Varianten wirksam.
  - Geschwindigkeit KRITISCH überarbeitet (gemeldet: eine feste Laufzeit von 30 Sekunden pro Durchlauf, unabhängig von der Textlänge, machte lange Ticker-Texte unlesbar schnell): die Laufzeit wird jetzt pro Ticker aus der tatsächlichen sichtbaren Zeichenanzahl und einer konfigurierbaren Lesegeschwindigkeit (Zeichen pro Minute, Standard 900) berechnet - die Lesegeschwindigkeit bleibt dadurch bei JEDER Textlänge gleich, ein langer Text läuft proportional länger statt schneller durch. Mit mehreren Testfällen verifiziert, inkl. Prüfung, dass doppelt so langer Text auch etwa doppelt so lange läuft (konstante Zeichen-pro-Sekunde-Rate).
  - Neue Breiten-Einstellung (Zeichenanzahl, altes "breite"): begrenzt den sichtbaren Ausschnitt auf eine feste Breite (CSS-Einheit "ch", genau die Bedeutung von "Zeichenanzahl" aus der alten LMO4-Doku) statt der vollen Container-Breite - 0/leer = unverändert volle Breite.
- TickerRenderer::wrapMarkup() als neue, öffentliche Methode ausgegliedert (Markup-Aufbau + Laufzeit-/Breiten-Berechnung), damit render() (Hook-Fall) und ticker.php (Embed-Fall, mehrere zusammengefügte Ligen) dieselbe Logik nutzen, ohne sie zu duplizieren.
- ticker.php: neue Parameter breite/geschwindigkeit/notizanzeigen (bzw. $ticker_breite/$ticker_geschwindigkeit/$ticker_notizanzeigen im include-Fall), tickerart unterstützt jetzt auch "ergebnisse_favorit". Breite/Geschwindigkeit gelten für das gesamte zusammengefügte Laufband (nicht pro Liga), da mehrere Ligen zu einem gemeinsamen Text verschmolzen werden.
- admin/view_liga_settings.php + admin/handler_settings.php (Core): neue Einstellungen ergänzt, siehe dortige CHANGELOG-Einträge im Hauptprojekt.

## Version 1.0.0

- Erstveröffentlichung als eigenständiges Addon. Core-Anbindung über den
  Hook "liga.ticker_block" (siehe frontend/data_liga.php im Hauptprojekt) -
  ohne aktives Addon bleibt der Ticker komplett unsichtbar, kein Fallback im
  Core (auf Wunsch, wie beim alten LMO4).
- Die eigentliche Render-Logik (TickerRenderer.php) ist eine reine
  Verschiebung aus src/Liga/RenderViewsTrait.php (Core), keine funktionale
  Änderung an der Text-/Ergebnisticker-Logik selbst.
- Das CSS für die Marquee-Animation (reine CSS-Animation, keine JavaScript-
  Abhängigkeit - bewusster Unterschied zum alten LMO4, das jquery.marquee.min.js
  brauchte) lebt jetzt als Inline-<style>-Block im Hook-Ergebnis, da das
  Addon keinen festen Platz mehr im Core-Template-CSS hat.
- NEU (gab es vorher weder im Core noch im alten Kern-Feature): ticker.php
  als eigenständiger Embed-Entry-Point, analog zum addon/ticker/ticker.php
  des alten LMO4 - unterstützt sowohl PHP-include (bevorzugt) als auch
  iframe-Einbindung, sowie mehrere Ligen gleichzeitig in einem gemeinsamen
  Laufband (Liga-IDs statt .l98-Dateinamen, da LMOnext relational statt
  dateibasiert arbeitet).
- Admin-Formular für die Ticker-Einstellungen (Ein/Aus, Ticker-Art, freier
  Text) bleibt bewusst im Core (admin/view_liga_settings.php,
  admin/handler_settings.php) - nur mit einer isEnabled('ticker')-Bedingung
  umschlossen, analog zum bereits etablierten Muster des player-Addons. Ein
  echtes Admin-Hook-System für UI-Injektion existiert in diesem Projekt noch
  nicht (auch das teamvergleich-Addon nutzt denselben Ansatz für seine
  "show_teamvergleich"-Einstellung).

## Referenz: Auszug aus der alten LMO4-Hilfe-Seite zum Newsticker

Vom Nutzer bereitgestellt, als Hintergrund für dieses Addon:

> In den LMO ist ein flexibler Newsticker integriert, der über Ergebnisse
> und Notizen in einem Platz sparenden Laufband informiert. Im
> Ergebniseditor lässt sich der Ticker für jede Liga separat an- oder
> abschalten. Der Ticker ist dann im Userbereich über der jeweiligen Liga zu
> sehen. Sie haben aber auch die Möglichkeit, ihn an beliebige Stellen ihrer
> Homepage zu setzen und er lässt sich zusätzlich konfigurieren, so dass zum
> Beispiel auch mehrere Ligen in einem Ticker angezeigt werden können.
>
> Steuerparameter: standard_ligen, tickerart (1=Ergebnisticker,
> 2=Tickertext, 3=Ergebnisticker nur Favoritenteam), tickertitel,
> notizanzeigen, breite, geschwindigkeit.
>
> Einbindung per include (bevorzugt) oder per iframe (Fallback für Server
> ohne PHP-Unterstützung oder .html-Dateien).

Übernommen in dieses Addon: tickerart 1 (Ergebnisticker) und 2 (freier
Text), Einbindung per include und iframe, mehrere Ligen gleichzeitig.

Bewusst NICHT übernommen (bisher): tickerart=3 (Ergebnisticker nur
Favoritenteam), tickertitel (Tickerüberschrift), notizanzeigen
(Spielnotizen im Ticker), breite/geschwindigkeit als konfigurierbare
Parameter (die Marquee-Geschwindigkeit ist aktuell fest im CSS verankert,
30s pro Durchlauf) - können bei Bedarf ergänzt werden.
