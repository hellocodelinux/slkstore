<?php
// Start output buffering to prevent any premature output.
ob_start();
set_time_limit(600); // Set timeout to 10 minutes for downloads and extraction.

// --- CONFIGURATION AND INITIALIZATION ---

// Define paths for cache files, directories, and remote URLs.
$cache_file      = __DIR__ . '/../cache/packages.php';  // Path to the generated PHP cache file.
$hash_file       = __DIR__ . '/../cache/packages.hash'; // Path to store the hash of PACKAGES.TXT.gz.
$datas_dir       = __DIR__ . '/../datas/';              // Directory containing local data files.
$icon_dir        = __DIR__ . '/../cache/icons';         // Directory containing package icons.
$packages_file   = __DIR__ . '/../cache/PACKAGES.TXT';  // Path to the uncompressed packages list.
$slackbuilds_dir = __DIR__ . '/../cache/slackbuilds';   // Directory to extract SlackBuilds into.
$logstore        = __DIR__ . '/../tmp/logstore.txt';    // Path to the log file for this script.
$manifest_file   = __DIR__ . '/../cache/manifest.json'; // Path to the manifest file storing file hashes.
$products_cache  = [];                                  // Initialize an array to hold package data.

/**
 * Appends a log message with a timestamp to the log file.
 * @param string $m The message to log.
 */
function logmsg($m)
{
    global $logstore;
    file_put_contents($logstore, date('[Y-m-d H:i:s] ') . $m . "\n", FILE_APPEND);
}

// --- SCRIPT EXECUTION START ---

logmsg('start');

// Create necessary directories if they don't exist.
if (! is_dir(__DIR__ . '/../cache')) {
    mkdir(__DIR__ . '/../cache', 0755, true);
}

if (! is_dir(dirname($logstore))) {
    mkdir(dirname($logstore), 0755, true);
}

if (! is_dir($icon_dir)) {
    mkdir($icon_dir, 0755, true);
}

if (! is_dir($datas_dir)) {
    mkdir($datas_dir, 0755, true);
}

// --- SYNC PACKAGES AND SLACKBUILDS FROM GITHUB ---

$github_raw_url = 'https://raw.githubusercontent.com/hellocodelinux/slkstore/main/datas/';
$github_api_url = 'https://api.github.com/repos/hellocodelinux/slkstore/contents/datas';

/**
 * Downloads a file from a URL.
 * @param string $url The URL of the file to download.
 * @param string $dest The destination path to save the file.
 * @return bool True on success, false on failure.
 */
function download_file($url, $dest)
{
    logmsg("Downloading $url to $dest");
    $ch = curl_init($url);
    $fp = fopen($dest, 'w');
    if ($fp === false) {
        logmsg("Failed to open destination file: $dest");
        curl_close($ch);
        return false;
    }
    curl_setopt($ch, CURLOPT_USERAGENT, 'SlkStore-App'); // GitHub API requires a User-Agent
    curl_setopt($ch, CURLOPT_FILE, $fp);
    curl_setopt($ch, CURLOPT_HEADER, 0);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    $result    = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    fclose($fp);

    if ($result === false || $http_code !== 200) {
        logmsg("Download failed for $url. HTTP code: $http_code");
        @unlink($dest); // Clean up failed download
        return false;
    }
    logmsg("Download successful for $url.");
    return true;
}

// --- HASH-BASED UPDATE CHECK ---

$local_manifest       = file_exists($manifest_file) ? json_decode(file_get_contents($manifest_file), true) : [];
$remote_manifest_data = [];

// Fetch remote manifest from GitHub API
logmsg("Fetching remote manifest from GitHub API...");
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $github_api_url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
curl_setopt($ch, CURLOPT_USERAGENT, 'SlkStore-App'); // GitHub API requires a User-Agent
$response  = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($response !== false && $http_code === 200) {
    $files = json_decode($response, true);
    foreach ($files as $file) {
        if (isset($file['name']) && isset($file['sha'])) {
            $remote_manifest_data[$file['name']] = $file['sha'];
        }
    }
    logmsg("Successfully fetched remote manifest.");
} else {
    logmsg("Failed to fetch remote manifest. HTTP code: $http_code. Will skip updates.");
    $remote_manifest_data = []; // Prevent updates if API fails
}

// Define file types and their properties using prefixes.
$file_types = [
    'ic'  => ['extract_dir' => $icon_dir, 'type' => 'tar'],
    'sl'  => ['extract_dir' => $slackbuilds_dir, 'type' => 'tar'],
    'pkg' => ['extract_dir' => $packages_file, 'type' => 'gz'],
];

$package_list_updated = false;

