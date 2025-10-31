<?php
include $_SERVER['DOCUMENT_ROOT'] . '/modules/preinit.php';

$tmp_dir = $_SERVER['DOCUMENT_ROOT'] . '/tmp/';

if (!isset($_POST['ajax'])) {
    $packages_to_download_json = isset($_POST['packages']) ? htmlspecialchars_decode($_POST['packages']) : '[]';
    $packages_to_download = json_decode($packages_to_download_json, true);

    shell_exec("rm -f " . $tmp_dir . "*.txz");

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

if ($_POST['ajax'] === 'list_installs') {
    $files = glob($tmp_dir . "*.txz");
    $names = [];
    foreach ($files as $f) $names[] = basename($f);
    header('Content-Type: application/json');
    echo json_encode($names);
    exit;
}

if ($_POST['ajax'] === 'install_one') {
    $pkg = basename($_POST['pkg']);
    $file = $tmp_dir . $pkg;
    $out = shell_exec("upgradepkg --install-new " . escapeshellarg($file) . " 2>&1");
    echo "<p style=color:green>Installed {$pkg}</p>";
    exit;
}

include_once $_SERVER['DOCUMENT_ROOT'] . '/cache/packages.php';
$all_packages = $products_cache;
$pkgfull = $_POST['package'];

function find_package_by_full($full, $packages) {
    foreach ($packages as $pkg)
        if ($pkg['full'] === $full)
            return $pkg;
    return null;
}

$p = find_package_by_full($pkgfull, $all_packages);
if ($p) {
    $base = 'https://slackware.uk/slackdce/packages/15.0/x86_64/';
    $dir = $_SERVER['DOCUMENT_ROOT'] . '/tmp/';
    $url = $base . strtolower($p['category']) . '/' . $p['name'] . '/' . $p['full'];
    $dest = $dir . $p['full'];
    shell_exec("wget -q -O " . escapeshellarg($dest) . " " . escapeshellarg($url));
    if (file_exists($dest))
        echo "<p style=color:green>Download {$pkgfull}</p>";
    else
        echo "<p style=color:red>error {$pkgfull}</p>";
}