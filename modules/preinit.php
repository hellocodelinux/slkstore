<?php
/**
 * Pre-initialization Module
 *
 * This module handles the theme system initialization and CSS processing
 * for the SlkStore application. It manages:
 * - Theme configuration loading and saving
 * - Theme switching functionality
 * - CSS processing and color inversion for light/dark modes
 */

// Define the path for the theme configuration file that stores user preference
$config_file = $_SERVER['DOCUMENT_ROOT'] . '/themes/theme.conf';

// Load the current theme setting from configuration
// The system supports two themes: 'dark' (default) and 'light'
if (file_exists($config_file)) {
    // Read and sanitize the theme setting from the configuration file
    $theme = trim(file_get_contents($config_file));
} else {
    // If no configuration exists, default to the 'dark' theme for better visibility
    $theme = 'dark';
}

// Handle dynamic theme switching through URL parameters
// Users can change themes by appending ?theme=light or ?theme=dark to any URL
if (isset($_GET['theme']) && in_array($_GET['theme'], ['dark', 'light'])) {
    // Validate and apply the requested theme
    $theme = $_GET['theme'];
    // Persist the theme preference to maintain it across page loads
    file_put_contents($config_file, $theme);
}

// Load the base stylesheet that defines the application's appearance
$css = file_get_contents($_SERVER['DOCUMENT_ROOT'] . '/themes/style.css');

// Process the CSS for light theme by inverting colors
// This provides an automatic light mode without maintaining separate stylesheets
if ($theme === 'light') {
    // Find and process all hexadecimal color codes in the CSS
    // The regular expression matches standard 6-digit hex colors (#RRGGBB)
    $css = preg_replace_callback('/#([0-9a-fA-F]{6})/', function ($matches) {
        // Convert each color to its inverse by subtracting each RGB component from 255
        $hex = $matches[1];
        $r   = 255 - hexdec(substr($hex, 0, 2)); // Red component
        $g   = 255 - hexdec(substr($hex, 2, 2)); // Green component
        $b   = 255 - hexdec(substr($hex, 4, 2)); // Blue component
                                                 // Format the inverted color back to hexadecimal
        return sprintf("#%02X%02X%02X", $r, $g, $b);
    }, $css);
}
