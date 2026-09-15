<?php
/**
 * Project: LMOnext
 * Filename: addon/ticker/TickerRenderer.php
 * Fileversion: 1.0.0
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
 * vollständigen Hintergrund). Ursprünglich in src/Liga/RenderViewsTrait.php
 * (Core) implementiert - reine Verschiebung, keine Funktionsänderung an der
 * eigentlichen Logik (Text-/Ergebnisticker-Varianten, Marquee-Aufbau).
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
        $html = ($withStyle ? self::styleBlock() : '') . '<div class="liga-ticker">'
              . '<span class="liga-ticker-icon">📢</span>'
              . '<div class="liga-ticker-viewport"><div class="liga-ticker-track">'
              . '<span class="liga-ticker-text">' . $content . '</span>'
              . '<span class="liga-ticker-text" aria-hidden="true">' . $content . '</span>'
              . '</div></div></div>';
        return $html;
    }

    /**
     * Baut nur den durchlaufenden Textinhalt (ohne umgebendes Markup) - für
     * ticker.php, das mehrere Ligen zu EINEM gemeinsamen Laufband
     * zusammenfügt (siehe dortiger Trenner zwischen den Ligen).
     */
    public static function buildContent(int $ligaId, ?array $opts = null) : string
    {
        $opts ??= \getLigaOptions($ligaId);
        $tickerart = ($opts['tickerart'] ?? 'text') === 'ergebnisse' ? 'ergebnisse' : 'text';

        if ($tickerart === 'ergebnisse') {
            return self::buildErgebnisText($ligaId);
        }
        // Zeilenumbrüche im freien Text werden zu Trennzeichen statt <br> -
        // ein Laufband ist eine einzige, fortlaufende Zeile, in der ein
        // "harter" Umbruch keinen Sinn ergibt.
        $rawText = trim((string)($opts['tickertext'] ?? ''));
        $lines = array_filter(array_map('trim', preg_split('/\R/', $rawText)), static fn(string $l) : bool => $l !== '');
        return implode(' &nbsp;•&nbsp; ', array_map('\h', $lines));
    }

    /**
     * Baut den durchlaufenden Ergebnistext für tickerart="ergebnisse" - die
     * letzten 15 gespielten Partien dieser Liga, neueste zuerst, durch
     * " • " getrennt. Nutzt dieselbe Grüne-Tisch-Wertungslogik wie die
     * restliche Anzeige (gtCreditedScore()), damit ein annulliertes/
     * gewertetes Spiel im Ticker nicht als "kein Ergebnis" fehlt.
     */
    private static function buildErgebnisText(int $ligaId) : string
    {
        $allSpieltage = \getAllSpieltage($ligaId);
        $partien = \getAllLigaPartien($allSpieltage);
        $opts = \getLigaOptions($ligaId);
        $gtToreGespielt     = (int)($opts['GtToreGespielt'] ?? 2);
        $gtToreNichtantritt = (int)($opts['GtToreNichtantritt'] ?? 2);

        $gespielt = [];
        foreach ($partien as $p) {
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
            ];
        }
        if ($gespielt === []) {
            return '';
        }
        usort($gespielt, static fn(array $a, array $b) : int => strcmp((string)$b['zeit'], (string)$a['zeit']));
        $gespielt = array_slice($gespielt, 0, 15);

        $parts = array_map(
            static fn(array $g) : string => \h($g['heim']) . ' ' . (string)$g['hTore'] . ':' . (string)$g['gTore'] . ' ' . h($g['gast']),
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
  animation:liga-ticker-scroll 30s linear infinite}
.liga-ticker-track:hover{animation-play-state:paused}
.liga-ticker-text{white-space:nowrap;padding-right:60px}
@keyframes liga-ticker-scroll{from{transform:translateX(0)}to{transform:translateX(-50%)}}
@media (prefers-reduced-motion: reduce){.liga-ticker-track{animation:none}
  .liga-ticker-viewport{overflow-x:auto}
  .liga-ticker-text[aria-hidden="true"]{display:none}}
</style>';
    }
}