foreach ($file_types as $prefix => $info) {
    // Find the remote file that matches the current prefix.
    $remote_filename = null;
    $remote_hash     = null;
    foreach ($remote_manifest_data as $name => $sha) {
        if (strpos($name, $prefix . '-') === 0) {
            $remote_filename = $name;
            $remote_hash     = $sha;
            break;
        }
    }

    // Handle case where remote file is not found for a prefix
    if (! $remote_filename) {
        logmsg("No remote file found for prefix '{$prefix}'. Skipping.");
        continue;
    }

    // Find the corresponding local file info from the manifest.
    $local_filename = isset($local_manifest[$prefix]['filename']) ? $local_manifest[$prefix]['filename'] : null;
    $local_hash     = isset($local_manifest[$prefix]['hash']) ? $local_manifest[$prefix]['hash'] : null;

    // Determine if an update is needed (new file name or different hash).
    if ($remote_filename && ($remote_filename !== $local_filename || $remote_hash !== $local_hash)) {
        if ($local_filename) {
            logmsg("Updating '{$prefix}': remote file '{$remote_filename}' is different from local '{$local_filename}'.");
        } else {
            logmsg("New file for '{$prefix}': found '{$remote_filename}' on remote.");
        }

        // Dynamically set the destination path based on the new filename.
        $destination_path = $datas_dir . $remote_filename;

        if (download_file($github_raw_url . $remote_filename, $destination_path)) {
            try {
                if ($info['type'] === 'tar') {
                    if (! is_dir($info['extract_dir'])) {
                        mkdir($info['extract_dir'], 0755, true);
                    }

                    logmsg("Extracting {$remote_filename} to {$info['extract_dir']}");

                    // Check if tarball has a single root directory
                    $tar_contents     = shell_exec(sprintf("tar -tf %s", escapeshellarg($destination_path)));
                    $lines            = explode("\n", trim($tar_contents));
                    $first_entry      = $lines[0];
                    $strip_components = (strpos($first_entry, '/') !== false && count(array_unique(array_map(fn($l) => explode('/', $l, 2)[0], $lines))) === 1);

                    $strip_option = $strip_components ? "--strip-components=1" : "";
                    logmsg("Using tar with strip_option: '{$strip_option}'");
                    $command = sprintf("tar -xzf %s -C %s %s", escapeshellarg($destination_path), escapeshellarg($info['extract_dir']), $strip_option);
                    $output  = shell_exec($command . ' 2>&1');
                    logmsg("tar output: " . ($output ?: 'No output'));

                } elseif ($info['type'] === 'gz') {
                    logmsg("Decompressing {$remote_filename} to {$info['extract_dir']} using gunzip...");
                    // Use system gunzip command for decompression
                    $command = sprintf("gunzip -c %s > %s", escapeshellarg($destination_path), escapeshellarg($info['extract_dir']));
                    $output  = shell_exec($command . ' 2>&1');
                    logmsg("gunzip output: " . ($output ?: 'No output'));

                    $package_list_updated = true; // Mark that PACKAGES.TXT was updated
                }
                // unlink($destination_path); // Do not clean up downloaded archive as requested.

                // If a new version of the file was downloaded, remove the old local archive.
                if ($local_filename && $local_filename !== $remote_filename && file_exists($datas_dir . $local_filename)) {
                    unlink($datas_dir . $local_filename);
                }
                logmsg("Successfully processed {$remote_filename}.");
                // Update local manifest with new file info.
                $local_manifest[$prefix] = ['filename' => $remote_filename, 'hash' => $remote_hash];
            } catch (Exception $e) {
                logmsg("Error processing {$remote_filename}: " . $e->getMessage());
            }
        }
    } else {
        if ($remote_filename) {
            logmsg("File for prefix '{$prefix}' ('{$remote_filename}') is up to date. Skipping.");
        } else {
            // This case is now handled at the top of the loop.
        }
        // If pkg-1.0.gz is up to date, but PACKAGES.TXT doesn't exist, we should still trigger a rebuild.
        if ($prefix === 'pkg' && $remote_filename && ! file_exists($packages_file)) {
            logmsg("PACKAGES.TXT missing, forcing a rebuild check.");
            $package_list_updated = true;
        }
    }
}

// Save the updated manifest
file_put_contents($manifest_file, json_encode($local_manifest, JSON_PRETTY_PRINT));

// --- REBUILD CACHE FROM PACKAGES.TXT ---

