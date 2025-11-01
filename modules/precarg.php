<?php
/**
 * Pre-loading Cache Generator
 *
 * This script manages the cache generation process for SlkStore.
 * It provides a user-friendly loading screen while rebuilding the package cache
 * and handles the output buffering to ensure proper display of status messages.
 *
 * The process includes:
 * - Displaying a loading screen
 * - Building/updating the package cache
 * - Redirecting back to the main interface
 *
 * @package SlkStore
 * @author Eduardo Castillo
 * @email hellcodelinux@gmail.com
 */

// Flush any existing output buffers to ensure immediate display
ob_flush();
flush();

// Include the pre-initialization module for theme support
include $_SERVER['DOCUMENT_ROOT'] . '/modules/preinit.php';

// Start outputting the HTML document.
echo '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8">';
echo '<meta name="viewport" content="width=device-width, initial-scale=1.0">';
// Output the page title and the processed CSS.
echo '<title>SlkStore - Slackware Apps</title><style>' . $css . '</style></head>';
// Add the current theme as a class to the body for further styling.
echo '<body class="' . $theme . ' precar-body">';

// Display the loading message to inform users about the cache update process
// This provides immediate feedback while the potentially long-running operation executes
echo '<div class="precar">
Updating... Please wait
</div>';

// Force the browser to display the loading message immediately
// This ensures users see the status before the cache building begins
// Using both ob_flush() and flush() for maximum compatibility
ob_flush();
flush();

// Execute the cache building process
// This will fetch and process package information from the repository
include 'build_cache.php';

// Redirect to the main page after cache generation is complete
// Using HTML meta refresh for compatibility - no JavaScript required
// The zero-second delay ensures immediate redirect once the cache is built
echo '<meta http-equiv="refresh" content="0;url=../index.php">';

echo '</body></html>';
