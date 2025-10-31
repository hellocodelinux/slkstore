<?php

// Define the path for the theme configuration file.
$config_file = '../themes/theme.conf';

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
$css = file_get_contents('../themes/style.css');

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

// Begin HTML output.
echo '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8">';
echo '<meta name="viewport" content="width=device-width, initial-scale=1.0">';
// Output the page title and the dynamically processed CSS.
echo '<title>SlkStore - Slackware Apps</title><style>' . $css . '</style></head>';
// Apply the current theme as a class to the body for potential theme-specific styling.
echo '<body class="' . $theme . '">';

echo '<div class="about">';

// Read the README.md file to display its content on the page.
$readme = file_get_contents('../README.md');

// Convert Markdown headings to HTML tags (h2, h3).
$readme = preg_replace('/^# (.*)$/m', '<h2>$1</h2>', $readme);
$readme = preg_replace('/^## (.*)$/m', '<h3>$1</h3>', $readme);
$readme = preg_replace('/^### (.*)$/m', '<h3>$1</h3>', $readme);

// Convert Markdown horizontal rules (---) to <hr> tags.
$readme = preg_replace('/^---$/m', '<hr>', $readme);

// Convert Markdown bold text (**text**) to <strong> tags.
$readme = preg_replace('/\*\*(.*?)\*\*/', '<strong>$1</strong>', $readme);

// Convert Markdown unordered lists (* item) to HTML lists (<ul><li>...</li></ul>).
$readme = preg_replace_callback('/^\* (.*)$/m', function ($matches) {
    static $in_list = false;
    $item           = '<li>' . $matches[1] . '</li>';
    // If this is the first item, open the <ul> tag.
    if (! $in_list) {
        $in_list = true;
        return '<ul>' . $item;
    }
    return $item;
}, $readme);

// Close the list. Note: This assumes a list was present and may create an empty <ul> tag otherwise.
$readme .= '</ul>';

// Convert Markdown links [text](url) to just the URL.
$readme = preg_replace('/\[(.*?)\]\((.*?)\)/', '$2', $readme);

// Convert newlines to <br> tags and remove excessive line breaks.
$readme = nl2br($readme);
$readme = preg_replace('/(<br\s*\/?>\s*){1,}/i', '', $readme);

// Convert Markdown italics (*text*) to <em> tags.
$readme = preg_replace('/\*(.*?)\*/', '<em>$1</em>', $readme);

// Output the processed README content.
echo $readme;

echo '</div>';

// Close the HTML document.
echo '</body></html>';
