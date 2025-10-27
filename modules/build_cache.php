<?php
ob_start();
set_time_limit(600);

$cache_file      = __DIR__ . '/../cache/packages.php';
$hash_file       = __DIR__ . '/../cache/packages.hash';
$datas_dir       = __DIR__ . '/../datas/';
$icon_dir        = __DIR__ . '/../cache/icons';
$packages_file   = __DIR__ . '/../cache/PACKAGES.TXT';
$slackbuilds_dir = __DIR__ . '/../cache/slackbuilds';
$logstore        = __DIR__ . '/../tmp/logstore.txt';
$manifest_file   = __DIR__ . '/../cache/manifest.json';
$products_cache  = [];

function logmsg($m)
{
    global $logstore;
    file_put_contents($logstore, date('[Y-m-d H:i:s] ') . $m . "\n", FILE_APPEND);
}

logmsg('start');

foreach ([dirname($logstore), $icon_dir, $datas_dir, __DIR__ . '/../cache'] as $d) {
    if (! is_dir($d)) {
        mkdir($d, 0755, true);
    }

}

// --- RSYNC DATAS ---
$remote_url = 'rsync://slackware.uk/slackdce/slkstore/';
logmsg("Syncing datas/ with $remote_url");
exec(sprintf('rsync -avz --delete %s %s', escapeshellarg($remote_url), escapeshellarg($datas_dir)), $output, $ret);
logmsg("rsync return code: $ret");
logmsg("rsync output:\n" . implode("\n", $output));

// --- CHECK FILES AND PROCESS ---
$required_files       = ['ic', 'pkg', 'sl'];
$local_manifest       = file_exists($manifest_file) ? json_decode(file_get_contents($manifest_file), true) : [];
$package_list_updated = false;

foreach ($required_files as $prefix) {
    $matches = glob($datas_dir . $prefix . '-*.*');
    if (! $matches) {
        logmsg("Warning: Missing $prefix file after sync, skipping.");
        continue;
    }

    $filename = basename($matches[0]);
    $hash     = md5_file($datas_dir . $filename);

    if (! isset($local_manifest[$prefix]) || $local_manifest[$prefix]['filename'] !== $filename || $local_manifest[$prefix]['hash'] !== $hash) {
        logmsg("File $prefix updated or new: $filename");
        if ($prefix === 'pkg') {
            $package_list_updated = true;
        }

        foreach (glob($datas_dir . $prefix . '-*.*') as $f) {
            if (basename($f) !== $filename) {unlink($f);
                logmsg("Removed old file: " . basename($f));}
        }

        $local_manifest[$prefix] = ['filename' => $filename, 'hash' => $hash];
    } else {
        logmsg("File $prefix up to date: $filename");
    }

    $file_path = $datas_dir . $filename;
    if (! file_exists($file_path)) {logmsg("File $file_path does not exist, skipping extraction");
        continue;}

    if ($prefix === 'pkg') {
        exec("gunzip -c " . escapeshellarg($file_path) . " > " . escapeshellarg($packages_file), $out, $ret);
        $package_list_updated = true;
    } else {
        $dir = ($prefix === 'ic') ? $icon_dir : $slackbuilds_dir;
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        exec("tar -xzf " . escapeshellarg($file_path) . " -C " . escapeshellarg($dir) . " --strip-components=1", $out, $ret);
    }
}

// --- SAVE MANIFEST ---
file_put_contents($manifest_file, json_encode($local_manifest, JSON_PRETTY_PRINT));

// --- REBUILD CACHE ---
if ($package_list_updated) {
    logmsg('rebuilding cache');
    $icon_cache_path = 'cache/icons/';
    $icons           = [];
    foreach (glob($icon_dir . '/*.svg') as $f) {
        $icons[strtolower(basename($f, '.svg'))] = $f;
    }

    $handle = @fopen($packages_file, "r");
    if ($handle) {
        $skip             = true;
        $pkg              = [];
        $description_mode = false;

        while (($line = fgets($handle)) !== false) {
            $line = trim($line);
            if ($skip) {if (strpos($line, 'PACKAGE NAME:') === 0) {$skip = false;} else {continue;}}
            if ($line === '' || $description_mode) {
                if ($line === '') {
                    $description_mode = false;
                }

                if ($description_mode && strpos($line, ':') !== false) {$pkg['desc'] = trim(substr($line, strpos($line, ':') + 1));
                    $description_mode                       = false;}
                continue;
            }

            if (strpos($line, 'PACKAGE NAME:') === 0) {
                if (! empty($pkg) && ! empty($pkg['name'])) {
                    $baseName = strtolower($pkg['name']);
                    $iconPath = null;
                    if (isset($icons[$baseName])) {
                        $iconPath = $icon_cache_path . basename($icons[$baseName]);
                    } else {
                        foreach ($icons as $iconName => $filePath) {if (strpos($baseName, $iconName) !== false || strpos($iconName, $baseName) !== false) {$iconPath = $icon_cache_path . basename($filePath);
                            break;}}
                    }

                    $pkg['icon']      = $iconPath ?: $icon_cache_path . 'terminal.svg';
                    $products_cache[] = $pkg;
                }
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

        if (! empty($pkg) && ! empty($pkg['name'])) {
            $baseName = strtolower($pkg['name']);
            $iconPath = null;
            if (isset($icons[$baseName])) {
                $iconPath = $icon_cache_path . basename($icons[$baseName]);
            } else {
                foreach ($icons as $iconName => $filePath) {if (strpos($baseName, $iconName) !== false || strpos($iconName, $baseName) !== false) {$iconPath = $icon_cache_path . basename($filePath);
                    break;}}
            }

            $pkg['icon']      = $iconPath ?: $icon_cache_path . 'terminal.svg';
            $products_cache[] = $pkg;
        }

        if (! is_dir(dirname($cache_file))) {
            mkdir(dirname($cache_file), 0755, true);
        }

        file_put_contents($cache_file, '<?php $products_cache=' . var_export($products_cache, true) . ';');
        logmsg('cache updated');
    } else {logmsg("Error opening {$packages_file} for reading.");}
}

logmsg('end');
