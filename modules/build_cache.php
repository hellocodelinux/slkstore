<?php
/**
 * Package Cache Builder
 *
 * This script manages the package cache system for SlkStore. It handles:
 * - Synchronization with remote package repositories
 * - Package metadata parsing and processing
 * - Icon management and association
 * - Cache file generation and maintenance
 * - Update checking and manifest management
 */

// Initialize output buffering and extend execution time limit
ob_start();
set_time_limit(900); // Set 15-minute timeout for large repository updates

// Define paths for cache storage and data management
// These paths are used for storing downloaded files, icons, and processed data
$cache_file       = $_SERVER['DOCUMENT_ROOT'] . '/cache/packages.php';
$datas_dir        = $_SERVER['DOCUMENT_ROOT'] . '/datas/';
$icon_dir         = $_SERVER['DOCUMENT_ROOT'] . '/cache/icons';
$packages_file    = $_SERVER['DOCUMENT_ROOT'] . '/cache/PACKAGES.TXT';
$packages_gz_file = $_SERVER['DOCUMENT_ROOT'] . '/datas/PACKAGES.TXT.gz';
$logstore         = $_SERVER['DOCUMENT_ROOT'] . '/tmp/logstore.txt';
$manifest_file    = $_SERVER['DOCUMENT_ROOT'] . '/cache/manifest.json';
$products_cache   = [];

/**
 * Log a message with timestamp to the log file
 *
 * This function provides a consistent logging mechanism for tracking
 * cache building operations and debugging issues.
 *
 * @param string $m The message to log
 */
function logmsg($m)
{
    global $logstore;
    file_put_contents($logstore, date('[Y-m-d H:i:s] ') . $m . "\n", FILE_APPEND);
}

// Mark the beginning of cache building process
logmsg('start');

// Define all directories needed for cache operation
$needed_dirs = [
    dirname($logstore),                   // Log directory
    $icon_dir,                            // Icon storage
    $datas_dir,                           // Downloaded data storage
    $_SERVER['DOCUMENT_ROOT'] . '/cache', // Main cache directory
];

// Create any missing directories with appropriate permissions
// Uses recursive creation (mkdir -p) and 0755 permissions for security
foreach ($needed_dirs as $d) {
    if (! is_dir($d)) {
        mkdir($d, 0755, true);
    }
}

$local_manifest       = file_exists($manifest_file) ? json_decode(file_get_contents($manifest_file), true) : [];
$package_list_updated = false; // Track if any package or icon data was updated

$remote_packages_url = 'rsync://slackware.uk/slackdce/packages/15.0/x86_64/PACKAGES.TXT.gz';
// --- SYNC PACKAGES.TXT ---

logmsg("Syncing PACKAGES.TXT.gz from $remote_packages_url");
// Download the latest PACKAGES.TXT.gz using rsync
exec(sprintf('rsync -avz %s %s', escapeshellarg($remote_packages_url), escapeshellarg($packages_gz_file)), $output, $ret);
logmsg("rsync PACKAGES.TXT.gz return code: $ret");
logmsg("rsync PACKAGES.TXT.gz output:\n" . implode("\n", $output));

$packages_hash = file_exists($packages_gz_file) ? md5_file($packages_gz_file) : '';
// Check if PACKAGES.TXT.gz has changed
if (! isset($local_manifest['packages']) || $local_manifest['packages']['hash'] !== $packages_hash) {
    logmsg("PACKAGES.TXT.gz updated or new.");
    $package_list_updated = true;
    if (file_exists($packages_gz_file)) {
        // Unzip the downloaded package list
        exec("gunzip -c " . escapeshellarg($packages_gz_file) . " > " . escapeshellarg($packages_file), $out, $ret);
        logmsg("Unzipped PACKAGES.TXT.gz to cache/PACKAGES.TXT. Return code: $ret");
    }
    $local_manifest['packages'] = ['filename' => basename($packages_gz_file), 'hash' => $packages_hash];
} else {
    logmsg("PACKAGES.TXT.gz is up to date.");
}

