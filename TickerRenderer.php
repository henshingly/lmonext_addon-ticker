<?php
/**
 * Project: LMOnext
 * Filename: addon/ticker/TickerRenderer.php
 * Fileversion: 1.1.0
 *
 * PHP version 8.2
 *
 * @author    Dietmar Kersting <webmaster@liga-manager-online.org>
 * @author    Torsten Hofmann <entwickler@bastel-code.de>
 * @copyright 2026 Dietmar Kersting, Torsten Hofmann
 * @license   GPL-3.0-only
 *
 * Render-Logik des Liga-Newstickers (auf Wunsch als eigenständiges Addon
 * ausgegliedert - der alte LMO4 hatte den Newsticker ebenfalls im addon/-
 * Ordner, nicht im Core, siehe CHANGELOG.md dieses Addons für den
 * vollständigen Hintergrund).
 *
 * Auf weiteren Wunsch (Auszug aus der alten LMO4-Hilfe, siehe CHANGELOG.md):
 * - tickerart="ergebnisse_favorit" (altes tickerart=3): Ergebnisticker,
 *   gefiltert auf die als "Lieblingsmannschaft" der Liga hinterlegte
 *   Mannschaft (liga_options "favTeam").
 * - "Spielnotizen anzeigen?" (altes notizanzeigen): hängt eine hinterlegte
 *   Spielnotiz hinter das Ergebnis an, nur bei den beiden Ergebnisticker-
 *   Varianten wirksam.
 * - Geschwindigkeit NICHT mehr als feste Laufzeit (die ursprüngliche
 *   Umsetzung hatte fest "30s pro Durchlauf", das machte lange Texte
 *   unlesbar schnell) sondern als Zeichen pro Minute: die Laufzeit wird pro
 *   Ticker aus der tatsächlichen Zeichenanzahl berechnet, damit die
 *   Lesegeschwindigkeit bei JEDER Textlänge gleich bleibt (siehe
 *   calculateDurationSeconds()).
 * - Breite (in Zeichen): begrenzt den sichtbaren Ausschnitt auf eine feste
 *   Zeichenbreite (CSS-Einheit "ch" = Breite einer "0" in der aktuellen
 *   Schrift, genau die Bedeutung von "Zeichenanzahl" aus der alten LMO4-
 *   Doku) statt der vollen Container-Breite.
 *
 * Genutzt sowohl vom Hook "liga.ticker_block" (hooks_frontend.php, Ticker
 * auf der normalen Liga-Seite) als auch vom eigenständigen Embed-Skript
 * (ticker.php, für iframe/include auf externen Seiten).
 */
declare(strict_types = 1);

namespace LMOnext\Addon\Ticker;

final class TickerRenderer
{
    /**
     * Baut den kompletten Ticker-HTML-Block (inkl. eines einmalig
     * ausgegebenen <style>-Blocks für die Marquee-Animation - das Addon hat
     * keinen eigenen Platz im Core-Template-CSS mehr, daher wird das CSS
     * hier als Inline-<style> mitgeliefert, analog zu renderH2hModalAssets()
     * im teamvergleich-Addon) für eine einzelne Liga. Gibt einen leeren
     * String zurück, wenn der Ticker für diese Liga aus ist oder nichts
     * Anzeigbares vorhanden ist.
     *
     * $withStyle: false unterdrückt den <style>-Block (für den Fall, dass
     * ein Aufrufer mehrere Ticker auf einer Seite zusammenbaut - z.B.
     * ticker.php bei mehreren Ligen - und das CSS nur einmal ausgeben will).
     */
    public static function render(int $ligaId, bool $withStyle = true) : string
    {
        $opts = \getLigaOptions($ligaId);
        if (($opts['ticker'] ?? '0') !== '1') {
            return '';
        }
        $content = self::buildContent($ligaId, $opts);
        if ($content === '') {
            return '';
        }
        $breite = (int)($opts['tickerbreite'] ?? 0);
        $geschwindigkeit = (int)($opts['tickergeschwindigkeit'] ?? 900);
        return self::wrapMarkup($content, $breite, $geschwindigkeit, $withStyle);
    }

    /**
     * Umschließt einen bereits fertig aufbereiteten Textinhalt mit dem
     * Marquee-Markup (Viewport/Track/zwei Kopien für nahtlosen Loop) und
     * berechnet die individuelle Laufzeit/Breite. Öffentlich, damit
     * ticker.php denselben Aufbau für mehrere zusammengefügte Ligen nutzen
     * kann, ohne die Berechnung zu duplizieren.
     */
    public static function wrapMarkup(string $content, int $breiteZeichen, int $geschwindigkeitZpm, bool $withStyle = true) : string
    {
        $durationSeconds = self::calculateDurationSeconds($content, $geschwindigkeitZpm);
        $viewportStyle = $breiteZeichen > 0 ? ' style="width:' . $breiteZeichen . 'ch"' : '';
        $trackStyle = ' style="animation-duration:' . $durationSeconds . 's"';

        return ($withStyle ? self::styleBlock() : '') . '<div class="liga-ticker">'
              . '<span class="liga-ticker-icon">📢</span>'
              . '<div class="liga-ticker-viewport"' . $viewportStyle . '><div class="liga-ticker-track"' . $trackStyle . '>'
              . '<span class="liga-ticker-text">' . $content . '</span>'
              . '<span class="liga-ticker-text" aria-hidden="true">' . $content . '</span>'
              . '</div></div></div>';
    }

