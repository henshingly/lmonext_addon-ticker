<?php
/**
 * Project: LMOnext
 * Filename: addon/ticker/ticker.php
 * Fileversion: 1.0.0
 *
 * PHP version 8.2
 *
 * @author    Dietmar Kersting <webmaster@liga-manager-online.org>
 * @author    Torsten Hofmann <entwickler@bastel-code.de>
 * @copyright 2026 Dietmar Kersting, Torsten Hofmann
 * @license   GPL-3.0-only
 *
 * Eigenständiger, extern einbettbarer Newsticker (auf Wunsch, analog zum
 * addon/ticker/ticker.php des alten LMO4 - siehe dessen Doku-Auszug im
 * CHANGELOG.md dieses Addons). Zwei Einbindungsarten, wie beim alten LMO4:
 *
 * 1. Per include (bevorzugt, nur wenn PHP auf der Zielseite läuft):
 *      <?php
 *      $ticker_ligen = "1,5,12";   // Liga-IDs statt Dateinamen (LMOnext
 *                                  // arbeitet relational, nicht mit .l98-
 *                                  // Dateien wie das alte LMO4)
 *      $ticker_tickerart = "ergebnisse";  // optional, überschreibt die
 *                                          // Pro-Liga-Einstellung
 *      include("PfadZumLMOnext/addon/ticker/ticker.php");
 *
 * 2. Per iframe (nur wenn 1. nicht funktioniert, z.B. keine PHP-Unterstützung
 *    auf der Zielseite):
 *      <iframe src="URLZumLMOnext/addon/ticker/ticker.php?ligen=1,5,12&tickerart=ergebnisse"
 *              frameborder="0" width="100%" height="40" scrolling="no"></iframe>
 *
 * Mehrere Ligen werden zu EINEM gemeinsamen Laufband zusammengefügt,
 * getrennt durch " +++ " (derselbe Trenner wie im alten LMO4). Jede Liga
 * wird einzeln über TickerRenderer::buildContent() aufbereitet - eine Liga
 * ohne anzeigbaren Inhalt (Ticker aus, oder leer) wird stillschweigend
 * übersprungen statt eine Lücke im Laufband zu hinterlassen.
 *
 * WICHTIG (siehe alte LMO4-Doku): niemals per include() über eine URL
 * einbinden (include("http://.../ticker.php")) - nur über einen Dateipfad,
 * sonst funktioniert der Zugriff auf die LMOnext-Datenbank nicht.
 */
declare(strict_types = 1);

// Per include gesetzte Variablen ($ticker_ligen usw.) haben Vorrang vor
// GET-Parametern (iframe-Fall) - siehe Docblock oben. Wird VOR dem Bootstrap
// geprüft, damit die Unterscheidung "include vs. direkter Request" nicht
// versehentlich von etwas überschrieben wird, das der Bootstrap selbst setzt.
$isIncludeMode = isset($ticker_ligen) || isset($ticker_tickerart) || isset($ticker_tickertext);

require_once __DIR__ . '/../../frontend/bootstrap.php';
require_once __DIR__ . '/TickerRenderer.php';

use LMOnext\Addon\Ticker\TickerRenderer;

if (function_exists('addonManager')) {
    \addonManager()->loadLanguages('ticker');
}

$ligenParam      = isset($ticker_ligen) ? (string)$ticker_ligen : (string)($_GET['ligen'] ?? '');
$tickerartParam  = isset($ticker_tickerart) ? (string)$ticker_tickerart : (string)($_GET['tickerart'] ?? '');
$freierTextParam = isset($ticker_tickertext) ? (string)$ticker_tickertext : (string)($_GET['tickertext'] ?? '');

$ligaIds = array_values(array_filter(array_map(
    static fn(string $s) : int => (int)trim($s),
    explode(',', $ligenParam)
), static fn(int $id) : bool => $id > 0));

$parts = [];
if ($freierTextParam !== '') {
    // Direkt übergebener Text ohne Liga-Bezug (z.B. eine reine Ankündigung
    // auf einer externen Seite, ohne dass dafür eine Liga in LMOnext
    // existieren muss) - Zeilenumbrüche wie bei TickerRenderer::
    // buildContent() zu Trennzeichen statt <br>.
    $lines = array_filter(array_map('trim', preg_split('/\R/', $freierTextParam)), static fn(string $l) : bool => $l !== '');
    $parts[] = implode(' &nbsp;•&nbsp; ', array_map('h', $lines));
}
foreach ($ligaIds as $ligaId) {
    $opts = getLigaOptions($ligaId);
    if (empty($opts)) {
        continue; // Liga existiert nicht (mehr) - stillschweigend überspringen
    }
    // tickerart-Parameter überschreibt die Pro-Liga-Einstellung, falls
    // gültig übergeben - ansonsten gilt, was in der Liga selbst konfiguriert
    // ist (inkl. "Ticker aus" -> diese Liga liefert dann keinen Beitrag).
    if (in_array($tickerartParam, ['text', 'ergebnisse'], true)) {
        $opts['tickerart'] = $tickerartParam;
        $opts['ticker'] = '1'; // explizit angeforderte Liga zählt als aktiviert
    }
    if (($opts['ticker'] ?? '0') !== '1') {
        continue;
    }
    $content = TickerRenderer::buildContent($ligaId, $opts);
    if ($content !== '') {
        $parts[] = $content;
    }
}

$trenner = ' &nbsp;+++&nbsp; ';
$gesamtText = implode($trenner, $parts);

if ($gesamtText === '') {
    // Nichts anzuzeigen (keine gültige Liga, alle Ticker aus, oder leer) -
    // bewusst KEIN Markup ausgeben, damit ein umgebender iframe nicht mit
    // einer sichtbaren leeren Box auffällt.
    exit;
}

$html = '<div class="liga-ticker">'
      . '<span class="liga-ticker-icon">📢</span>'
      . '<div class="liga-ticker-viewport"><div class="liga-ticker-track">'
      . '<span class="liga-ticker-text">' . $gesamtText . '</span>'
      . '<span class="liga-ticker-text" aria-hidden="true">' . $gesamtText . '</span>'
      . '</div></div></div>';

$style = '<style>
body{margin:0;padding:0;font-family:sans-serif}
.liga-ticker{font-size:.9rem;margin:0;padding:8px 12px;
  background:#f4f5f7;border-radius:0;display:flex;align-items:center;gap:10px;overflow:hidden}
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

if ($isIncludeMode) {
    // Per include eingebunden: nur den Schnipsel ausgeben, das CSS gehört
    // dann idealerweise in die Zielseite selbst (hier trotzdem mitgeliefert,
    // damit es "out of the box" funktioniert - ein <style>-Block mitten im
    // <body> ist gültiges, wenn auch unübliches HTML und funktioniert in
    // allen gängigen Browsern).
    echo $style . $html;
} else {
    // Direkter HTTP-Request (typischerweise iframe) - vollständiges,
    // eigenständiges Mini-Dokument, damit die Darstellung unabhängig vom
    // einbettenden Elterndokument korrekt ist.
    header('Content-Type: text/html; charset=UTF-8');
    echo '<!DOCTYPE html><html><head><meta charset="UTF-8">' . $style . '</head><body>' . $html . '</body></html>';
}
