<?php
// Get the application name from the query string, or exit if not set.
$app = isset($_GET['app']) ? $_GET['app'] : '';
if (! $app) {
    exit('app not set');
}

// Determine the theme (dark or light) from a configuration file or query string.
$config_file = __DIR__ . '/../themes/theme.conf';
$theme       = file_exists($config_file) ? trim(file_get_contents($config_file)) : 'dark';
if (isset($_GET['theme']) && in_array($_GET['theme'], ['dark', 'light'])) {
    $theme = $_GET['theme'];
    file_put_contents($config_file, $theme);
}

// Load the base stylesheet.
$css = file_get_contents(__DIR__ . '/../themes/style.css');
// If the theme is light, invert the colors in the stylesheet.
if ($theme === 'light') {
    $css = preg_replace_callback('/#([0-9a-fA-F]{6})/', function ($m) {
        $hex = $m[1];
        $r   = 255 - hexdec(substr($hex, 0, 2));
        $g   = 255 - hexdec(substr($hex, 2, 2));
        $b   = 255 - hexdec(substr($hex, 4, 2));
        return sprintf("#%02X%02X%02X", $r, $g, $b);
    }, $css);
}

// Load the cached package data and installation status functions.
include __DIR__ . '/../cache/packages.php';
include __DIR__ . '/insta_status.php';
$products = $products_cache;

// Find the specified application in the product list.
$found = null;
foreach ($products as $p) {
    if (strtolower($p['name']) === strtolower($app)) {$found = $p;
        break;}
}
// Exit if the application is not found.
if (! $found) {
    exit('Application not found');
}

// Get application details.
$category  = strtolower($found['category']);
$installed = is_installed($found['name']);

// Prepare to read README and .info files from the slackbuilds cache.
$slackbuilds_dir = __DIR__ . '/../cache/slackbuilds';
$readme_content  = '';
$info_content    = [];

// Start generating the HTML output.
echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>' . htmlspecialchars($found['name']) . '</title><style>' . $css . '</style></head><body class="' . $theme . '">';
$icon = '../' . htmlspecialchars($found['icon']);
echo '<div class="app-header" style="display:flex;align-items:center;gap:20px;">';
echo '<img src="' . $icon . '" class="app-icon" style="width: 128px; height: 128px; min-width: 128px;">';
echo '<div class="app-title">' . htmlspecialchars($found['name']) . '<br>Version ' . htmlspecialchars(substr($found['version'], 0, 10)) . '</div>';
echo '<div class="app-actions">';
echo '<button class="app-install" ' . ($installed ? 'disabled' : '') . ' onclick="parent.showInIframe(\'modules/packinstall.php?full=' . urlencode($found['full']) . '\')">Install</button>';
echo '<button class="app-remove" ' . ($installed ? '' : 'disabled') . '>Remove</button>';
echo '<button class="back" onclick="history.back()">Back</button>';
echo '</div></div>';

// Display application details from packages.php

if (preg_match('/\(([^)]+)\)/', $found['desc'], $m)) {
    $desc = $m[1];
}

echo '<div class="app-enca">';

$screenshot_url = '';
$xml            = new DOMDocument();
$xml->load(__DIR__ . '/../cache/flathub-appstream.xml');
$xpath   = new DOMXPath($xml);
$name    = strtolower($found['name']);
$query   = "//component[contains(translate(id,'ABCDEFGHIJKLMNOPQRSTUVWXYZ','abcdefghijklmnopqrstuvwxyz'),'$name')]/screenshots/screenshot/image[1]";
$entries = $xpath->query($query);
if ($entries->length > 0) {
    $screenshot_url = $entries->item(0)->textContent;
}

if (! empty($screenshot_url)) {
    echo '<div class="app-screenshot">';
    echo '<img src="' . htmlspecialchars($screenshot_url) . '" width="320" height="240" alt="Screenshot of ' . htmlspecialchars($found['name']) . '">';
    echo '</div>';
}

echo '<div class="app-detailm">';
echo '<b>Name:</b> ' . htmlspecialchars($found['name']) . '<br>';
echo '<b>Category:</b> ' . htmlspecialchars($found['category']) . '<br>';
echo '<b>Version:</b> ' . htmlspecialchars($found['version']) . '<br>';
echo '<b>Description:</b> ' . htmlspecialchars($desc) . '<br>';
echo '<b>Compressed Size:</b> ' . number_format(htmlspecialchars($found['sizec']) / 1024, 2) . ' MB<br>';
echo '<b>Uncompressed Size:</b> ' . number_format(htmlspecialchars($found['sizeu']) / 1024, 2) . ' MB<br>';
echo '<b>Full Package Name:</b> ' . htmlspecialchars($found['full']) . '<br>';
echo '</div></div>';

echo '<pre class="app-detail">' . htmlspecialchars($found['descfull']) . '</pre>';

// Display information from the .info file, such as requirements, homepage, maintainer, etc.

if ($found['req']) {
    echo '<div class="app-req">REQUIRES: ' . htmlspecialchars(strtoupper(str_replace(',', ' ', $found['req']))) . '</div>';
}

// Close the HTML document.
echo '</body></html>';
