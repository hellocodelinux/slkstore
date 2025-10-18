<?php
// Start output buffering to prevent any premature output.
ob_start();

// --- CONFIGURATION AND INITIALIZATION ---

// Define paths for cache files, directories, and remote URLs.
$cache_file      = __DIR__ . '/../cache/packages.php';                                   // Path to the generated PHP cache file.
$hash_file       = __DIR__ . '/../cache/packages.hash';                                  // Path to store the hash of PACKAGES.TXT.gz.
$icon_dir        = __DIR__ . '/../icons/';                                               // Directory containing package icons.
$packages_url    = 'https://slackware.uk/slackdce/packages/15.0/x86_64/PACKAGES.TXT.gz'; // URL for the Slackware packages list.
$packages_file   = __DIR__ . '/../cache/PACKAGES.TXT.gz';                                // Local path to store the downloaded packages list.
$slackbuilds_tar = __DIR__ . '/../cache/slackbuilds.tar.gz';                             // Path to the SlackBuilds tarball.
$slackbuilds_dir = __DIR__ . '/../cache/slackbuilds';                                    // Directory to extract SlackBuilds into.
$tag_file        = __DIR__ . '/../cache/slackbuilds.tag';                                // File to store the latest SlackBuilds tag.
$logstore        = __DIR__ . '/../tmp/logstore.txt';                                     // Path to the log file for this script.
$products_cache  = [];                                                                   // Initialize an array to hold package data.

/**
 * Appends a log message with a timestamp to the log file.
 * @param string $m The message to log.
 */
function logmsg($m)
{
    global $logstore;
    file_put_contents($logstore, date('[Y-m-d H:i:s] ') . $m . "\n", FILE_APPEND);
}

/**
 * Fetches content from a URL. Uses cURL if available, otherwise falls back to file_get_contents.
 * @param string $url The URL to fetch.
 * @return array An array containing the HTTP status code and the content.
 */
function fetch_url($url)
{
    if (function_exists('curl_version')) {
        // Use cURL for fetching.
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_USERAGENT, 'SlackBuildsChecker/1.0');
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        $content = curl_exec($ch);
        $code    = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        return ['code' => $code, 'content' => $content];
    } else {
        // Fallback to file_get_contents if cURL is not available.
        $opts    = ['http' => ['method' => 'GET', 'header' => "User-Agent: SlackBuildsChecker/1.0\r\nAccept: application/vnd.github.v3+json\r\n"], 'ssl' => ['verify_peer' => true]];
        $ctx     = stream_context_create($opts);
        $content = @file_get_contents($url, false, $ctx);
        $code    = 0;
        if (isset($http_response_header)) {
            foreach ($http_response_header as $h) {
                if (preg_match('#HTTP/\d+\.\d+\s+(\d+)#', $h, $m)) {$code = (int) $m[1];
                    break;}
            }
        }
        return ['code' => $code, 'content' => $content];
    }
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

// --- DOWNLOAD OFFICIAL SLACKWARE PACKAGES LIST (PACKAGES.TXT.gz) ---

// Download the file if it doesn't exist or is older than 24 hours (86400 seconds).
if (! file_exists($packages_file) || time() - filemtime($packages_file) > 86400) {
    $r = fetch_url($packages_url);
    if ($r['code'] === 200 && $r['content'] !== '') {
        file_put_contents($packages_file, $r['content']);
        logmsg('downloaded PACKAGES.TXT.gz');
    } else {
        logmsg('failed PACKAGES.TXT.gz code=' . $r['code']);
    }

}

// --- DOWNLOAD AND EXTRACT SLACKBUILDS.ORG REPOSITORY ---

