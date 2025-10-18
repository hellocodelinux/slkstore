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

echo '<div class="precar">
Update .. Please wait
</div>';

include 'build_cache.php';