$remote_icons_url = 'rsync://slackware.uk/slackdce/slkstore/ic-1.0.tar.gz';
// --- SYNC ICONS (ic-*.tar.gz) ---

$icon_archive_path = $datas_dir . basename($remote_icons_url);
logmsg("Syncing icons from $remote_icons_url");
// Download the latest icon archive using rsync
exec(sprintf('rsync -avz %s %s', escapeshellarg($remote_icons_url), escapeshellarg($icon_archive_path)), $output_ic, $ret_ic);
logmsg("rsync icons return code: $ret_ic");
logmsg("rsync icons output:\n" . implode("\n", $output_ic));

$icon_hash = file_exists($icon_archive_path) ? md5_file($icon_archive_path) : '';
// Check if icon archive has changed
if (! isset($local_manifest['icons']) || $local_manifest['icons']['hash'] !== $icon_hash) {
    logmsg("Icons archive updated or new.");
    $package_list_updated = true; // Force cache rebuild if icons change
    if (file_exists($icon_archive_path)) {
        if (! is_dir($icon_dir)) {
            mkdir($icon_dir, 0755, true);
        }
        // Remove old icons before extracting new ones
        exec('rm -f ' . escapeshellarg($icon_dir) . '/*.svg', $out_rm, $ret_rm);
        logmsg("Cleaned old icons. Return code: $ret_rm");

        // Extract new icons from the archive
        exec("tar -xzf " . escapeshellarg($icon_archive_path) . " -C " . escapeshellarg($icon_dir) . " --strip-components=1", $out_tar, $ret_tar);
        logmsg("Extracted icons. Return code: $ret_tar");
    }
    $local_manifest['icons'] = ['filename' => basename($icon_archive_path), 'hash' => $icon_hash];
} else {
    logmsg("Icons are up to date.");
}

$remote_flathub_url = 'https://dl.flathub.org/repo/appstream/x86_64/appstream.xml.gz';
// --- SYNC FLATHUB APPSTREAM ---

$flathub_gz_path  = $datas_dir . 'flathub-appstream.xml.gz';
$flathub_xml_path = $_SERVER['DOCUMENT_ROOT'] . '/cache/flathub-appstream.xml';

logmsg("Syncing Flathub appstream from $remote_flathub_url");
// Download the latest Flathub appstream using wget
exec(sprintf('wget -q -O %s %s', escapeshellarg($flathub_gz_path), escapeshellarg($remote_flathub_url)), $output_fh, $ret_fh);
logmsg("wget Flathub appstream return code: $ret_fh");

$flathub_hash = file_exists($flathub_gz_path) ? md5_file($flathub_gz_path) : '';
// Check if Flathub appstream has changed
if (! isset($local_manifest['flathub']) || $local_manifest['flathub']['hash'] !== $flathub_hash) {
    logmsg("Flathub appstream updated or new.");
    $package_list_updated = true; // Force cache rebuild if flathub appstream changes
    if (file_exists($flathub_gz_path)) {
        // Unzip the downloaded Flathub appstream
        exec("gunzip -c " . escapeshellarg($flathub_gz_path) . " > " . escapeshellarg($flathub_xml_path), $out_fh_unzip, $ret_fh_unzip);
        logmsg("Unzipped Flathub appstream to cache. Return code: $ret_fh_unzip");
    }
    $local_manifest['flathub'] = ['filename' => basename($flathub_gz_path), 'hash' => $flathub_hash];
} else {
    logmsg("Flathub appstream is up to date.");
}

// --- SAVE MANIFEST ---
// Save the updated manifest file
file_put_contents($manifest_file, json_encode($local_manifest, JSON_PRETTY_PRINT));

