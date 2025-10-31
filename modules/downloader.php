<?php
include $_SERVER['DOCUMENT_ROOT']. '/modules/preinit.php';

if (!isset($_POST['ajax'])) {
    $packages_to_download_json = isset($_POST['packages']) ? htmlspecialchars_decode($_POST['packages']) : '[]';
    $packages_to_download = json_decode($packages_to_download_json, true);

    $tmp_dir = $_SERVER['DOCUMENT_ROOT'] . '/tmp/';
    shell_exec("rm -f " . $tmp_dir . "*.txz");

    echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Downloading</title>';
    echo '<style>' . $css . '</style></head><body>';
    echo '<h1>Downloading Packages</h1>';
    echo '<div id="progress"></div><div id="log"></div>';
    echo '<script>
    let packages = ' . json_encode($packages_to_download) . ';
    let total = packages.length;
    let count = 0;

    async function next() {
        if (packages.length === 0) {
            document.getElementById("progress").innerHTML = "<h2>Download Complete!</h2>";
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
        document.getElementById("progress").innerHTML = "<h2>"+count+" / "+total+" ("+pct+"%)</h2><div style=\'width:80%;border:1px solid #ccc;\'><div style=\'width:"+pct+"%;height:30px;background:#468848;color:#fff;text-align:center;line-height:30px;\'>"+pct+"%</div></div>";
        next();
    }

    next();
    </script></body></html>';
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
        echo "<p style=color:green>descargado {$pkgfull}</p>";
    else
        echo "<p style=color:red>error {$pkgfull}</p>";
}
