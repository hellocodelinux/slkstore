<?php

// Define the path to the theme configuration file.
$config_file = '../themes/theme.conf';

// Check if the theme configuration file exists.
if (file_exists($config_file)) {
    // If it exists, read the theme from the file.
    $theme = trim(file_get_contents($config_file));
} else {
    // If it doesn't exist, default to the 'dark' theme.
    $theme = 'dark';
}

// Check if a theme is specified in the URL query parameters.
if (isset($_GET['theme']) && in_array($_GET['theme'], ['dark', 'light'])) {
    // If a valid theme is specified, update the theme variable.
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
        // Get the hex color from the match.
        $hex = $m[1];
        // Invert the red, green, and blue components of the color.
        $r = 255 - hexdec(substr($hex, 0, 2));
        $g = 255 - hexdec(substr($hex, 2, 2));
        $b = 255 - hexdec(substr($hex, 4, 2));
        // Return the new inverted hex color code.
        return sprintf("#%02X%02X%02X", $r, $g, $b);
    }, $css);
}

// Start outputting the HTML document.
echo '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8">';
echo '<meta name="viewport" content="width=device-width, initial-scale=1.0">';
echo '<title>SlkStore - Slackware Apps</title><style>' . $css . '</style></head>';
echo '<body class="' . $theme . '">';

// Output the header section.
echo '<header><div class="page-container"><div class="container">';
echo '<h1 class="logo">SlkStore v1.0</h1>';

// Output the search form.
echo '<form method="get" class="search-form">';
echo '<input type="text" name="search" placeholder="Search apps..." value="' . (isset($_GET['search']) ? htmlspecialchars($_GET['search']) : '') . '" class="search-input">';
echo '<input type="hidden" name="theme" value="' . $theme . '">';
echo '<button type="submit" class="search-button">Go</button>'; 
echo '<button type="button" class="clear-button" onclick="document.querySelector(\'.search-input\').value=\'\'; window.location.href=\'../index.php\';">Home</button>';
echo '</form>';

// Output the navigation menu.
echo '<nav>';
echo '<a href="' . ($theme === 'light' ? '?theme=light' : '#') . '">Upgrade</a>';

// Output the theme switcher icon based on the current theme.
if ($theme === 'dark') {
    echo '<a href="?theme=light" class="theme-icon">☀️</a>';
} else {
    echo '<a href="?theme=dark" class="theme-icon">🌙</a>';
}
echo '<a href="about.php">About</a>';
echo '</nav></div></header>';

// Output the about section content.
echo '<div class="about">';
echo '<h2>SlackStore created by Eduardo Castillo - (2025)</h2>';
echo 'A graphical software store for Slackware.<br><br>';
echo 'Technologies used: Qt5, PHP, Slackware Linux.<br>';
echo 'License: Creative Commons Attribution-NonCommercial-NoDerivatives 4.0 International (CC BY-NC-ND 4.0)<br>';
echo 'Email: hellocodelinux@gmail.com<br>';
echo 'Repository: https://slackware.uk/slackdce/<br>';
echo 'Slackdce manifest: https://slackware.uk/slackdce/MANIFEST.txt<br>';
echo '</div></div></footer></body></html>';
