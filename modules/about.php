<?php

$config_file = '../themes/theme.conf';
if (file_exists($config_file)) {
    $theme = trim(file_get_contents($config_file));
} else {
    $theme = 'dark';
}
if (isset($_GET['theme']) && in_array($_GET['theme'], ['dark', 'light'])) {
    $theme = $_GET['theme'];
    file_put_contents($config_file, $theme);
}
$css = file_get_contents('../themes/style.css');
if ($theme === 'light') {
    $css = preg_replace_callback('/#([0-9a-fA-F]{6})/', function ($m) {
        $hex = $m[1];
        $r   = 255 - hexdec(substr($hex, 0, 2));
        $g   = 255 - hexdec(substr($hex, 2, 2));
        $b   = 255 - hexdec(substr($hex, 4, 2));
        return sprintf("#%02X%02X%02X", $r, $g, $b);
    }, $css);
}

echo '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8">';
echo '<meta name="viewport" content="width=device-width, initial-scale=1.0">';
echo '<title>SlkStore - Slackware Apps</title><style>' . $css . '</style></head>';
echo '<body class="' . $theme . '">';
echo '<header><div class="page-container"><div class="container">';
echo '<h1 class="logo">SlkStore v1.0</h1>';
echo '<form method="get" class="search-form">';
echo '<input type="text" name="search" placeholder="Search apps..." value="' . (isset($_GET['search']) ? htmlspecialchars($_GET['search']) : '') . '" class="search-input">';
echo '<input type="hidden" name="theme" value="' . $theme . '">';
echo '<button type="submit" class="search-button">Go</button>';
echo '<button type="button" class="clear-button" onclick="document.querySelector(\'.search-input\').value=\'\'; window.location.href=\'../index.php\';">Home</button>';
echo '</form>';
echo '<nav>';
echo '<a href="' . ($theme === 'light' ? '?theme=light' : '#') . '">Upgrade</a>';
if ($theme === 'dark') {
    echo '<a href="?theme=light" class="theme-icon">☀️</a>';
} else {
    echo '<a href="?theme=dark" class="theme-icon">🌙</a>';
}
echo '<a href="about.php">About</a>';
echo '</nav></div></header>';
echo '<div class="about">';
echo '<h2>SlackStore created by Eduardo Castillo - (2025)</h2>';
echo 'A graphical software store for Slackware.<br><br>';
echo 'Technologies used: Qt5, PHP, Slackware Linux.<br>';
echo 'License: Creative Commons Attribution-NonCommercial-NoDerivatives 4.0 International (CC BY-NC-ND 4.0)<br>';
echo 'Email: hellocodelinux@gmail.com<br>';
echo 'Repository: https://slackware.uk/slackdce/<br>';
echo 'Slackdce manifest: https://slackware.uk/slackdce/MANIFEST.txt<br>';
echo '</div></div></footer></body></html>';