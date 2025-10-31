<?php

ini_set('memory_limit', '2G');
ini_set('max_execution_time', '600');
ini_set('output_buffering', '1');
ini_set('opcache.memory_consumption', '512');
ini_set('opcache.max_accelerated_files', '16000');
ini_set('opcache.revalidate_freq', '300');
ini_set('opcache.validate_timestamps', '1');

ob_start(); // Start output buffering to show loading screen

// --- THEME HANDLING ---

// Define the path for the theme configuration file.
$config_file = 'themes/theme.conf';

// Check if the theme configuration file exists and read the theme from it.
// If the file doesn't exist, default to the 'dark' theme.
if (file_exists($config_file)) {
    $theme = trim(file_get_contents($config_file));
} else {
    $theme = 'dark';
}

// Check if a theme is specified in the URL query parameters.
// If a valid theme is provided ('dark' or 'light'), update the current theme
// and save it to the configuration file for persistence.
if (isset($_GET['theme']) && in_array($_GET['theme'], ['dark', 'light'])) {
    $theme = $_GET['theme'];
    file_put_contents($config_file, $theme);
}

// Read the base CSS stylesheet.
$css = file_get_contents('themes/style.css');

// If the current theme is 'light', dynamically invert the colors in the CSS.
// This provides a simple mechanism to switch between dark and light themes
// by programmatically altering the color values.
if ($theme === 'light') {
    $css = preg_replace_callback('/#([0-9a-fA-F]{6})/', function ($m) {
        $hex = $m[1];
        $r   = 255 - hexdec(substr($hex, 0, 2));
        $g   = 255 - hexdec(substr($hex, 2, 2));
        $b   = 255 - hexdec(substr($hex, 4, 2));
        return sprintf("#%02X%02X%02X", $r, $g, $b);
    }, $css);
}

// --- HTML RENDERING (Part 1) & LOADING SCREEN ---

// Begin rendering the HTML document.
echo '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8">';
echo '<meta name="viewport" content="width=device-width, initial-scale=1.0">';
echo '<title>SlkStore - Slackware Apps</title><style>' . $css . '</style>';
echo '<script>
function showInIframe(url) {
    window.scrollTo(0, 0);
    var f = document.createElement("iframe");
    f.src = url;
    f.style.width = "100%";
    f.style.border = "none";
    f.onload = function() { this.style.height = this.contentWindow.document.body.scrollHeight + "px"; };
    document.querySelector("main").innerHTML = "";
    document.querySelector("main").appendChild(f);
}
</script></head>';
echo '<body class="' . $theme . '">';

// --- UPDATE CHECK ---

$check_file    = __DIR__ . '/tmp/slackdce_update_check.txt';
$local_pkg_gz  = __DIR__ . '/cache/PACKAGES.TXT.gz';
$remote_pkg_gz = 'rsync://slackware.uk/slackdce/packages/15.0/x86_64/PACKAGES.TXT.gz';
$today         = date('Y-m-d');
$last_check    = @file_get_contents($check_file);

if ($last_check !== $today) {
    // Display a loading overlay only when checking/updating

    // Use rsync with checksum to check if the remote PACKAGES.TXT.gz has changed.
    // The -i flag will produce output if there are differences.
    $command = sprintf('rsync -ani %s %s', escapeshellarg($remote_pkg_gz), escapeshellarg($local_pkg_gz));
    $output  = shell_exec($command);

    // If rsync output is not empty, it means the file has changed.
    if (! empty(trim($output))) {
        include "modules/precarg.php";
        header('Location: index.php'); // Redirect to reload the page after cache build
        exit;                          // Ensure script stops execution
    }
}

// If we reach here, there is no update. No overlay is shown.

$readmex = file('README.md');
$version = trim(end($readmex));

// --- CATEGORY AND SEARCH HANDLING ---

// Get the current category from the URL query parameters. Default to 'Welcome'.
$current_category = isset($_GET['category']) ? $_GET['category'] : 'Welcome';

