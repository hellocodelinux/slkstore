<?php

// --- THEME HANDLING ---

// Define the configuration file path for the theme
$config_file = 'themes/theme.conf';

// Check if the theme configuration file exists and read the theme from it.
// Default to 'dark' if the file doesn't exist.
if (file_exists($config_file)) {
    $theme = trim(file_get_contents($config_file));
} else {
    $theme = 'dark';
}

// Check if a theme is specified in the URL query parameters.
// If it is, update the theme and save it to the configuration file.
if (isset($_GET['theme']) && in_array($_GET['theme'], ['dark', 'light'])) {
    $theme = $_GET['theme'];
    file_put_contents($config_file, $theme);
}

// Read the base CSS file.
$css = file_get_contents('themes/style.css');

// If the theme is 'light', invert the colors in the CSS.
// This is a simple way to create a light theme from a dark one.
if ($theme === 'light') {
    $css = preg_replace_callback('/#([0-9a-fA-F]{6})/', function ($m) {
        $hex = $m[1];
        $r   = 255 - hexdec(substr($hex, 0, 2));
        $g   = 255 - hexdec(substr($hex, 2, 2));
        $b   = 255 - hexdec(substr($hex, 4, 2));
        return sprintf("#%02X%02X%02X", $r, $g, $b);
    }, $css);
}

// --- CATEGORY AND SEARCH HANDLING ---

// Get the current category from the URL, default to 'Welcome'.
$current_category = isset($_GET['category']) ? $_GET['category'] : 'Welcome';

// Define the list of all available categories.
$all_categories = [
    'Academic', 'Accessibility', 'Audio', 'Business', 'Desktop', 'Development', 'Games', 'Gis', 'Graphics',
    'Ham', 'Haskell', 'Libraries', 'Misc', 'Multimedia', 'Network', 'Office', 'Perl', 'Python', 'Ruby', 'System',
];

// Sort the categories alphabetically.
sort($all_categories);

// Add 'Welcome' to the beginning of the categories list.
array_unshift($all_categories, 'Welcome');

// Get the search term from the URL, if any.
$search = isset($_GET['search']) ? strtolower($_GET['search']) : '';

// --- CACHE AND DATA LOADING ---

// Define the path for the package cache file.
$cache_file = 'cache/packages.php';

// If the cache file doesn't exist, build it by including the build script.
if (! file_exists($cache_file)) {
    include 'modules/build_cache.php';
}

// Include the cache file to load the product data.
include $cache_file;
$products = $products_cache; // Assign cached products to a variable.

// Include the script to check the installation status of applications.
include 'modules/insta_status.php';

// --- HTML RENDERING ---

// Start rendering the HTML document.
echo '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8">';
echo '<meta name="viewport" content="width=device-width, initial-scale=1.0">';
echo '<title>SlkStore - Slackware Apps</title><style>' . $css . '</style></head>';
echo '<body class="' . $theme . '">';

// --- HEADER ---
echo '<header><div class="page-container"><div class="container">';
echo '<h1 class="logo">SlkStore v1.0</h1>';

// Search form
echo '<form method="get" class="search-form">';
echo '<input type="text" name="search" placeholder="Search apps..." value="' . (isset($_GET['search']) ? htmlspecialchars($_GET['search']) : '') . '" class="search-input">';
echo '<input type="hidden" name="theme" value="' . $theme . '">'; // Preserve theme on search
echo '<button type="submit" class="search-button">Go</button>';
echo '<button type="button" class="clear-button" onclick="document.querySelector(\'.search-input\').value=\'\'; window.location.href=\'index.php\';">Home</button>';
echo '</form>';

// Navigation
echo '<nav>';
echo '<a href="' . ($theme === 'light' ? '?theme=light' : '#') . '">Upgrade</a>';
// Theme switcher icon
if ($theme === 'dark') {
    echo '<a href="?theme=light" class="theme-icon">☀️</a>';
} else {
    echo '<a href="?theme=dark" class="theme-icon">🌙</a>';
}
echo '<a href="#" onclick="document.querySelector(\'main\').innerHTML=\'<iframe src=&quot;modules/about.php&quot; style=&quot;width:100%;height:400%;border:none;&quot;></iframe>\';return false;">About</a>';
echo '</nav></div></header>';

