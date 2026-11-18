<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/../config/map_component.php';

ob_start();
burnout_render_map('test-editor-a');
burnout_render_map('test-reader', false);
burnout_render_map('test-editor-b');
$html = ob_get_clean();
$dom = new DOMDocument();
$previous = libxml_use_internal_errors(true);
$dom->loadHTML('<?xml encoding="UTF-8">' . $html);
libxml_clear_errors();
libxml_use_internal_errors($previous);
$xpath = new DOMXPath($dom);
function map_component_check(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
    echo 'OK: ' . $message . PHP_EOL;
}
map_component_check($xpath->query('//*[@data-burnout-map]')->length === 3, 'Varias instancias del mismo componente');
map_component_check($xpath->query('//*[@data-map-mode="edit"]//canvas')->length === 2, 'Canvas solo en editores');
map_component_check($xpath->query('//*[@id="test-reader"]//*[@data-map-action]')->length === 3 && $xpath->query('//*[@id="test-reader"]//*[@data-map-tool or @data-map-color or @data-map-size or @data-map-fill-menu or @role="dialog"]')->length === 0, 'Modo lectura sin herramientas ni popups de edición');
map_component_check($xpath->query('//*[@data-burnout-map]//*[@data-map-overlay]')->length === 3, 'Capa independiente por instancia');
map_component_check($xpath->query('//*[contains(@class,"admin-map-canvas-wrap")]/*[contains(@class,"admin-map-zoom")]')->length === 3 && $xpath->query('//*[contains(@class,"admin-map-toolbar")]//*[@data-map-action="zoom-in" or @data-map-action="zoom-out"]')->length === 0, 'Zoom flotante dentro del mapa, fuera de la barra superior');
$ids = [];
foreach ($xpath->query('//*[@id]') as $node) {
    $id = $node->getAttribute('id');
    map_component_check(!isset($ids[$id]), 'Identificador único: ' . $id);
    $ids[$id] = true;
}
foreach (['aria-controls', 'aria-labelledby', 'aria-describedby', 'for'] as $attribute) {
    foreach ($xpath->query('//*[@' . $attribute . ']') as $node) {
        foreach (explode(' ', $node->getAttribute($attribute)) as $target) {
            if (!isset($ids[$target])) throw new RuntimeException('Referencia accesible inexistente: ' . $attribute);
        }
    }
}
map_component_check(true, 'Referencias accesibles resueltas dentro de sus instancias');
