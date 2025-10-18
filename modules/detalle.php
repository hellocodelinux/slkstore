<?php
$app = isset($_GET['app']) ? $_GET['app'] : '';
if (! $app) {
    exit('app not set');
}

$config_file = __DIR__ . '/../themes/theme.conf';
$theme       = file_exists($config_file) ? trim(file_get_contents($config_file)) : 'dark';
if (isset($_GET['theme']) && in_array($_GET['theme'], ['dark', 'light'])) {
    $theme = $_GET['theme'];
    file_put_contents($config_file, $theme);
}

$css = file_get_contents(__DIR__ . '/../themes/style.css');
if ($theme === 'light') {
    $css = preg_replace_callback('/#([0-9a-fA-F]{6})/', function ($m) {
        $hex = $m[1];
        $r   = 255 - hexdec(substr($hex, 0, 2));
        $g   = 255 - hexdec(substr($hex, 2, 2));
        $b   = 255 - hexdec(substr($hex, 4, 2));
        return sprintf("#%02X%02X%02X", $r, $g, $b);
    }, $css);
}

// Cargar cache
include __DIR__ . '/../cache/packages.php';
include __DIR__ . '/insta_status.php';
$products = $products_cache;

$found = null;
foreach ($products as $p) {
    if (strtolower($p['name']) === strtolower($app)) {$found = $p;
        break;}
}
if (! $found) {
    exit('Application not found');
}

$category  = strtolower($found['category']);
$installed = is_installed($found['name']);

$slackbuilds_dir = __DIR__ . '/../cache/slackbuilds';
$readme_content  = '';
$info_content    = [];

$app_dir = "$slackbuilds_dir/$category/$app";
if (is_dir($app_dir)) {
    $readme_file = "$app_dir/README";
    if (file_exists($readme_file)) {
        $readme_content = file_get_contents($readme_file);
    }

    $info_file = "$app_dir/$app.info";
    if (file_exists($info_file)) {
        $lines = file($info_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            if (preg_match('/^(\w+)="?(.*?)"?$/', $line, $m)) {
                $info_content[$m[1]] = $m[2];
            }

        }
    }
}

echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>' . htmlspecialchars($found['name']) . '</title><style>' . $css . '</style></head><body class="' . $theme . '">';
$icon = htmlspecialchars($found['icon']);
echo '<div class="app-header" style="display:flex;align-items:center;gap:20px;">';
echo '<img src="' . $icon . '" width="128" height="128" class="app-icon">';
echo '<div class="app-title">' . htmlspecialchars($found['name']) . '<br>Version ' . htmlspecialchars(substr($found['version'], 0, 10)) . '</div>';
echo '<div class="app-actions">';
echo '<button class="app-install" ' . ($installed ? 'disabled' : '') . '>Install</button>';
echo '<button class="app-remove" ' . ($installed ? '' : 'disabled') . '>Remove</button>';
echo '</div></div>';

if ($readme_content !== '') {
    echo '<pre class="app-detail">' . htmlspecialchars($readme_content) . '</pre>';
} else {
    echo '<p>README not found</p>';
}

if ($info_content) {
    if (! empty($info_content['REQUIRES'])) {
        echo '<div class="app-detail">REQUIRES: ' . htmlspecialchars(strtoupper($info_content['REQUIRES'])) . '</div>';
    }
    echo '<div class="app-detailx">';
    if (! empty($info_content['HOMEPAGE'])) {
        echo '<pre>Home: ' . htmlspecialchars($info_content['HOMEPAGE']) . '<br>';
    }
    if (! empty($info_content['DOWNLOAD'])) {
        echo 'Source: https://slackbuilds.org/<br>';
    }
    if (! empty($info_content['MAINTAINER'])) {
        echo 'Maintainer: ' . htmlspecialchars($info_content['MAINTAINER']) . '<br>';
    }
    if (! empty($info_content['EMAIL'])) {
        echo 'Email: ' . htmlspecialchars($info_content['EMAIL']) . '</pre>';
    }
    echo '</div>';
}

echo '</body></html>';