// --- MAIN CONTENT ---
echo '<div class="page-body">';

// --- SIDEBAR (CATEGORIES) ---
echo '<aside id="sidebar"><h3 class="sidebar-title">Categories</h3>';
foreach ($all_categories as $category) {
    $active_class = (strtolower($current_category) == strtolower($category)) ? ' active' : '';
    $link         = '?category=' . urlencode($category);
    // Preserve theme and search parameters in category links
    if ($theme === 'light') {
        $link .= '&theme=light';
    }
    if ($search) {
        $link .= '&search=' . urlencode($search);
    }
    echo '<a href="' . $link . '" class="category-button' . $active_class . '">' . htmlspecialchars($category) . '</a>';
}
echo '</aside>';

// --- CONTENT AREA ---
echo '<main>';

// Check if a specific application is requested.
if (isset($_GET['app'])) {
    $app   = $_GET['app'];
    $found = null;
    // Find the application in the products list.
    foreach ($products as $p) {
        if (strtolower($p['name']) === strtolower($app)) {
            $found = $p;
            break;
        }
    }
    // If the application is found, display its details in an iframe.
    if ($found) {
        $category = strtolower($found['category']);
        $url      = 'modules/detalle.php?app=' . urlencode($app);
        if ($theme === 'light') {
            $url .= '&theme=light';
        }
        echo '<iframe src="' . $url . '" style="width:100%;height:400%;border:none;"></iframe>';
    } else {
        echo '<p>Application not found.</p>';
    }
} else {
    // --- DEFAULT VIEW (CATEGORY OR WELCOME PAGE) ---
    echo '<h2>' . htmlspecialchars(ucfirst($current_category)) . '</h2>';

    // If it's the Welcome page and there's no search, show the banner and featured apps.
    if (strtolower($current_category) === 'welcome' && ! $search) {
        echo '<div class="banner"><div class="banner-left"><div class="banner-te">SlkStore - Slackware 15.0 Apps</div>';
        echo '<div class="banner-tex">powered by slackdce repository</div></div>';
        echo '<div class="banner-right"><img src="icons/slkstore.png" class="icon"></div></div>';

        // Display a random selection of apps from each category.
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
                // Show up to 8 apps per category

                $link = '?app=' . urlencode($product['name']);
                if ($theme === 'light') {
                    $link .= '&theme=light';
                }

                echo '<a href="' . $link . '" class="product-link"><div class="product-card">';
                $status = is_installed($product['name']) ? 'installed' : 'not-installed';
                echo '<div class="insta ' . $status . '"></div>';
                echo '<img class="product-icon" src="' . htmlspecialchars($product['icon']) . '">';
                // Extract description from parenthesis if available
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
        // --- CATEGORY/SEARCH RESULTS VIEW ---
        echo '<div class="product-grid">';
        $display_products = [];
        // Filter products based on search or category.
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
            // Display the filtered products.
            foreach ($display_products as $product) {
                $link = '?app=' . urlencode($product['name']);
                if ($theme === 'light') {
                    $link .= '&theme=light';
                }

                echo '<a href="' . $link . '" class="product-link"><div class="product-card">';
                $status = is_installed($product['name']) ? 'installed' : 'not-installed';
                echo '<div class="insta ' . $status . '"></div>';
                echo '<img class="product-icon" src="' . htmlspecialchars($product['icon']) . '">';
                // Extract description from parenthesis if available
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

echo '</main></div>';

// --- FOOTER ---
echo '<footer>';
$total_programs = count($products);
$found_programs = isset($display_products) ? count($display_products) : 0;
echo '<div class="container"><p class="copyright">&copy; ' . date("Y") . ' SlkStore (By SlackDCE). All rights reserved.</p>';
echo '<div class="status-bar">';
echo "Programs in this view: $found_programs / $total_programs - ";
// Read and display information from the PACKAGES.TXT.gz file.
$line = @gzopen('cache/PACKAGES.TXT.gz', 'r');
if ($line) {
    $first_line = trim(gzgets($line));
    $first_line = str_replace('PACKAGES.TXT; ', '', $first_line);
    echo htmlspecialchars($first_line);
    gzclose($line);
}
echo ' - Slackware 15 (64 bit) <br>';
echo '</div></div></footer></body></html>';
