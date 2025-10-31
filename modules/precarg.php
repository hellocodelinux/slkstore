<?php

ob_flush();
flush();

include $_SERVER['DOCUMENT_ROOT'] . '/modules/preinit.php';


// Start outputting the HTML document.
echo '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8">';
echo '<meta name="viewport" content="width=device-width, initial-scale=1.0">';
// Output the page title and the processed CSS.
echo '<title>SlkStore - Slackware Apps</title><style>' . $css . '</style></head>';
// Add the current theme as a class to the body for further styling.
echo '<body class="' . $theme . ' precar-body">';

// Display the "Please wait" message and start the cache build.
echo '<div class="precar">
Updating... Please wait
</div>';

// Flush the output buffer to ensure the message is displayed
// before the long-running cache build process starts.
ob_flush();
flush();

// Include and execute the script to build the application cache.
include 'build_cache.php';

// After building the cache, redirect to the index page using an HTML meta refresh tag.
// This avoids using JavaScript.
echo '<meta http-equiv="refresh" content="0;url=../index.php">';

echo '</body></html>';
