<?php
/**
 * Package Download and Installation Handler
 *
 * This script manages the download and installation process for SlackDCE packages.
 * It provides:
 * - Asynchronous package downloading
 * - Progress tracking and display
 * - Package installation management
 * - AJAX-based status updates
 */

include $_SERVER['DOCUMENT_ROOT'] . '/modules/preinit.php';

// Define temporary directory for package downloads
$tmp_dir = $_SERVER['DOCUMENT_ROOT'] . '/tmp/';

// Check if this is a regular page load (not an AJAX request)
if (! isset($_POST['ajax'])) {
    // Retrieve and decode the list of packages to download
    // Default to empty array if no packages specified
    $packages_to_download_json = isset($_POST['packages']) ? htmlspecialchars_decode($_POST['packages']) : '[]';
    $packages_to_download      = json_decode($packages_to_download_json, true);

    // Clean up any previous package downloads
    // Remove all .txz files from temporary directory
    shell_exec("rm -f " . $tmp_dir . "*.txz");

    // Initialize the download progress page
    echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Downloading</title>';
    echo '<style>' . $css . '</style></head><body>';
    echo '<h1>Downloading Packages</h1>';
    echo '<div id="progress"><h2>0 / ' . count($packages_to_download) . ' (0%)</h2><div style="width:80%;border:1px solid #ccc;"><div style="width:0%;height:30px;background:#468848;color:#fff;text-align:center;line-height:30px;">0%</div></div></div>';
    echo '<div id="log"></div>';
    @ob_flush();
    @flush();

    echo '<script>
    let packages = ' . json_encode($packages_to_download) . ';
    let total = packages.length;
    let count = 0;

    async function next() {
        if (packages.length === 0) {
            document.getElementById("log").innerHTML = ""; // limpia la pantalla antes de instalar
            document.getElementById("progress").innerHTML = "<h2>Installing Packages...</h2><div style=\'width:80%;border:1px solid #ccc;\'><div id=\'installbar\' style=\'width:0%;height:30px;background:#0066cc;color:#fff;text-align:center;line-height:30px;\'>0%</div></div>";
            startInstall();
            return;
        }
        let pkg = packages.shift();
        let formData = new FormData();
        formData.append("ajax", "1");
        formData.append("package", pkg);
        const r = await fetch("/modules/downloader.php", { method: "POST", body: formData });
        const t = await r.text();
        document.getElementById("log").innerHTML += t;
        count++;
        let pct = Math.round((count / total) * 100);
        document.querySelector("#progress div div").style.width = pct + "%";
        document.querySelector("#progress div div").textContent = pct + "%";
        document.querySelector("#progress h2").textContent = count + " / " + total + " (" + pct + "%)";
        next();
    }

    async function startInstall() {
        const r = await fetch("/modules/downloader.php", {
            method: "POST",
            body: new URLSearchParams({ ajax: "list_installs" })
        });
        const list = await r.json();
        let total = list.length;
        let done = 0;

        async function installNext() {
            if (list.length === 0) {
                document.getElementById("progress").innerHTML += "<h2>Installation Complete!</h2>";
                return;
            }
            let pkg = list.shift();
            const f = await fetch("/modules/downloader.php", {
                method: "POST",
                body: new URLSearchParams({ ajax: "install_one", pkg: pkg })
            });
            const t = await f.text();
            document.getElementById("log").innerHTML += t;
            done++;
            let pct = Math.round((done / total) * 100);
            document.querySelector("#installbar").style.width = pct + "%";
            document.querySelector("#installbar").textContent = pct + "%";
            installNext();
        }
        installNext();
    }

    next();
    </script></body></html>';
    exit;
}

// Handle AJAX request for listing downloaded packages ready for installation
if ($_POST['ajax'] === 'list_installs') {
    // Get all downloaded .txz package files from temporary directory
    $files = glob($tmp_dir . "*.txz");
    // Extract just the filenames without paths
    $names = [];
    foreach ($files as $f) {
        $names[] = basename($f);
    }

    // Return list as JSON response
    header('Content-Type: application/json');
    echo json_encode($names);
    exit;
}

// Handle AJAX request for installing a single package
if ($_POST['ajax'] === 'install_one') {
    // Extract package name and ensure it's safe (no directory traversal)
    $pkg  = basename($_POST['pkg']);
    $file = $tmp_dir . $pkg;
    // Install package using upgradepkg with --install-new option
    // This will either upgrade an existing package or install a new one
    // Redirect stderr to stdout (2>&1) to capture all output
    $out = shell_exec("upgradepkg --install-new " . escapeshellarg($file) . " 2>&1");
    echo "<p style=color:green>Installed {$pkg}</p>";
    exit;
}

include_once $_SERVER['DOCUMENT_ROOT'] . '/cache/packages.php';
$all_packages = $products_cache;
$pkgfull      = $_POST['package'];

/**
 * Search for a package by its full filename in the package list
 *
 * @param string $full     The complete package filename to search for
 * @param array  $packages List of all available packages
 * @return array|null      Package information if found, null otherwise
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

// Look up the package information in the package database
$p = find_package_by_full($pkgfull, $all_packages);
// If the package was found in the database, proceed with download
if ($p) {
                                                                   // Configure download parameters
    $base = 'https://slackware.uk/slackdce/packages/15.0/x86_64/'; // SlackDCE repository URL
    $dir  = $_SERVER['DOCUMENT_ROOT'] . '/tmp/';                   // Local download directory
                                                                   // Construct the full download URL following repository structure:
                                                                   // base/category/package-name/package-full-name.txz
    $url  = $base . strtolower($p['category']) . '/' . $p['name'] . '/' . $p['full'];
    $dest = $dir . $p['full']; // Local destination path
    shell_exec("wget -q -O " . escapeshellarg($dest) . " " . escapeshellarg($url));
    if (file_exists($dest)) {
        echo "<p style=color:green>Download {$pkgfull}</p>";
    } else {
        echo "<p style=color:red>error {$pkgfull}</p>";
    }

}
