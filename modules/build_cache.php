<?php
ob_start();
set_time_limit(900); // Increased time limit for robustness

$cache_file       =  $_SERVER['DOCUMENT_ROOT'] . '/cache/packages.php';
$datas_dir        = $_SERVER['DOCUMENT_ROOT'] . '/datas/';
$icon_dir         = $_SERVER['DOCUMENT_ROOT'] . '/cache/icons';
$packages_file    = $_SERVER['DOCUMENT_ROOT'] . '/cache/PACKAGES.TXT';
$packages_gz_file = $_SERVER['DOCUMENT_ROOT']  . '/datas/PACKAGES.TXT.gz';
$logstore         = $_SERVER['DOCUMENT_ROOT'] . '/tmp/logstore.txt';
$manifest_file    = $_SERVER['DOCUMENT_ROOT'] . '/cache/manifest.json';
$products_cache   = [];

function logmsg($m)
{
    global $logstore;
    file_put_contents($logstore, date('[Y-m-d H:i:s] ') . $m . "\n", FILE_APPEND);
}

logmsg('start');

foreach ([dirname($logstore), $icon_dir, $datas_dir, $_SERVER['DOCUMENT_ROOT']  . '/cache'] as $d) {
    if (! is_dir($d)) {
        mkdir($d, 0755, true);
    }

}
$local_manifest       = file_exists($manifest_file) ? json_decode(file_get_contents($manifest_file), true) : [];
$package_list_updated = false;

// --- RSYNC PACKAGES.TXT ---
$remote_packages_url = 'rsync://slackware.uk/slackdce/packages/15.0/x86_64/PACKAGES.TXT.gz';
logmsg("Syncing PACKAGES.TXT.gz from $remote_packages_url");
exec(sprintf('rsync -avz %s %s', escapeshellarg($remote_packages_url), escapeshellarg($packages_gz_file)), $output, $ret);
logmsg("rsync PACKAGES.TXT.gz return code: $ret");
logmsg("rsync PACKAGES.TXT.gz output:\n" . implode("\n", $output));

$packages_hash = file_exists($packages_gz_file) ? md5_file($packages_gz_file) : '';
if (! isset($local_manifest['packages']) || $local_manifest['packages']['hash'] !== $packages_hash) {
    logmsg("PACKAGES.TXT.gz updated or new.");
    $package_list_updated = true;
    if (file_exists($packages_gz_file)) {
        exec("gunzip -c " . escapeshellarg($packages_gz_file) . " > " . escapeshellarg($packages_file), $out, $ret);
        logmsg("Unzipped PACKAGES.TXT.gz to cache/PACKAGES.TXT. Return code: $ret");
    }
    $local_manifest['packages'] = ['filename' => basename($packages_gz_file), 'hash' => $packages_hash];
} else {
    logmsg("PACKAGES.TXT.gz is up to date.");
}

// --- RSYNC ICONS (ic-*.tar.gz) ---
$remote_icons_url  = 'rsync://slackware.uk/slackdce/slkstore/ic-1.0.tar.gz';
$icon_archive_path = $datas_dir . basename($remote_icons_url);
logmsg("Syncing icons from $remote_icons_url");
exec(sprintf('rsync -avz %s %s', escapeshellarg($remote_icons_url), escapeshellarg($icon_archive_path)), $output_ic, $ret_ic);
logmsg("rsync icons return code: $ret_ic");
logmsg("rsync icons output:\n" . implode("\n", $output_ic));

$icon_hash = file_exists($icon_archive_path) ? md5_file($icon_archive_path) : '';
if (! isset($local_manifest['icons']) || $local_manifest['icons']['hash'] !== $icon_hash) {
    logmsg("Icons archive updated or new.");
    $package_list_updated = true; // Force cache rebuild if icons change
    if (file_exists($icon_archive_path)) {
        if (! is_dir($icon_dir)) {
            mkdir($icon_dir, 0755, true);
        }
        // Clean old icons before extracting new ones
        exec('rm -f ' . escapeshellarg($icon_dir) . '/*.svg', $out_rm, $ret_rm);
        logmsg("Cleaned old icons. Return code: $ret_rm");

        exec("tar -xzf " . escapeshellarg($icon_archive_path) . " -C " . escapeshellarg($icon_dir) . " --strip-components=1", $out_tar, $ret_tar);
        logmsg("Extracted icons. Return code: $ret_tar");
    }
    $local_manifest['icons'] = ['filename' => basename($icon_archive_path), 'hash' => $icon_hash];
} else {
    logmsg("Icons are up to date.");
}

// --- SYNC FLATHUB APPSTREAM ---
$remote_flathub_url = 'https://dl.flathub.org/repo/appstream/x86_64/appstream.xml.gz';
$flathub_gz_path    = $datas_dir . 'flathub-appstream.xml.gz';
$flathub_xml_path   = $_SERVER['DOCUMENT_ROOT']  . '/cache/flathub-appstream.xml';