// Check if the packages file has changed since the last cache build.
// The cache should only be rebuilt if the package list was newly downloaded/decompressed.
if ($package_list_updated) {
    logmsg('rebuilding cache');
                                       // Load all available icon filenames.
    $icon_cache_path = 'cache/icons/'; // Relative path for the cache file
    $icons           = [];
    foreach (glob($icon_dir . '/*.svg') as $f) {
        $icons[strtolower(basename($f, '.svg'))] = $f;
    }

    // Read the uncompressed packages file.
    $handle = @fopen($packages_file, "r");
    if ($handle) {
        $skip             = true;
        $pkg              = [];
        $description_mode = false;

        while (($line = fgets($handle)) !== false) {
            $line = trim($line);

            // Skip header lines until the first "PACKAGE NAME:" is found.
            if ($skip) {if (strpos($line, 'PACKAGE NAME:') === 0) {
                $skip = false;
            } else {
                continue;
            }}

            if ($line === '' || $description_mode) {
                if ($line === '') {
                    $description_mode = false;
                }
                if ($description_mode && strpos($line, ':') !== false) {
                    $pkg['desc']      = trim(substr($line, strpos($line, ':') + 1));
                    $description_mode = false; // Description found, turn off mode
                }
                continue;
            }

            // Parse each package entry.
            if (strpos($line, 'PACKAGE NAME:') === 0) {
                // If we are starting a new package, process and save the previous one first.
                if (! empty($pkg) && ! empty($pkg['name'])) {
                    $baseName = strtolower($pkg['name']);
                    $iconPath = null;

                    if (isset($icons[$baseName])) {
                        $iconPath = $icon_cache_path . basename($icons[$baseName]);
                    } else {
                        foreach ($icons as $iconName => $filePath) {
                            $found = false;
                            if (strlen($iconName) < 3) {
                                if (strpos($baseName, $iconName) === 0 || strpos($iconName, $baseName) !== false) {
                                    $found = true;
                                }
                            } else {
                                if (strpos($baseName, $iconName) !== false || strpos($iconName, $baseName) !== false) {
                                    $found = true;
                                }
                            }

                            if ($found) {
                                $iconPath = $icon_cache_path . basename($filePath);
                                break;
                            }
                        }
                    }

                    $pkg['icon']      = $iconPath ?: $icon_cache_path . 'terminal.svg';
                    $products_cache[] = $pkg;
                }

                // Now, reset for the new package.
                $pkg         = ["name" => "", "category" => "", "version" => "", "desc" => "", "sizec" => "", "sizeu" => "", "req" => "", "icon" => ""];
                $pkg['full'] = trim(substr($line, 14));
                if (preg_match('/-([0-9][^-]*)-/', $pkg['full'], $v)) {
                    $pkg['version'] = $v[1];
                }
            } elseif (strpos($line, 'PACKAGE LOCATION:') === 0) {
                $loc = trim(substr($line, 18));
                if (preg_match('#\\/([^/]+)/([^/]+)$#', $loc, $m)) {$pkg['category'] = ucfirst(strtolower($m[1]));
                    $pkg['name']                                = $m[2];}
            } elseif (strpos($line, 'PACKAGE SIZE (compressed):') === 0) {
                $pkg['sizec'] = trim(substr($line, 28));
            } elseif (strpos($line, 'PACKAGE SIZE (uncompressed):') === 0) {
                $pkg['sizeu'] = trim(substr($line, 30));
            } elseif (strpos($line, 'PACKAGE REQUIRED:') === 0) {
                $pkg['req'] = trim(substr($line, 18));
            } elseif (strpos($line, 'PACKAGE DESCRIPTION:') === 0) {
                $description_mode = true;
            }
        }
        fclose($handle);

        // Process the very last package in the file, which might not have been added yet.
        if (! empty($pkg) && ! empty($pkg['name'])) {
            $baseName = strtolower($pkg['name']);
            $iconPath = null;

            if (isset($icons[$baseName])) {
                $iconPath = $icon_cache_path . basename($icons[$baseName]);
            } else {
                foreach ($icons as $iconName => $filePath) {
                    $found = false;
                    if (strlen($iconName) < 3) {
                        if (strpos($baseName, $iconName) === 0 || strpos($iconName, $baseName) !== false) {
                            $found = true;
                        }
                    } else {
                        if (strpos($baseName, $iconName) !== false || strpos($iconName, $baseName) !== false) {
                            $found = true;
                        }
                    }

                    if ($found) {
                        $iconPath = $icon_cache_path . basename($filePath);
                        break;
                    }
                }
            }

            $pkg['icon']      = $iconPath ?: $icon_cache_path . 'terminal.svg';
            $products_cache[] = $pkg;
        }

        // Write the new cache to a PHP file.
        if (! is_dir(dirname($cache_file))) {
            mkdir(dirname($cache_file), 0755, true);
        }

        file_put_contents($cache_file, '<?php $products_cache=' . var_export($products_cache, true) . ';');
        logmsg('cache updated');
    } else {
        logmsg("Error opening {$packages_file} for reading.");
    }
}

// --- SCRIPT COMPLETION ---

logmsg('end');
// Redirect back to the main page.
echo '<script>window.top.location.href = "/../index.php";</script>';
