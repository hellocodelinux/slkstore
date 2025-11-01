<?php
/**
 * Package Removal Handler
 *
 * This script manages the removal process for SlackDCE packages.
 * It provides:
 * - Package removal management
 * - Progress tracking and display
 * - AJAX-based status updates
 */

include $_SERVER['DOCUMENT_ROOT'] . '/modules/preinit.php';

// Check if this is a regular page load (not an AJAX request)
if (!isset($_POST['ajax'])) {
    // Get the package to remove from the URL parameters
    $package_to_remove = isset($_GET['full']) ? $_GET['full'] : '';
    
    if (empty($package_to_remove)) {
        exit('No package specified for removal');
    }

    // Initialize the removal progress page
    echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Removing Package</title>';
    echo '<style>' . $css . '</style></head><body class="' . $theme . '">';
    echo '<h1>Removing Package</h1>';
    echo '<div id="progress">';
    echo '<h2>Removing package...</h2>';
    echo '<div style="width:80%;border:1px solid #ccc;">';
    echo '<div id="removebar" style="width:0%;height:30px;background:#cc0000;color:#fff;text-align:center;line-height:30px;">0%</div>';
    echo '</div></div>';
    echo '<div id="log"></div>';
    @ob_flush();
    @flush();

    echo '<script>
    const package_to_remove = ' . json_encode($package_to_remove) . ';

    async function startRemoval() {
        const f = await fetch("/modules/packremove.php", {
            method: "POST",
            body: new URLSearchParams({ 
                ajax: "remove_package", 
                package: package_to_remove 
            })
        });
        const t = await f.text();
        document.getElementById("log").innerHTML += t;
        document.querySelector("#removebar").style.width = "100%";
        document.querySelector("#removebar").textContent = "100%";
        document.getElementById("progress").innerHTML += "<h2>Removal Complete!</h2><div class=\"logo\" style=\"font-size: 18px;\">Press CLEAR button</div>";
    }

    startRemoval();
    </script></body></html>';
    exit;
}

// Handle AJAX request for removing the package
if ($_POST['ajax'] === 'remove_package') {
    // Extract package name and ensure it's safe
    $pkg = basename($_POST['package']);
    
    // Remove package using removepkg
    // Redirect stderr to stdout (2>&1) to capture all output
    $out = shell_exec("sudo removepkg " . escapeshellarg($pkg) . " 2>&1");
    
    if ($out) {
        echo "<p style='color:green'>Successfully removed package: {$pkg}</p>";
        echo "<pre style='color:gray'>{$out}</pre>";
    } else {
        echo "<p style='color:red'>Error removing package: {$pkg}</p>";
    }
    exit;
}
