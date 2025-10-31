<?php

// Define the path for the theme configuration file.
$config_file = $_SERVER['DOCUMENT_ROOT'] . '/themes/theme.conf';

// Check if the theme configuration file exists to determine the current theme.
if (file_exists($config_file)) {
    // If the file exists, read the theme from it.
    $theme = trim(file_get_contents($config_file));
} else {
    // If the file does not exist, default to the 'dark' theme.
    $theme = 'dark';
}

// Allow theme switching via URL parameter (e.g., ?theme=light).
if (isset($_GET['theme']) && in_array($_GET['theme'], ['dark', 'light'])) {
    // If a valid theme is provided in the URL, update the theme variable.
    $theme = $_GET['theme'];
    // Save the newly selected theme to the configuration file for persistence.
    file_put_contents($config_file, $theme);
}

// Read the main stylesheet content.
$css = file_get_contents($_SERVER['DOCUMENT_ROOT'] . '/themes/style.css');

// If the theme is 'light', invert the colors in the CSS.
if ($theme === 'light') {
    // Use a regular expression to find all 6-digit hex color codes.
    $css = preg_replace_callback('/#([0-9a-fA-F]{6})/', function ($matches) {
        // For each matched color, calculate its photographic negative.
        $hex = $matches[1];
        $r   = 255 - hexdec(substr($hex, 0, 2));
        $g   = 255 - hexdec(substr($hex, 2, 2));
        $b   = 255 - hexdec(substr($hex, 4, 2));
        // Return the new inverted color in hex format.
        return sprintf("#%02X%02X%02X", $r, $g, $b);
    }, $css);
}

?>