<?php
/**
 * Package Installation Handler
 *
 * This script manages the package installation process for SlkStore.
 * It handles:
 * - Package dependency resolution
 * - Size calculations for downloads and disk space
 * - Installation status checking
 * - User interface for installation confirmation
 */

include $_SERVER['DOCUMENT_ROOT'] . '/modules/preinit.php';

// Retrieve and sanitize the package identifier from the URL query string
// The 'full' parameter contains the complete package filename
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

/**
 * Search for a package in the package list by its name
 *
 * @param string $name     The package name to search for
 * @param array  $packages List of available packages
 * @return array|null      Package information or null if not found
 */
function find_package_by_name($name, $packages)
{
    foreach ($packages as $pkg) {
        if (strcasecmp($pkg['name'], $name) == 0) {
            return $pkg;
        }
    }
    return null;
}

/**
 * Search for a package in the package list by its full filename
 *
 * @param string $full     The complete filename to search for
 * @param array  $packages List of available packages
 * @return array|null      Package information or null if not found
 */
function find_package_by_full($full, $packages)
{
    foreach ($packages as $pkg) {
        if ($pkg['full'] === $full) {
            return $pkg;
        }
    }
    return null;
}

/**
 * Recursively resolve package dependencies
 *
 * This function traverses the dependency tree of a package and builds
 * a list of all required packages. It handles circular dependencies
 * and ensures each package is only processed once.
 *
 * @param string $package_name    Name of the package to resolve dependencies for
 * @param array  $all_packages    Complete list of available packages
 * @param array  &$resolved_deps  Reference to array storing resolved dependencies
 * @param array  &$processing     Reference to array tracking packages being processed
 */
function resolve_dependencies($package_name, $all_packages, &$resolved_deps, &$processing)
{
    // Skip if package has already been resolved
    if (isset($resolved_deps[$package_name])) {
        return;
    }

    // Prevent circular dependency loops
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

/**
 * Convert package size from Kilobytes to Megabytes
 *
 * Takes a size string in the format "XXXX K" and converts it to MB
 * Used for displaying more user-friendly size information
 *
 * @param string $size_str Size string in the format "XXXX K"
 * @return float          Size in megabytes
 */
function convert_size_to_mb($size_str)
{
    $size_kb = floatval(str_replace(' K', '', $size_str));
    return $size_kb / 1024;
}

// Begin the main package installation process
// This section handles package resolution and displays installation information
if ($full !== 'Not provided') {
    // Locate the requested package in the package database
    $initial_package = find_package_by_full($full, $all_packages);

    if ($initial_package) {
        // Initialize the list of packages to be installed
        // Start with the main requested package
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
                $dep_package  = find_package_by_name($dep_name, $all_packages);
                $is_installed = is_installed($dep_name);
                $class_color  = $is_installed ? 'installed' : 'not-installed';

                echo '<li class="' . $class_color . '">' . htmlspecialchars($dep_full) . ($is_installed ? ' ✔️' : '');

                if ($dep_package && ! $is_installed) {
                    $packages_to_install[] = $dep_full;
                    $dep_size_c_mb         = convert_size_to_mb($dep_package['sizec']);
                    $dep_size_u_mb         = convert_size_to_mb($dep_package['sizeu']);
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
