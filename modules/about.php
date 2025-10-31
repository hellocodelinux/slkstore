<?php
/**
 * About Page Generator
 *
 * This script generates the About page for SlkStore by:
 * - Reading and processing the README.md file
 * - Converting Markdown syntax to HTML
 * - Applying theme-based styling
 * - Handling code blocks and special formatting
 */

include $_SERVER['DOCUMENT_ROOT'] . '/modules/preinit.php';

// Initialize the HTML document with proper meta tags and encoding
echo '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8">';
echo '<meta name="viewport" content="width=device-width, initial-scale=1.0">';
// Output the page title and the dynamically processed CSS.
echo '<title>SlkStore - Slackware Apps</title><style>' . $css . '</style></head>';
// Apply the current theme as a class to the body for potential theme-specific styling.
echo '<body class="' . $theme . '">';

echo '<div class="about">';

// Load the README file content that contains the About information
// The file is located in the root directory of the application
$readme = file_get_contents('../README.md');

// Process Markdown headings into their HTML equivalents
// Convert different heading levels maintaining the document hierarchy:
// # Title    -> <h2>Title</h2>    (main sections)
// ## Title   -> <h3>Title</h3>    (subsections)
// ### Title  -> <h3>Title</h3>    (detailed sections)
$readme = preg_replace('/^# (.*)$/m', '<h2>$1</h2>', $readme);
$readme = preg_replace('/^## (.*)$/m', '<h3>$1</h3>', $readme);
$readme = preg_replace('/^### (.*)$/m', '<h3>$1</h3>', $readme);

// Convert Markdown horizontal rules (---) to <hr> tags.
$readme = preg_replace('/^---$/m', '<hr>', $readme);

// Convert Markdown bold text (**text**) to <strong> tags.
$readme = preg_replace('/\*\*(.*?)\*\*/', '<strong>$1</strong>', $readme);

// Process Markdown unordered lists into HTML list structures
// Handles lists that start with * (asterisk) and maintains proper HTML nesting
// Example:
// * Item 1     ->  <ul><li>Item 1</li>
// * Item 2     ->      <li>Item 2</li></ul>
$readme = preg_replace_callback('/^\* (.*)$/m', function ($matches) {
    static $in_list = false; // Track if we're currently inside a list
    $item           = '<li>' . $matches[1] . '</li>';

    // Start a new list if this is the first item encountered
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

// Process code blocks marked with triple backticks (```)
// This handles:
// 1. Language-specific syntax highlighting classes
// 2. HTML escaping for security
// 3. Proper code block formatting
// Example: ```php echo "Hello"; ``` -> <div class="code-block language-php">echo &quot;Hello&quot;</div>
$readme = preg_replace_callback('/```([\w-]*)\s*(.*?)\s*```/s', function ($m) {
    // Add language class if specified in the markdown
    $language = $m[1] ? ' language-' . htmlspecialchars($m[1]) : '';
    // Create code block with escaped content to prevent XSS
    return '<div class="code-block' . $language . '">' . htmlspecialchars($m[2]) . '</div>';
}, $readme);

// Handle line breaks in the content while preserving code block formatting
// This process:
// 1. Skips code blocks to preserve their formatting
// 2. Converts regular newlines to HTML breaks
// 3. Removes redundant line breaks to maintain clean formatting
$readme = preg_replace_callback('/<div class="code-block.*?<\/div>(*SKIP)(*FAIL)|./s', function ($m) {
    // Convert newlines to HTML breaks for regular content
    $text = nl2br($m[0]);
    // Remove excessive consecutive line breaks
    return preg_replace('/(<br\s*\/?>\s*){1,}/i', '', $text);
}, $readme);

// Convert Markdown italics (*text*) to <em> tags.
$readme = preg_replace('/\*(.*?)\*/', '<em>$1</em>', $readme);

// Output the processed README content.
echo $readme;

echo '</div>';

// Close the HTML document.
echo '</body></html>';