// --- REBUILD CACHE ---
if ($package_list_updated) {
    logmsg('rebuilding cache');
    $icon_cache_path = 'cache/icons/';
    $icons           = [];
    // Create an association map between package names and their icons
    // Keys are lowercase package names, values are paths to corresponding SVG files
    // This allows for efficient icon lookup during package processing
    foreach (glob($icon_dir . '/*.svg') as $f) {
        $icons[strtolower(basename($f, '.svg'))] = $f;
    }

    // Open the package list file for processing
    // Use error suppression (@) as we'll handle any errors explicitly
    $handle = @fopen($packages_file, "r");
    if ($handle) {
        $skip                      = true;
        $pkg                       = [];
        $description_mode          = false;
        $current_description_lines = []; // Temporary storage for all description lines

        while (($line = fgets($handle)) !== false) {
            $line = trim($line);
            if ($skip) {
                // Wait for the first package entry
                if (strpos($line, 'PACKAGE NAME:') === 0) {
                    $skip = false;
                } else {
                    continue;
                }
            }

            // Determine if the current line starts a new package field section
            // This check helps identify when we've reached the end of a description block
            // and need to process the collected description lines
            $is_package_field_header = (
                strpos($line, 'PACKAGE NAME:') === 0 ||                // Package identifier
                strpos($line, 'PACKAGE LOCATION:') === 0 ||            // Package directory location
                strpos($line, 'PACKAGE SIZE (compressed):') === 0 ||   // Compressed package size
                strpos($line, 'PACKAGE SIZE (uncompressed):') === 0 || // Installed size
                strpos($line, 'PACKAGE REQUIRED:') === 0 ||            // Dependencies
                strpos($line, 'PACKAGE DESCRIPTION:') === 0            // Package description
            );

            if ($description_mode && ($is_package_field_header || $line === '')) {
                // Description block has ended. Process collected lines.
                $description_mode      = false;
                $pkg['desc']           = '';
                $pkg['descfull']       = [];
                $first_desc_line_found = false;
                foreach ($current_description_lines as $d_line) {
                    if (! $first_desc_line_found && trim($d_line) !== '') {
                        $pkg['desc']           = trim($d_line);
                        $first_desc_line_found = true;
                    } elseif ($first_desc_line_found) {
                        $pkg['descfull'][] = $d_line; // Add all subsequent lines, including empty ones
                    }
                }
                // Remove empty lines at the start and end of descfull
                while (count($pkg['descfull']) > 0 && trim(reset($pkg['descfull'])) === '') {array_shift($pkg['descfull']);}
                while (count($pkg['descfull']) > 0 && trim(end($pkg['descfull'])) === '') {array_pop($pkg['descfull']);}
                $pkg['descfull']           = implode("\n", $pkg['descfull']);
                $current_description_lines = []; // Reset for next description
            }

            if (strpos($line, 'PACKAGE NAME:') === 0) {
                if (! empty($pkg) && ! empty($pkg['name'])) {
                    // The description for this package should have been processed above
                    $baseName = strtolower($pkg['name']);
                    $iconPath = null;
                    if (isset($icons[$baseName])) {
                        $iconPath = $icon_cache_path . basename($icons[$baseName]);
                    } else {
                        foreach ($icons as $iconName => $filePath) {
                            if (strpos($baseName, $iconName) !== false || strpos($iconName, $baseName) !== false) {
                                $iconPath = $icon_cache_path . basename($filePath);
                                break;
                            }
                        }
                    }

                    $pkg['icon']      = $iconPath ?: $icon_cache_path . 'terminal.svg';
                    $products_cache[] = $pkg;
                }
                // Initialize new package, including descfull
                $pkg         = ["name" => "", "category" => "", "version" => "", "desc" => "", "descfull" => "", "sizec" => "", "sizeu" => "", "req" => "", "icon" => ""];
                $pkg['full'] = trim(substr($line, 14));
                if (preg_match('/-([0-9][^-]*)-/', $pkg['full'], $v)) {
                    $pkg['version'] = $v[1];
                }

            } elseif (strpos($line, 'PACKAGE LOCATION:') === 0) {
                $loc = trim(substr($line, 18));
                if (preg_match('#\/([^/]+)/([^/]+)$#', $loc, $m)) {
                    $pkg['category'] = ucfirst(strtolower($m[1]));
                    $pkg['name']     = $m[2];
                }
            } elseif (strpos($line, 'PACKAGE SIZE (compressed):') === 0) {
                $pkg['sizec'] = trim(substr($line, 28));
            } elseif (strpos($line, 'PACKAGE SIZE (uncompressed):') === 0) {
                $pkg['sizeu'] = trim(substr($line, 30));
            } elseif (strpos($line, 'PACKAGE REQUIRED:') === 0) {
                $pkg['req'] = trim(substr($line, 18));
            } elseif (strpos($line, 'PACKAGE DESCRIPTION:') === 0) {
                $description_mode          = true;
                $current_description_lines = []; // Clear for new description
            }
            // Collect description lines if in description mode
            elseif ($description_mode) {
                $clean_line = $line;
                if (! empty($pkg['name'])) {
                    $prefix = strtolower($pkg['name']) . ':';
                    if (strpos(strtolower($line), $prefix) === 0) {
                        $clean_line = ltrim(substr($line, strlen($prefix)));
                    }
                }
                $current_description_lines[] = $clean_line; // Add without trim to preserve internal spacing
            }
        }
        fclose($handle);

        // After the loop, process the description for the last package if description_mode was still active
        if ($description_mode) {
            $pkg['desc']           = '';
            $pkg['descfull']       = [];
            $first_desc_line_found = false;
            foreach ($current_description_lines as $d_line) {
                if (! $first_desc_line_found && trim($d_line) !== '') {
                    $pkg['desc']           = trim($d_line);
                    $first_desc_line_found = true;
                } elseif ($first_desc_line_found) {
                    $pkg['descfull'][] = $d_line;
                }
            }
            while (count($pkg['descfull']) > 0 && trim(reset($pkg['descfull'])) === '') {array_shift($pkg['descfull']);}
            while (count($pkg['descfull']) > 0 && trim(end($pkg['descfull'])) === '') {array_pop($pkg['descfull']);}
            $pkg['descfull'] = implode("\n", $pkg['descfull']);
        }

        // Finalize and add the last package to the cache
        if (! empty($pkg) && ! empty($pkg['name'])) {
            // The description for this package should have been processed above
            $baseName = strtolower($pkg['name']);
            $iconPath = null;
            if (isset($icons[$baseName])) {
                $iconPath = $icon_cache_path . basename($icons[$baseName]);
            } else {
                foreach ($icons as $iconName => $filePath) {
                    if (strpos($baseName, $iconName) !== false || strpos($iconName, $baseName) !== false) {
                        $iconPath = $icon_cache_path . basename($filePath);
                        break;
                    }
                }
            }

            $pkg['icon']      = $iconPath ?: $icon_cache_path . 'terminal.svg';
            $products_cache[] = $pkg;
        }

        // Ensure the cache directory exists
        if (! is_dir(dirname($cache_file))) {
            mkdir(dirname($cache_file), 0755, true);
        }

        // Save the cache file with all products
        file_put_contents($cache_file, '<?php $products_cache=' . var_export($products_cache, true) . ';');
        logmsg('cache updated');
    } else {
        logmsg("Error opening {$packages_file} for reading.");
    }
}

logmsg('end'); // Log the end of the script

// Update the daily check file to prevent re-checking on the same day
$check_file = $_SERVER['DOCUMENT_ROOT'] . '/tmp/slackdce_update_check.txt';
$today      = date('Y-m-d');
file_put_contents($check_file, $today);
logmsg("Updated daily check file: {$check_file}");
