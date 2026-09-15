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
