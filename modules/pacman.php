<?php

// Set the path for the theme configuration file.
$config_file = '../themes/theme.conf';

// Check if the theme configuration file exists.
if (file_exists($config_file)) {
    // If it exists, read the theme from the file.
    $theme = trim(file_get_contents($config_file));
} else {
    // If it doesn't exist, default to the 'dark' theme.
    $theme = 'dark';
}

// Check if a 'theme' parameter is set in the URL and if it's a valid theme.
if (isset($_GET['theme']) && in_array($_GET['theme'], ['dark', 'light'])) {
    // If a valid theme is passed, update the theme variable.
    $theme = $_GET['theme'];
    // Save the new theme setting to the configuration file.
    file_put_contents($config_file, $theme);
}

// Read the content of the main stylesheet.
$css = file_get_contents('../themes/style.css');

// If the theme is 'light', process the CSS to invert colors.
if ($theme === 'light') {
    // Use a regular expression to find all hex color codes.
    $css = preg_replace_callback('/#([0-9a-fA-F]{6})/', function ($m) {
        // For each color, calculate its inverse.
        $hex = $m[1];
        $r   = 255 - hexdec(substr($hex, 0, 2));
        $g   = 255 - hexdec(substr($hex, 2, 2));
        $b   = 255 - hexdec(substr($hex, 4, 2));
        // Return the new inverted color in hex format.
        return sprintf("#%02X%02X%02X", $r, $g, $b);
    }, $css);
}

// Start outputting the HTML document.
echo '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8">';
echo '<meta name="viewport" content="width=device-width, initial-scale=1.0">';
// Output the page title and the processed CSS.
echo '<title>SlkStore - Slackware Apps</title><style>' . $css . '</style></head>';
// Add the current theme as a class to the body for further styling.
echo '<body class="' . $theme . '">';

echo '<div class="pacman">';
echo '<button class="back" onclick="history.back()" style="margin-bottom: 15px;">Back</button>';

// Load the cached package data.
if (file_exists(__DIR__ . '/../cache/packages.php')) {
    include __DIR__ . '/../cache/packages.php';
} else {
    echo '<p>Cache file not found. Please build the cache first.</p>';
    $products_cache = [];
}

// Sort products by name length descending to avoid partial matches (e.g. 'qt' matching 'qt-creator')
usort($products_cache, function ($a, $b) {
    return strlen($b['name']) - strlen($a['name']);
});

// Get the list of installed slackdce packages.
$installed_packages_str = shell_exec('ls /var/log/packages | grep "_slackdce"');
$installed_packages     = $installed_packages_str ? explode("
", trim($installed_packages_str)) : [];

// Display the count of installed packages.
$installed_count = count($installed_packages);
echo "<h2>$installed_count SlackDCE packages installed</h2>";

echo '<div class="installed-packages-list">';

$updates_available = 0;
$update_list_html  = '';

foreach ($installed_packages as $installed_pkg_full) {
    if (empty($installed_pkg_full)) {
        continue;
    }

    $update_found             = false;
    $available_pkg_for_update = '';

    foreach ($products_cache as $product) {
        // Check if the installed package filename starts with the product name and a hyphen.
        if (strpos($installed_pkg_full, $product['name'] . '-') === 0) {
            $available_pkg_full_no_ext = pathinfo($product['full'], PATHINFO_FILENAME);
            if ($installed_pkg_full !== $available_pkg_full_no_ext) {
                $update_found             = true;
                $available_pkg_for_update = $available_pkg_full_no_ext;
            }
            // Found the right product, break from the inner loop.
            break;
        }
    }

    if ($update_found) {
        $updates_available++;
        $update_list_html .= '<div class="installed-package-item">';
        $update_list_html .= '<div class="package-info">';
        $update_list_html .= '<span class="package-name">' . htmlspecialchars($installed_pkg_full) . ' =&gt; ' . htmlspecialchars($available_pkg_for_update) . '</span>';
        $update_list_html .= '</div>';
        $update_list_html .= '<div class="package-actions"><button class="update-button">Update</button></div>';
        $update_list_html .= '</div>';
    }
}

if ($updates_available > 0) {
    echo $update_list_html;
} else {
    echo "<p>All updated, continue</p>";

}

echo '</div>'; // .installed-packages-list
echo '</div>'; // .pacman
echo '</body></html>';
