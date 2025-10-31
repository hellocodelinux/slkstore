<?php

include $_SERVER['DOCUMENT_ROOT']  . '/modules/preinit.php';

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