    /**
     * Laufzeit in Sekunden für EINEN Durchlauf (= Verschiebung um eine
     * Kopiebreite), berechnet aus der sichtbaren Zeichenanzahl des Inhalts
     * und der gewünschten Lesegeschwindigkeit (Zeichen pro Minute) - siehe
     * ausführlicher Klassen-Kommentar oben. HTML-Tags/Entities werden vor
     * dem Zählen entfernt bzw. aufgelöst, damit z.B. "&nbsp;•&nbsp;"
     * korrekt nur als die paar sichtbaren Zeichen zählt, die es tatsächlich
     * darstellt, nicht als die längere Entity-Schreibweise.
     * Untergrenze von 5 Sekunden, damit ein sehr kurzer Text nicht absurd
     * schnell durchrauscht.
     */
    private static function calculateDurationSeconds(string $htmlContent, int $geschwindigkeitZpm) : float
    {
        $geschwindigkeitZpm = max(10, $geschwindigkeitZpm);
        $plainText = html_entity_decode(strip_tags($htmlContent), ENT_QUOTES, 'UTF-8');
        // Defensiv wie admin/data_loader.php (mb_strtolower dort) - mbstring
        // ist auf den meisten Hosting-Umgebungen vorhanden, aber nicht
        // garantiert; strlen() als Fallback zählt bei Umlauten/Mehrbyte-
        // Zeichen zwar etwas zu hoch, das wirkt sich auf die Laufzeit-
        // Berechnung hier nur geringfügig aus (kein funktionaler Fehler).
        $charCount = function_exists('mb_strlen') ? mb_strlen($plainText) : strlen($plainText);
        $duration = ($charCount / $geschwindigkeitZpm) * 60.0;
        return round(max(5.0, $duration), 1);
    }

    /**
     * Baut nur den durchlaufenden Textinhalt (ohne umgebendes Markup) - für
     * ticker.php, das mehrere Ligen zu EINEM gemeinsamen Laufband
     * zusammenfügt (siehe dortiger Trenner zwischen den Ligen).
     */
    public static function buildContent(int $ligaId, ?array $opts = null) : string
    {
        $opts ??= \getLigaOptions($ligaId);
        $tickerart = $opts['tickerart'] ?? 'text';
        if (!in_array($tickerart, ['text', 'ergebnisse', 'ergebnisse_favorit'], true)) {
            $tickerart = 'text';
        }

        if ($tickerart === 'ergebnisse' || $tickerart === 'ergebnisse_favorit') {
            $favoritOnly = $tickerart === 'ergebnisse_favorit';
            $showNotizen = ($opts['tickernotizen'] ?? '0') === '1';
            return self::buildErgebnisText($ligaId, $opts, $favoritOnly, $showNotizen);
        }
        // Zeilenumbrüche im freien Text werden zu Trennzeichen statt <br> -
        // ein Laufband ist eine einzige, fortlaufende Zeile, in der ein
        // "harter" Umbruch keinen Sinn ergibt.
        $rawText = trim((string)($opts['tickertext'] ?? ''));
        $lines = array_filter(array_map('trim', preg_split('/\R/', $rawText)), static fn(string $l) : bool => $l !== '');
        return implode(' &nbsp;•&nbsp; ', array_map('\h', $lines));
    }