logmsg("Syncing Flathub appstream from $remote_flathub_url");
// Use wget for https URL
exec(sprintf('wget -q -O %s %s', escapeshellarg($flathub_gz_path), escapeshellarg($remote_flathub_url)), $output_fh, $ret_fh);
logmsg("wget Flathub appstream return code: $ret_fh");

$flathub_hash = file_exists($flathub_gz_path) ? md5_file($flathub_gz_path) : '';
if (! isset($local_manifest['flathub']) || $local_manifest['flathub']['hash'] !== $flathub_hash) {
    logmsg("Flathub appstream updated or new.");
    $package_list_updated = true; // Force cache rebuild if flathub appstream changes
    if (file_exists($flathub_gz_path)) {
        exec("gunzip -c " . escapeshellarg($flathub_gz_path) . " > " . escapeshellarg($flathub_xml_path), $out_fh_unzip, $ret_fh_unzip);
        logmsg("Unzipped Flathub appstream to cache. Return code: $ret_fh_unzip");
    }
    $local_manifest['flathub'] = ['filename' => basename($flathub_gz_path), 'hash' => $flathub_hash];
} else {
    logmsg("Flathub appstream is up to date.");
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
        $skip                      = true;
        $pkg                       = [];
        $description_mode          = false;
        $current_description_lines = []; // Almacenamiento temporal para todas las líneas de descripción

        while (($line = fgets($handle)) !== false) {
            $line = trim($line);
            if ($skip) {if (strpos($line, 'PACKAGE NAME:') === 0) {$skip = false;} else {continue;}} // Wait for first package

            // Verificar si el modo de descripción debe terminar y procesar las líneas recolectadas
            $is_package_field_header = (
                strpos($line, 'PACKAGE NAME:') === 0 ||
                strpos($line, 'PACKAGE LOCATION:') === 0 ||
                strpos($line, 'PACKAGE SIZE (compressed):') === 0 ||
                strpos($line, 'PACKAGE SIZE (uncompressed):') === 0 ||
                strpos($line, 'PACKAGE REQUIRED:') === 0 ||
                strpos($line, 'PACKAGE DESCRIPTION:') === 0
            );

            if ($description_mode && ($is_package_field_header || $line === '')) {
                                                // El bloque de descripción ha terminado. Procesar las líneas recolectadas.
                $description_mode      = false; // Salir del modo de descripción
                $pkg['desc']           = '';
                $pkg['descfull']       = [];
                $first_desc_line_found = false;
                foreach ($current_description_lines as $d_line) {
                    if (! $first_desc_line_found && trim($d_line) !== '') {
                        $pkg['desc']           = trim($d_line);
                        $first_desc_line_found = true;
                    } elseif ($first_desc_line_found) {
                        $pkg['descfull'][] = $d_line; // Añadir todas las líneas subsiguientes, incluyendo las vacías
                    }
                }
                // Eliminar líneas vacías al principio y al final del array descfull
                while (count($pkg['descfull']) > 0 && trim(reset($pkg['descfull'])) === '') {array_shift($pkg['descfull']);}
                while (count($pkg['descfull']) > 0 && trim(end($pkg['descfull'])) === '') {array_pop($pkg['descfull']);}
                $pkg['descfull']           = implode("\n", $pkg['descfull']);
                $current_description_lines = []; // Reiniciar para la siguiente descripción
            }

            if (strpos($line, 'PACKAGE NAME:') === 0) {
                if (! empty($pkg) && ! empty($pkg['name'])) {
                    // La descripción para este paquete ya debería haber sido procesada arriba
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
                // Inicializar nuevo paquete, incluyendo descfull
                $pkg         = ["name" => "", "category" => "", "version" => "", "desc" => "", "descfull" => "", "sizec" => "", "sizeu" => "", "req" => "", "icon" => ""];
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
                $description_mode          = true;
                $current_description_lines = []; // Limpiar para la nueva descripción
            }
            // Recolectar líneas de descripción si estamos en modo de descripción
            elseif ($description_mode) {
                $clean_line = $line;
                if (! empty($pkg['name'])) {
                    $prefix = strtolower($pkg['name']) . ':';
                    if (strpos(strtolower($line), $prefix) === 0) {
                        $clean_line = ltrim(substr($line, strlen($prefix)));
                    }
                }
                $current_description_lines[] = $clean_line; // Añadir sin trim para preservar el espaciado interno
            }
        }
        fclose($handle);

        // Después del bucle, procesar la descripción para el último paquete si description_mode seguía activo
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

        // Finalizar y añadir el último paquete a la caché
        if (! empty($pkg) && ! empty($pkg['name'])) {
            // La descripción para este paquete ya debería haber sido procesada arriba
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

// Update the daily check file to prevent re-checking on the same day.
$check_file = $_SERVER['DOCUMENT_ROOT'] . '/tmp/slackdce_update_check.txt';
$today      = date('Y-m-d');
file_put_contents($check_file, $today);
logmsg("Updated daily check file: {$check_file}");
