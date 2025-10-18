<?php

$config_file = 'themes/theme.conf';
if (file_exists($config_file)) {
    $theme = trim(file_get_contents($config_file));
} else {
    $theme = 'dark';
}
if (isset($_GET['theme']) && in_array($_GET['theme'], ['dark', 'light'])) {
    $theme = $_GET['theme'];
    file_put_contents($config_file, $theme);
}
$css = file_get_contents('themes/style.css');
if ($theme === 'light') {
    $css = preg_replace_callback('/#([0-9a-fA-F]{6})/', function ($m) {
        $hex = $m[1];
        $r   = 255 - hexdec(substr($hex, 0, 2));
        $g   = 255 - hexdec(substr($hex, 2, 2));
        $b   = 255 - hexdec(substr($hex, 4, 2));
        return sprintf("#%02X%02X%02X", $r, $g, $b);
    }, $css);
}
$current_category = isset($_GET['category']) ? $_GET['category'] : 'Welcome';
$all_categories   = [
    'Academic', 'Accessibility', 'Audio', 'Business', 'Desktop', 'Development', 'Games', 'Gis', 'Graphics',
    'Ham', 'Haskell', 'Libraries', 'Misc', 'Multimedia', 'Network', 'Office', 'Perl', 'Python', 'Ruby', 'System',
];
sort($all_categories);
array_unshift($all_categories, 'Welcome');
$search     = isset($_GET['search']) ? strtolower($_GET['search']) : '';
$cache_file = 'cache/packages.php';
if (! file_exists($cache_file)) {
    include 'modules/build_cache.php';
}

include $cache_file;
$products = $products_cache;
include 'modules/insta_status.php';

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
echo '<button type="button" class="clear-button" onclick="document.querySelector(\'.search-input\').value=\'\'; window.location.href=\'index.php\';">Home</button>';
echo '</form>';
echo '<nav>';
echo '<a href="' . ($theme === 'light' ? '?theme=light' : '#') . '">Upgrade</a>';
if ($theme === 'dark') {
    echo '<a href="?theme=light" class="theme-icon">☀️</a>';
} else {
    echo '<a href="?theme=dark" class="theme-icon">🌙</a>';
}
echo '<a href="modules/about.php">About</a>';
echo '</nav></div></header>';

echo '<div class="page-body"><aside id="sidebar"><h3 class="sidebar-title">Categories</h3>';
foreach ($all_categories as $category) {
    $active_class = (strtolower($current_category) == strtolower($category)) ? ' active' : '';
    $link         = '?category=' . urlencode($category);
    if ($theme === 'light') {
        $link .= '&theme=light';
    }

    if ($search) {
        $link .= '&search=' . urlencode($search);
    }

    echo '<a href="' . $link . '" class="category-button' . $active_class . '">' . htmlspecialchars($category) . '</a>';
}
echo '</aside><main>';