    /**
     * Baut den durchlaufenden Ergebnistext für tickerart="ergebnisse"/
     * "ergebnisse_favorit" - die letzten 15 gespielten Partien dieser Liga
     * (bzw. der Lieblingsmannschaft, siehe $favoritOnly), neueste zuerst,
     * durch " • " getrennt. Nutzt dieselbe Grüne-Tisch-Wertungslogik wie
     * die restliche Anzeige (gtCreditedScore()), damit ein annulliertes/
     * gewertetes Spiel im Ticker nicht als "kein Ergebnis" fehlt.
     *
     * $favoritOnly: filtert auf die als "Lieblingsmannschaft" hinterlegte
     * Mannschaft (liga_options "favTeam", siehe resolveTeamNumberToId() im
     * Core für den Hintergrund zur dortigen Migration auf stabile Team-IDs)
     * - ist keine Lieblingsmannschaft hinterlegt, bleibt der Ticker für
     * diese Liga bewusst leer statt ungefiltert auf ALLE Ergebnisse
     * zurückzufallen (das würde die "nur Favorit"-Erwartung verletzen).
     *
     * $showNotizen: hängt eine vorhandene Spielnotiz in Klammern an.
     */
    private static function buildErgebnisText(int $ligaId, array $opts, bool $favoritOnly, bool $showNotizen) : string
    {
        $favoritTeamId = null;
        if ($favoritOnly) {
            $favoritTeamId = \resolveTeamNumberToId($ligaId, (int)($opts['favTeam'] ?? 0));
            if ($favoritTeamId === null) {
                return '';
            }
        }

        $allSpieltage = \getAllSpieltage($ligaId);
        $partien = \getAllLigaPartien($allSpieltage);
        $gtToreGespielt     = (int)($opts['GtToreGespielt'] ?? 2);
        $gtToreNichtantritt = (int)($opts['GtToreNichtantritt'] ?? 2);

        $gespielt = [];
        foreach ($partien as $p) {
            $heimId = $p['heim_id'] !== null ? (int)$p['heim_id'] : null;
            $gastId = $p['gast_id'] !== null ? (int)$p['gast_id'] : null;
            if ($favoritTeamId !== null && $heimId !== $favoritTeamId && $gastId !== $favoritTeamId) {
                continue;
            }
            $gtEntscheidung = (int)($p['gt_entscheidung'] ?? 0);
            $hTore = $p['h_tore'] !== null ? (int)$p['h_tore'] : null;
            $gTore = $p['g_tore'] !== null ? (int)$p['g_tore'] : null;
            if ($gtEntscheidung === 1 || $gtEntscheidung === 2) {
                $credited = \LMOnext\Liga\LigaService::gtCreditedScore($gtEntscheidung, $hTore, $gTore, $gtToreGespielt, $gtToreNichtantritt);
                $hTore = $credited['h_tore'];
                $gTore = $credited['g_tore'];
            }
            if ($hTore === null || $gTore === null) {
                continue; // noch nicht gespielt -> nicht im Ergebnisticker
            }
            $gespielt[] = [
                'zeit'  => $p['zeit'] ?? '',
                'heim'  => (string)($p['heim_name'] ?? $p['heim_label'] ?? ''),
                'gast'  => (string)($p['gast_name'] ?? $p['gast_label'] ?? ''),
                'hTore' => $hTore,
                'gTore' => $gTore,
                'notiz' => (string)($p['notiz'] ?? ''),
            ];
        }
        if ($gespielt === []) {
            return '';
        }
        usort($gespielt, static fn(array $a, array $b) : int => strcmp((string)$b['zeit'], (string)$a['zeit']));
        $gespielt = array_slice($gespielt, 0, 15);

        $parts = array_map(
            static function (array $g) use ($showNotizen) : string {
                $line = \h($g['heim']) . ' ' . (string)$g['hTore'] . ':' . (string)$g['gTore'] . ' ' . \h($g['gast']);
                if ($showNotizen && trim($g['notiz']) !== '') {
                    $line .= ' (' . \h(trim($g['notiz'])) . ')';
                }
                return $line;
            },
            $gespielt
        );
        return implode(' &nbsp;•&nbsp; ', $parts);
    }

    /**
     * Der Marquee-<style>-Block, einmalig pro Seite (siehe render()s
     * $withStyle-Parameter). Reine CSS-Animation, keine JavaScript-
     * Abhängigkeit (bewusst - das alte LMO4 nutzte jquery.marquee.min.js,
     * das ist hier nicht nötig). Text im Track wird vom Aufrufer zweimal
     * hintereinander eingesetzt, die Animation verschiebt um exakt -50% -
     * dadurch ist die zweite Kopie beim Erreichen von -50% exakt an der
     * Stelle, an der die erste Kopie beim Start war, der Loop wirkt nahtlos.
     * animation-duration wird NICHT mehr hier fest gesetzt, sondern per
     * Inline-Style pro Ticker-Instanz (siehe wrapMarkup()), da sie von der
     * individuellen Textlänge abhängt.
     */
    private static function styleBlock() : string
    {
        static $emitted = false;
        if ($emitted) {
            return '';
        }
        $emitted = true;
        return '<style>
.liga-ticker{font-size:.88rem;margin:0 0 16px;padding:10px 14px;
  background:rgba(127,127,127,.08);border-radius:6px;border-left:4px solid #3b82f6;
  display:flex;align-items:center;gap:10px;overflow:hidden}
.liga-ticker-icon{flex:0 0 auto}
.liga-ticker-viewport{flex:1;overflow:hidden;min-width:0}
.liga-ticker-track{display:flex;white-space:nowrap;width:max-content;
  animation-name:liga-ticker-scroll;animation-timing-function:linear;animation-iteration-count:infinite}
.liga-ticker-track:hover{animation-play-state:paused}
.liga-ticker-text{white-space:nowrap;padding-right:60px}
@keyframes liga-ticker-scroll{from{transform:translateX(0)}to{transform:translateX(-50%)}}
@media (prefers-reduced-motion: reduce){.liga-ticker-track{animation:none}
  .liga-ticker-viewport{overflow-x:auto}
  .liga-ticker-text[aria-hidden="true"]{display:none}}
</style>';
    }
}
