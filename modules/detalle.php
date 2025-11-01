<?php
/**
 * Package Detail Display Module
 *
 * This script renders a detailed view of a specific package in the SlkStore.
 * It displays comprehensive information including:
 * - Basic package metadata (name, version, category)
 * - Installation status and actions
 * - Package sizes (compressed and uncompressed)
 * - Screenshots from Flathub (when available)
 * - Dependencies and requirements
 * - Full package description
 */

include $_SERVER['DOCUMENT_ROOT'] . '/modules/preinit.php';

// Retrieve and validate the application name from the URL query string
// Exit with an error message if the 'app' parameter is not provided
$app = isset($_GET['app']) ? $_GET['app'] : '';
if (! $app) {
    exit('app not set');
}

// Load the cached package data and installation status functions.
include $_SERVER['DOCUMENT_ROOT'] . '/cache/packages.php';
include $_SERVER['DOCUMENT_ROOT'] . '/modules/insta_status.php';
$products = $products_cache;

// Search for the requested application in the package database
// Perform a case-insensitive comparison to ensure reliable matching
$found = null;
foreach ($products as $p) {
    if (strtolower($p['name']) === strtolower($app)) {
        $found = $p;
        break;
    }
}

// If the package wasn't found in the database, terminate with an error
if (! $found) {
    exit('Application not found');
}

// Get application details.
$category  = strtolower($found['category']);
$installed = is_installed($found['name']);

// Initialize variables for README and .info files from the slackbuilds cache.
// These could be used for additional package information display
$slackbuilds_dir = $_SERVER['DOCUMENT_ROOT'] . '/cache/slackbuilds';
$readme_content  = '';
$info_content    = [];

// Start generating the HTML output with application details and styling
// Create the page structure with proper encoding and theme application
echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>' . htmlspecialchars($found['name']) . '</title><style>' . $css . '</style></head><body class="' . $theme . '">';
$icon = '../' . htmlspecialchars($found['icon']);
echo '<div class="app-header" style="display:flex;align-items:center;gap:20px;">';
echo '<img src="' . $icon . '" class="app-icon" style="width: 128px; height: 128px; min-width: 128px;">';
echo '<div class="app-title">' . htmlspecialchars($found['name']) . '<br>Version ' . htmlspecialchars(substr($found['version'], 0, 10)) . '</div>';
echo '<div class="app-actions">';
echo '<button class="app-install" ' . ($installed ? 'disabled' : '') . ' onclick="parent.showInIframe(\'modules/packinstall.php?full=' . urlencode($found['full']) . '\')">Install</button>';
echo '<button class="app-remove" ' . ($installed ? '' : 'disabled') . ' onclick="parent.showInIframe(\'modules/packremove.php?full=' . urlencode($found['full']) . '\')">Remove</button>';
echo '<button class="back" onclick="history.back()">Back</button>';
echo '</div></div>';

// Extract the short description from the full description
// The description is expected to be in parentheses within the desc field
if (preg_match('/\(([^)]+)\)/', $found['desc'], $m)) {
    $desc = $m[1];
}

echo '<div class="app-enca">';

// Attempt to find a screenshot for the application in Flathub's AppStream data
// The process involves:
// 1. Loading the AppStream XML database
// 2. Creating an XPath query to find matching application entries
// 3. Extracting the first screenshot URL if available
$screenshot_url = '';
$xml            = new DOMDocument();
$xml->load($_SERVER['DOCUMENT_ROOT'] . '/cache/flathub-appstream.xml');
$xpath = new DOMXPath($xml);
$name  = strtolower($found['name']);
// Case-insensitive XPath query to find the first screenshot for this application
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

// Display the main package details section
// This includes essential information about the package:
// - Package identification (name, category, version)
// - Package description
// - Size information (both compressed and installed sizes)
echo '<div class="app-detailm">';
echo '<b>Name:</b> ' . htmlspecialchars($found['name']) . '<br>';
echo '<b>Category:</b> ' . htmlspecialchars($found['category']) . '<br>';
echo '<b>Version:</b> ' . htmlspecialchars($found['version']) . '<br>';
echo '<b>Description:</b> ' . htmlspecialchars($desc) . '<br>';
// Convert size strings from KB to floating point values for conversion to MB
$sizec = (float) str_replace(' K', '', $found['sizec']);
$sizeu = (float) str_replace(' K', '', $found['sizeu']);
echo '<b>Compressed Size:</b> ' . number_format($sizec / 1024, 2) . ' MB<br>';
echo '<b>Uncompressed Size:</b> ' . number_format($sizeu / 1024, 2) . ' MB<br>';
echo '<b>Full Package Name:</b> ' . htmlspecialchars($found['full']) . '<br>';
echo '</div></div>';

echo '<pre class="app-detail">' . htmlspecialchars($found['descfull']) . '</pre>';

// Display package dependencies if they exist
// The requirements are displayed in uppercase for better visibility
if ($found['req']) {
    echo '<div class="app-req">REQUIRES: ' . htmlspecialchars(strtoupper(str_replace(',', ' ', $found['req']))) . '</div>';
}

// Close the HTML document.
echo '</body></html>';
