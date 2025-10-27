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

// Check for the application's directory in the slackbuilds cache.
$app_dir = "$slackbuilds_dir/$category/$app";
if (is_dir($app_dir)) {
    // Read the README file if it exists.
    $readme_file = "$app_dir/README";
    if (file_exists($readme_file)) {
        $readme_content = file_get_contents($readme_file);
    }

    // Read the .info file if it exists and parse its contents.
    $info_file = "$app_dir/$app.info";
    if (file_exists($info_file)) {
        $lines = file($info_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            if (preg_match('/^(\w+)="?(.*?)"?$/', $line, $m)) {
                $info_content[$m[1]] = $m[2];
            }

        }
    }
}

// Start generating the HTML output.
echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>' . htmlspecialchars($found['name']) . '</title><style>' . $css . '</style></head><body class="' . $theme . '">';
$icon = '../' . htmlspecialchars($found['icon']); // Adjust path for being in /modules
                                                  // Display the application header with icon, name, version, and install/remove buttons.
echo '<div class="app-header" style="display:flex;align-items:center;gap:20px;">';
echo '<img src="' . $icon . '" width="128" height="128" class="app-icon">';
echo '<div class="app-title">' . htmlspecialchars($found['name']) . '<br>Version ' . htmlspecialchars(substr($found['version'], 0, 10)) . '</div>';
echo '<div class="app-actions">';
echo '<button class="app-install" ' . ($installed ? 'disabled' : '') . '>Install</button>';
echo '<button class="app-remove" ' . ($installed ? '' : 'disabled') . '>Remove</button>';
echo '</div></div>';

// Display application details from packages.php
echo '<div class="app-detailm">';
echo '<b>Name:</b> ' . htmlspecialchars($found['name']) . '<br>';
echo '<b>Category:</b> ' . htmlspecialchars($found['category']) . '<br>';
echo '<b>Version:</b> ' . htmlspecialchars($found['version']) . '<br>';
echo '<b>Description:</b> ' . htmlspecialchars($found['desc']) . '<br>';
echo '<b>Compressed Size:</b> ' . htmlspecialchars($found['sizec']) . '<br>';
echo '<b>Uncompressed Size:</b> ' . htmlspecialchars($found['sizeu']) . '<br>';
echo '<b>Full Package Name:</b> ' . htmlspecialchars($found['full']) . '<br>';
echo '</div>';

// Display the README content if available.
if ($readme_content !== '') {
    echo '<pre class="app-detail">' . htmlspecialchars($readme_content) . '</pre>';
} else {
    echo '<p>README not found</p>';
}

// Display information from the .info file, such as requirements, homepage, maintainer, etc.
if ($info_content) {
    if (! empty($info_content['REQUIRES'])) {
        echo '<div class="app-detail">REQUIRES: ' . htmlspecialchars(strtoupper($info_content['REQUIRES'])) . '</div>';
    }
    echo '<div class="app-detailx">';
    if (! empty($info_content['HOMEPAGE'])) {
        echo '<pre>Home: ' . htmlspecialchars($info_content['HOMEPAGE']) . '<br>';
    }

    echo 'Source: https://slackbuilds.org/<br>';

    if (! empty($info_content['MAINTAINER'])) {
        echo 'Maintainer: ' . htmlspecialchars($info_content['MAINTAINER']) . '<br>';
    }
    if (! empty($info_content['EMAIL'])) {
        echo 'Email: ' . htmlspecialchars($info_content['EMAIL']) . '</pre>';
    }
    echo '</div>';
}

// Close the HTML document.
echo '</body></html>';
