<?php

include $_SERVER['DOCUMENT_ROOT']. '/modules/preinit.php';

// Get 'full' from the query string.
$full = isset($_GET['full']) ? htmlspecialchars($_GET['full']) : 'Not provided';

// Start HTML output.
echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Package Installation</title>';
echo '<style>' . $css . '</style></head><body class="' . $theme . '">';

echo '<h1>Package Installation</h1>';
echo '<form action="/modules/downloader.php" method="post">';
echo '<div class="install-header"><h2 class="install-title">Will be installed:</h2><button type="submit" class="button-accept">Accept</button><button type="button" class="button-cancel" onclick="history.back()">Cancel</button></div>';
echo '<div class="datapack">' . $full . '</div>';

// --- Dependency Resolution Code ---

include $_SERVER['DOCUMENT_ROOT'] . '/cache/packages.php';
$all_packages = $products_cache;

// Function to find a package by its name
function find_package_by_name($name, $packages)
{
    foreach ($packages as $pkg) {
        if (strcasecmp($pkg['name'], $name) == 0) {
            return $pkg;
        }
    }
    return null;
}

// Function to find a package by its full filename
function find_package_by_full($full, $packages)
{
    foreach ($packages as $pkg) {
        if ($pkg['full'] === $full) {
            return $pkg;
        }
    }
    return null;
}

// Recursive function to resolve dependencies
function resolve_dependencies($package_name, $all_packages, &$resolved_deps, &$processing)
{
    // If already resolved, do nothing
    if (isset($resolved_deps[$package_name])) {
        return;
    }

    // Avoid circular dependencies
    if (isset($processing[$package_name])) {
        return;
    }
    $processing[$package_name] = true;

    $package = find_package_by_name($package_name, $all_packages);

    if ($package && ! empty($package['req'])) {
        $dependencies = explode(',', $package['req']);
        foreach ($dependencies as $dep_name) {
            $dep_name = trim($dep_name);
            if (! empty($dep_name)) {
                resolve_dependencies($dep_name, $all_packages, $resolved_deps, $processing);
            }
        }
    }

    // Add the current package to the resolved list if it exists
    if ($package) {
        $resolved_deps[$package['name']] = $package['full'];
    }

    unset($processing[$package_name]);
}

// Function to convert size string (e.g., "5260 K") to MB
function convert_size_to_mb($size_str) {
    $size_kb = floatval(str_replace(' K', '', $size_str));
    return $size_kb / 1024;
}

if ($full !== 'Not provided') {
    $initial_package = find_package_by_full($full, $all_packages);

    if ($initial_package) {
        $packages_to_install   = [];
        $packages_to_install[] = $initial_package['full'];
        // Display initial package details
        $initial_size_c_mb = convert_size_to_mb($initial_package['sizec']);
        $initial_size_u_mb = convert_size_to_mb($initial_package['sizeu']);
        echo '<div class="package-size">Compressed: ' . number_format($initial_size_c_mb, 2) . ' MB, Uncompressed: ' . number_format($initial_size_u_mb, 2) . ' MB</div>';

        $total_size_c = $initial_size_c_mb;
        $total_size_u = $initial_size_u_mb;

        $resolved_dependencies = [];
        $processing_stack      = [];

        echo '<h3>Dependencies:</h3>';

        // Start resolution from the initial package's dependencies
        if (! empty($initial_package['req'])) {
            $dependencies = explode(',', $initial_package['req']);
            foreach ($dependencies as $dep_name) {
                $dep_name = trim($dep_name);
                if (! empty($dep_name)) {
                    resolve_dependencies($dep_name, $all_packages, $resolved_dependencies, $processing_stack);
                }
            }
        }

        if (empty($resolved_dependencies)) {
            echo '<p>No dependencies found.</p>';
        } else {
            include $_SERVER['DOCUMENT_ROOT'] . '/modules/insta_status.php'; // Include the status check function
            echo '<ul class="listpack">';
            foreach ($resolved_dependencies as $dep_name => $dep_full) {
                $dep_package = find_package_by_name($dep_name, $all_packages);
                $is_installed = is_installed($dep_name);
                $class_color  = $is_installed ? 'installed' : 'not-installed';
                
                echo '<li class="' . $class_color . '">' . htmlspecialchars($dep_full) . ($is_installed ? ' ✔️' : '');


                if ($dep_package && !$is_installed) {
                    $packages_to_install[] = $dep_full;
                    $dep_size_c_mb = convert_size_to_mb($dep_package['sizec']);
                    $dep_size_u_mb = convert_size_to_mb($dep_package['sizeu']);
                    $total_size_c += $dep_size_c_mb;
                    $total_size_u += $dep_size_u_mb;
                    echo '<div class="package-size">Compressed: ' . number_format($dep_size_c_mb, 2) . ' MB, Uncompressed: ' . number_format($dep_size_u_mb, 2) . ' MB</div>';
                }
                echo '</li><br>';
            }
            echo '</ul>';

            echo '<div class="total-size">';
            echo '<h4 style="font-weight: normal;">Total to download: <b>' . number_format($total_size_c, 2) . ' MB</b> - Total size on disk: <b>' . number_format($total_size_u, 2) . ' MB</b></h4>';
            echo '</div>';
        }
        echo '<input type="hidden" name="packages" value="' . htmlspecialchars(json_encode($packages_to_install)) . '">';
    } else {
        echo '<p>Initial package not found.</p>';
    }
}

echo '</form>';
// --- End of Code ---

echo '</body></html>';