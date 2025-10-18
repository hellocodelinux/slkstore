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

echo '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8">';
echo '<meta name="viewport" content="width=device-width, initial-scale=1.0">';
// Output the page title and the processed CSS.
echo '<title>SlkStore - Slackware Apps</title><style>' . $css . '</style></head>';
// Add the current theme as a class to the body for further styling.
echo '<body class="' . $theme . '">';

echo '<div class="about">';

// Read and process the README.md file for display.

$readme = file_get_contents('../README.md');

$readme = htmlspecialchars($readme);

// Títulos
$readme = preg_replace('/^# (.*)$/m', '<h2>$1</h2>', $readme);
$readme = preg_replace('/^## (.*)$/m', '<h3>$1</h3>', $readme);
$readme = preg_replace('/^### (.*)$/m', '<h3>$1</h3>', $readme);

// Separadores ---
$readme = preg_replace('/^---$/m', '<hr>', $readme);

// Negritas **
$readme = preg_replace('/\*\*(.*?)\*\*/', '<strong>$1</strong>', $readme);

// Listas con *
$readme = preg_replace_callback('/^\* (.*)$/m', function ($matches) {
    static $in_list = false;
    $item           = '<li>' . $matches[1] . '</li>';
    if (! $in_list) {
        $in_list = true;
        return '<ul>' . $item;
    }
    return $item;
}, $readme);

// Cierre de listas
$readme .= '</ul>';

// Enlaces [text](url)
$readme = preg_replace('/\[(.*?)\]\((.*?)\)/', '$2', $readme);

// Convertir saltos de línea en <br>
$readme = nl2br($readme);
$readme = preg_replace('/(<br\s*\/?>\s*){2,}/i', '', $readme);
echo $readme;

echo '</div>';