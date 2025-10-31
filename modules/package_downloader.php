<?php
/**
 * Package Download Handler
 *
 * This script manages the package download process for SlkStore.
 * It handles:
 * - Package verification
 * - Download from the Slackware repository
 * - Error handling and status reporting
 * - JSON response generation
 */

// Load the cached package database
include_once $_SERVER['DOCUMENT_ROOT'] . '/cache/packages.php';

// Retrieve and sanitize the package name from the POST request
// The package name should be the full package filename
$package_to_download_full = isset($_POST['package']) ? htmlspecialchars_decode($_POST['package']) : null;

if (! $package_to_download_full) {
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'No package specified.']);
    exit;
}

$all_packages = $products_cache;

/**
 * Search for a package in the package list by its full filename
 *
 * @param string $full     The complete package filename to search for
 * @param array  $packages List of available packages
 * @return array|null      Package information array or null if not found
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

$package_info = find_package_by_full($package_to_download_full, $all_packages);

$response = [];

if ($package_info) {
    // Configure download parameters
    // Base URL for the Slackware repository
    $base_url = 'https://slackware.uk/slackdce/packages/15.0/x86_64/';
    // Local temporary directory for downloads
    $download_dir = $_SERVER['DOCUMENT_ROOT'] . '/tmp/';
    // Construct the full URL based on package category and name
    $url = $base_url . strtolower($package_info['category']) . '/' . $package_info['name'] . '/' . $package_info['full'];
    // Set the local destination path for the downloaded file
    $destination = $download_dir . $package_info['full'];

    // Download the package using wget
    // -q: quiet mode (no output)
    // -O: specify output file
    shell_exec("wget -q -O " . escapeshellarg($destination) . " " . escapeshellarg($url));

    // Verify download success by checking if the file exists
    if (! file_exists($destination)) {
        // Download failed - generate error response
        $response = ['status' => 'error', 'message' => 'Failed to download ' . htmlspecialchars($package_to_download_full)];
    } else {
        // Download successful - generate success response
        $response = ['status' => 'success', 'message' => 'Successfully downloaded ' . htmlspecialchars($package_to_download_full)];
    }
} else {
    // Package not found in the database - generate error response
    $response = ['status' => 'error', 'message' => 'Package not found: ' . htmlspecialchars($package_to_download_full)];
}

// Send JSON response
// Set the content type header for JSON
header('Content-Type: application/json');
// Output the response as JSON
echo json_encode($response);
