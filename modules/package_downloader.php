<?php
include_once $_SERVER['DOCUMENT_ROOT'].'/cache/packages.php';

// Get package full name from POST
$package_to_download_full = isset($_POST['package']) ? htmlspecialchars_decode($_POST['package']) : null;

if (!$package_to_download_full) {
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'No package specified.']);
    exit;
}

$all_packages = $products_cache;

function find_package_by_full($full, $packages) {
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
    $base_url = 'https://slackware.uk/slackdce/packages/15.0/x86_64/';
    $download_dir = $_SERVER['DOCUMENT_ROOT'] . '/tmp/';
    $url = $base_url . strtolower($package_info['category']) . '/' . $package_info['name'] . '/' . $package_info['full'];
    $destination = $download_dir . $package_info['full'];

    // Download the file
    shell_exec("wget -q -O " . escapeshellarg($destination) . " " . escapeshellarg($url));

    if (!file_exists($destination)) {
        $response = ['status' => 'error', 'message' => 'Failed to download ' . htmlspecialchars($package_to_download_full)];
    } else {
        $response = ['status' => 'success', 'message' => 'Successfully downloaded ' . htmlspecialchars($package_to_download_full)];
    }
} else {
    $response = ['status' => 'error', 'message' => 'Package not found: ' . htmlspecialchars($package_to_download_full)];
}

header('Content-Type: application/json');
echo json_encode($response);
