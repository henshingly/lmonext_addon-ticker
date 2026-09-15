<?php
/**
 * Project: LMOnext
 * Filename: addon/ticker/hooks_frontend.php
 * Fileversion: 1.0.0
 *
 * PHP version 8.2
 *
 * @author    Dietmar Kersting <webmaster@liga-manager-online.org>
 * @author    Torsten Hofmann <entwickler@bastel-code.de>
 * @copyright 2026 Dietmar Kersting, Torsten Hofmann
 * @license   GPL-3.0-only
 *
 * Bindet den Newsticker über den generischen Hook-Mechanismus des
 * AddonManager an den einen Core-Anknüpfpunkt an (siehe frontend/
 * data_liga.php, renderTickerBlock() für den dortigen, kommentierten
 * Wrapper). Wird von AddonManager::bootFrontend() automatisch geladen,
 * wenn das Addon aktiviert ist (siehe addon.json, Feld "frontend_handlers"),
 * bei JEDEM Frontend-Request - der Hook selbst wird aber nur gefeuert, wenn
 * liga.php ihn tatsächlich aufruft (also nur auf der Liga-Detailseite).
 */
declare(strict_types = 1);

require_once __DIR__ . '/TickerRenderer.php';

use LMOnext\Addon\Ticker\TickerRenderer;

// Eigene Sprachdateien laden (addon/ticker/lang/de.php + en.php) - siehe
// dortiger Docblock. Diese Datei wird über frontend_handlers bei jedem
// Request geladen, daher genügt ein einmaliger Aufruf hier.
if (function_exists('addonManager')) {
    \addonManager()->loadLanguages('ticker');
}

registerHook('liga.ticker_block', static function (array $data) : array {
    $data['html'] = TickerRenderer::render((int)$data['liga_id']);
    return $data;
});