// Fetch the latest tags from the SlackBuilds.org GitHub repository.
$r = fetch_url('https://api.github.com/repos/SlackBuildsOrg/slackbuilds/tags');
if ($r['code'] === 200 && $r['content'] !== '') {
    $tags       = json_decode($r['content'], true);
    $latest_tag = '';
    // Find the latest valid version tag from the fetched tags.
    foreach ($tags as $t) {
        if (preg_match('/^\d+\.\d+\-\d{8}\.\d+$/', $t['name'])) {
            $latest_tag = $t['name'];
            break;
        }
    }
    if ($latest_tag !== '') {
        // Get the currently stored tag.
        $current_tag = file_exists($tag_file) ? trim(file_get_contents($tag_file)) : '';
        // Determine if a new download is needed.
        $download_new = ! file_exists($slackbuilds_tar) || $current_tag !== $latest_tag;

        if ($download_new) {
            // Download the new tarball if a new version is found.
            $tar_url = "https://github.com/SlackBuildsOrg/slackbuilds/archive/refs/tags/$latest_tag.tar.gz";
            $t       = fetch_url($tar_url);
            if ($t['code'] === 200 && strlen($t['content']) > 100000) {
                file_put_contents($slackbuilds_tar, $t['content']);
                file_put_contents($tag_file, $latest_tag);
                logmsg("downloaded new slackbuilds $latest_tag");

                // Clean up old directory and extract the new tarball.
                if (is_dir($slackbuilds_dir)) {
                    shell_exec("rm -rf " . escapeshellarg($slackbuilds_dir));
                }

                mkdir($slackbuilds_dir, 0755, true);
                shell_exec("tar -xzf " . escapeshellarg($slackbuilds_tar) . " -C " . escapeshellarg($slackbuilds_dir) . " --strip-components=1");
                logmsg("extracted slackbuilds to cache/slackbuilds");
            } else {
                logmsg("failed download tar code=" . $t['code']);
            }

        } else if (! is_dir($slackbuilds_dir) || count(scandir($slackbuilds_dir)) <= 2) {
            // Extract the tarball if the directory is missing or empty.
            mkdir($slackbuilds_dir, 0755, true);
            shell_exec("tar -xzf " . escapeshellarg($slackbuilds_tar) . " -C " . escapeshellarg($slackbuilds_dir) . " --strip-components=1");
            logmsg("extracted slackbuilds to cache/slackbuilds (folder missing)");
        } else {
            logmsg("slackbuilds up to date $latest_tag");
        }

    } else {
        logmsg('no valid version tag');
    }

} else {
    logmsg('error fetching tags code=' . $r['code']);
}

// --- REBUILD CACHE FROM PACKAGES.TXT.gz ---

// Check if the packages file has changed since the last cache build.
$current_hash = file_exists($packages_file) ? hash_file('sha256', $packages_file) : '';
$needs_update = true;
if ($current_hash && file_exists($hash_file) && trim(file_get_contents($hash_file)) === $current_hash) {
    $needs_update = false;
    logmsg('no changes detected');
}

// If an update is needed, parse the packages file and build the cache.
if ($needs_update && $current_hash) {
    logmsg('rebuilding cache');
    // Load all available icon filenames.
    $icons = [];
    foreach (glob($icon_dir . '*.svg') as $f) {
        $icons[strtolower(basename($f, '.svg'))] = $f;
    }

    // Read the gzipped packages file.
    $lines = @gzfile($packages_file);
    if ($lines !== false) {
        $skip = true;
        // Iterate through each line of the packages file.
        foreach ($lines as $i => $line) {
            $line = trim($line);
            // Skip header lines until the first "PACKAGE NAME:" is found.
            if ($skip) {if (strpos($line, 'PACKAGE NAME:') === 0) {
                $skip = false;
            } else {
                continue;
            }
            }
            if ($line === '') {
                continue;
            }

            // Parse each package entry.
            if (strpos($line, 'PACKAGE NAME:') === 0) {
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
                // Extract the package description.
                $desc = '';
                for ($j = $i + 1; $j < count($lines); $j++) {
                    $next = trim($lines[$j]);
                    if ($next == '' || strpos($next, 'PACKAGE NAME:') === 0) {
                        break;
                    }

                    if ($next != '' && strpos($next, ':') !== false) {$desc = trim(substr($next, strpos($next, ':') + 1));
                        break;}
                }
                $pkg['desc'] = $desc;

                // Find a matching icon for the package.
                $base = strtolower($pkg['name']);
                $icon = '/../icons/terminal.svg'; // Default icon.
                foreach ($icons as $n => $f) {
                    if ($n == $base || strpos($base, $n) !== false || strpos($n, $base) !== false) {$icon = '/../icons/' . basename($f);
                        break;}
                }

                $pkg['icon'] = $icon;

                // Add the completed package data to the cache array.
                $products_cache[] = $pkg;
            }
        }

        // Write the new cache to a PHP file.
        if (! is_dir(dirname($cache_file))) {
            mkdir(dirname($cache_file), 0755, true);
        }

        file_put_contents($cache_file, '<?php $products_cache=' . var_export($products_cache, true) . ';');
        // Update the hash file with the new hash.
        file_put_contents($hash_file, $current_hash);
        logmsg('cache updated');
    }
}

// --- SCRIPT COMPLETION ---

logmsg('end');
// Redirect back to the main page.
echo '<script>location="/../index.php";</script>';