// Define the list of all available application categories.
$all_categories = [
    'Academic', 'Accessibility', 'Audio', 'Business', 'Desktop', 'Development', 'Games', 'Gis', 'Graphics',
    'Ham', 'Haskell', 'Libraries', 'Misc', 'Multimedia', 'Network', 'Office', 'Perl', 'Python', 'Ruby', 'System',
];

// Sort the categories alphabetically for consistent ordering.
sort($all_categories);

// Add the 'Welcome' category to the beginning of the list.
array_unshift($all_categories, 'Welcome');

// Get the search term from the URL query parameters, if provided.
$search = isset($_GET['search']) ? strtolower($_GET['search']) : '';

// --- CACHE AND DATA LOADING ---

// Define the path for the cached packages data file.
$cache_file = 'cache/packages.php';

// If the cache file does not exist, generate it by running the build script.
// This ensures that the application data is available for the store.
if (! file_exists($cache_file)) {
    header('Location: modules/precarg.php');
    exit;
}

// Load the cached product data.
include $cache_file;
$products = $products_cache; // Assign the cached products to a working variable.

// Include the script to determine the installation status of applications.
include 'modules/insta_status.php';

// --- HTML RENDERING (Part 2) ---

// --- HEADER SECTION ---
echo '<header><div class="page-container"><div class="container">';
echo '<h1 class="logo">SlkStore ' . $version . '</h1>';

// Search form
echo '<form method="get" class="search-form">';
echo '<input type="text" name="search" placeholder="Search apps..." value="' . (isset($_GET['search']) ? htmlspecialchars($_GET['search']) : '') . '" class="search-input">';
echo '<input type="hidden" name="theme" value="' . $theme . '">'; // Preserve the current theme across searches.
echo '<button type="submit" class="search-button">Go</button>';
echo '<button type="button" class="clear-button" onclick="document.querySelector(\'.search-input\').value=\'\'; window.location.href=\'index.php\';">Clear</button>';
echo '</form>';

// Main navigation
echo '<nav>';
echo '<a href="#" onclick="showInIframe(\'modules/pacman.php\'); return false;">Upgrade</a>';
// Theme switcher icon (sun or moon)
if ($theme === 'dark') {
    echo '<a href="?theme=light" class="theme-icon">☀️</a>';
} else {
    echo '<a href="?theme=dark" class="theme-icon">🌙</a>';
}
echo '<a href="#" onclick="showInIframe(\'modules/about.php\'); return false;">About</a>';
echo '</nav></div></header>';

// --- MAIN CONTENT ---
echo '<div class="page-body">';