if (isset($_GET['app'])) {
    $app   = $_GET['app'];
    $found = null;
    foreach ($products as $p) {
        if (strtolower($p['name']) === strtolower($app)) {
            $found = $p;
            break;
        }
    }
    if ($found) {
        $category = strtolower($found['category']);
        $url      = 'modules/detalle.php?app=' . urlencode($app);
        if ($theme === 'light') {
            $url .= '&theme=light';
        }

        // echo '<h2>' . htmlspecialchars($found['name']) . '</h2>';
        echo '<iframe src="' . $url . '" style="width:100%;height:400%;border:none;"></iframe>';
    } else {
        echo '<p>Application not found.</p>';
    }
} else {
    echo '<h2>' . htmlspecialchars(ucfirst($current_category)) . '</h2>';
    if (strtolower($current_category) === 'welcome' && ! $search) {
        echo '<div class="banner"><div class="banner-left"><div class="banner-te">SlkStore - Slackware 15.0 Apps</div>';
        echo '<div class="banner-tex">powered by slackdce repository</div></div>';
        echo '<div class="banner-right"><img src="icons/slkstore.png" class="icon"></div></div>';
        foreach ($all_categories as $category) {
            if ($category === 'Welcome') {
                continue;
            }

            $cat_products = array_filter($products, fn($p) => strtolower($p['category']) === strtolower($category));
            if (! $cat_products) {
                continue;
            }

            shuffle($cat_products);
            $cat_link = '?category=' . urlencode($category);
            if ($theme === 'light') {
                $cat_link .= '&theme=light';
            }

            echo '<h3 class="category-title"><a href="' . $cat_link . '">' . htmlspecialchars($category) . ' ...</a></h3>';
            echo '<div class="category-products">';
            $count = 0;
            foreach ($cat_products as $product) {
                if ($count >= 8) {
                    break;
                }

                $link = '?app=' . urlencode($product['name']);
                if ($theme === 'light') {
                    $link .= '&theme=light';
                }

                echo '<a href="' . $link . '" class="product-link"><div class="product-card">';
                $status = is_installed($product['name']) ? 'installed' : 'not-installed';
                echo '<div class="insta ' . $status . '"></div>';
                echo '<img class="product-icon" src="' . htmlspecialchars($product['icon']) . '">';
                $desc = $product['desc'];
                if (preg_match('/\(([^)]+)\)/', $desc, $m)) {
                    $desc = $m[1];
                }

                echo '<h3 class="product-title">' . htmlspecialchars($product['name']) . '</h3>';
                echo '<p class="product-version">Version ' . htmlspecialchars(substr($product['version'], 0, 10)) . '</p>';
                echo '<p class="product-desc">' . htmlspecialchars($desc) . '</p>';
                echo '<p class="product-size">Size ' . htmlspecialchars($product['sizec']) . ' - Installed ' . htmlspecialchars($product['sizeu']) . '</p>';
                echo '</div></a>';
                $count++;
            }
            echo '</div>';
        }
    } else {
        echo '<div class="product-grid">';
        $display_products = [];
        foreach ($products as $p) {
            if ($search && strpos(strtolower($p['name']), $search) !== false) {
                $display_products[] = $p;
            } elseif (! $search && strtolower($p['category']) === strtolower($current_category)) {
                $display_products[] = $p;
            }

        }
        if (! $display_products) {
            echo "<p>No applications found.</p>";
        } else {
            foreach ($display_products as $product) {
                $link = '?app=' . urlencode($product['name']);
                if ($theme === 'light') {
                    $link .= '&theme=light';
                }

                echo '<a href="' . $link . '" class="product-link"><div class="product-card">';
                $status = is_installed($product['name']) ? 'installed' : 'not-installed';
                echo '<div class="insta ' . $status . '"></div>';
                echo '<img class="product-icon" src="' . htmlspecialchars($product['icon']) . '">';
                $desc = $product['desc'];
                if (preg_match('/\(([^)]+)\)/', $desc, $m)) {
                    $desc = $m[1];
                }

                echo '<h3 class="product-title">' . htmlspecialchars($product['name']) . '</h3>';
                echo '<p class="product-version">Version ' . htmlspecialchars(substr($product['version'], 0, 10)) . '</p>';
                echo '<p class="product-desc">' . htmlspecialchars($desc) . '</p>';
                echo '<p class="product-size">Size ' . htmlspecialchars($product['sizec']) . ' - Installed ' . htmlspecialchars($product['sizeu']) . '</p>';
                echo '</div></a>';
            }
        }
        echo '</div>';
    }
}

echo '</main></div><footer>';
$total_programs = count($products);
$found_programs = isset($display_products) ? count($display_products) : 0;
echo '<div class="container"><p class="copyright">&copy; ' . date("Y") . ' SlkStore (By SlackDCE). All rights reserved.</p>';
echo '<div class="status-bar">';
echo "Programs in this view: $found_programs / Total programs: $total_programs :::: ";
$line = @gzopen('cache/PACKAGES.TXT.gz', 'r');
if ($line) {
    $first_line = trim(gzgets($line));
    $first_line = str_replace('PACKAGES.TXT; ', '', $first_line);
    echo htmlspecialchars($first_line);
    gzclose($line);
}
echo ' - Slackware 15 (64 bit) - ' . date('Y-m-d') . '<br>';
echo '</div></div></footer></body></html>';