// --- SIDEBAR (CATEGORIES) ---
echo '<aside id="sidebar"><h3 class="sidebar-title">Categories</h3>';
foreach ($all_categories as $category) {
    $active_class = (strtolower($current_category) == strtolower($category)) ? ' active' : '';
    $link         = '?category=' . urlencode($category);
    // Preserve theme and search parameters in category links.
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

// Check if a specific application is requested to be displayed.
if (isset($_GET['app'])) {
    $app   = $_GET['app'];
    $found = null;
    // Search for the requested application in the product list.
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
        echo '<script>var f=document.createElement("iframe");f.src="' . $url . '";f.style.width="100%";f.style.border="none";f.onload=function(){this.style.height=this.contentWindow.document.body.scrollHeight+"px";};document.querySelector("main").innerHTML="";document.querySelector("main").appendChild(f);</script>';
    } else {
        echo '<p>Application not found.</p>';
    }
} else {
    // --- DEFAULT VIEW (EITHER WELCOME PAGE OR CATEGORY LISTING) ---
    echo '<h2>' . htmlspecialchars(ucfirst($current_category)) . '</h2>';

    // If on the Welcome page and not searching, display a banner and featured apps.
    if (strtolower($current_category) === 'welcome' && ! $search) {
        echo '<div class="banner"><div class="banner-left"><div class="banner-te">SlkStore - Slackware 15.0 Apps</div>';
        echo '<div class="banner-tex">powered by slackdce repository</div></div>';
        echo '<div class="banner-right"><img src="cache/icons/slkstore.png" class="icon"></div></div>';

        // Display a random selection of applications from each category.
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
                    break; // Show up to 8 applications per category on the welcome page.
                }

                $link = '?app=' . urlencode($product['name']);
                if ($theme === 'light') {
                    $link .= '&theme=light';
                }

                echo '<a href="' . $link . '" class="product-link"><div class="product-card">';
                $status = is_installed($product['name']) ? 'installed' : 'not-installed';
                echo '<div class="insta ' . $status . '"></div>';
                echo '<img class="product-icon" src="' . htmlspecialchars($product['icon']) . '">';
                // Extract a cleaner description from parenthesis if available.
                $desc = $product['desc'];
                if (preg_match('/\(([^)]+)\)/', $desc, $m)) {
                    $desc = $m[1];
                }
                echo '<h3 class="product-title">' . htmlspecialchars($product['name']) . '</h3>';
                echo '<p class="product-version">Version ' . htmlspecialchars(substr($product['version'], 0, 10)) . '</p>';
                echo '<p class="product-desc">' . htmlspecialchars($desc) . '</p>';
                echo '<p class="product-size">Size ' . round(htmlspecialchars($product['sizec']) / 1024, 2) . ' MB - Installed ' . round(htmlspecialchars($product['sizeu']) / 1024, 2) . ' MB</p>';
                echo '</div></a>';
                $count++;
            }
            echo '</div>';
        }
    } else {
        // --- CATEGORY/SEARCH RESULTS VIEW ---
        echo '<div class="product-grid">';
        $display_products = [];
        // Filter products based on the current search term or category.
        foreach ($products as $p) {
            $name_match = false;
            $desc_match = false;
            if ($search) {
            $name_match = strpos(strtolower($p['name']), $search) !== false;
            $desc_match = strpos(strtolower($p['desc']), $search) !== false;
            }
            if ($search && ($name_match || $desc_match)) {
                $display_products[] = $p;
            } elseif (! $search && strtolower($p['category']) === strtolower($current_category)) {
                $display_products[] = $p;
            }
        }

        if (! $display_products) {
            echo "<p>No applications found.</p>";
        } else {
            // Display the filtered list of products.
            foreach ($display_products as $product) {
                $link = '?app=' . urlencode($product['name']);
                if ($theme === 'light') {
                    $link .= '&theme=light';
                }

                echo '<a href="' . $link . '" class="product-link"><div class="product-card">';
                $status = is_installed($product['name']) ? 'installed' : 'not-installed';
                echo '<div class="insta ' . $status . '"></div>';
                echo '<img class="product-icon" src="' . htmlspecialchars($product['icon']) . '">';
                // Extract a cleaner description from parenthesis if available.
                $desc = $product['desc'];
                if (preg_match('/\(([^)]+)\)/', $desc, $m)) {
                    $desc = $m[1];
                }
                echo '<h3 class="product-title">' . htmlspecialchars($product['name']) . '</h3>';
                echo '<p class="product-version">Version ' . htmlspecialchars(substr($product['version'], 0, 10)) . '</p>';
                echo '<p class="product-desc">' . htmlspecialchars($desc) . '</p>';
                echo '<p class="product-size">Size ' . round(htmlspecialchars($product['sizec']) / 1024, 2) . ' MB - Installed ' . round(htmlspecialchars($product['sizeu']) / 1024, 2) . ' MB</p>';
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
echo '<div class="container"><p class="copyright">&copy; ' . date("Y") . ' SlkStore ' . $version . ' (By SlackDCE). All rights reserved.</p>';
echo '<div class="status-bar">';
echo "Programs in this view: $found_programs / $total_programs - ";
// Read and display repository information from the PACKAGES.TXT file.
$packages_txt_path = 'cache/PACKAGES.TXT';
if (file_exists($packages_txt_path)) {
    $line = @fopen($packages_txt_path, 'r');
    if ($line) {
        $first_line = trim(fgets($line));
        $first_line = str_replace('PACKAGES.TXT; ', '', $first_line);
        echo htmlspecialchars($first_line);
        fclose($line);
    }
}
echo ' - Slackware 15 (64 bit) <br>';
echo '</div></div></footer></body></html>';
