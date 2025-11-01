<?php
/**
 * Package Removal Handler
 *
 * This script handles the removal of Slackware packages through a web interface.
 * It provides both a user interface for confirming package removal and
 * an AJAX endpoint for executing the actual removal process.
 *
 * @package SlkStore
 * @author Eduardo Castillo
 * @email hellcodelinux@gmail.com
 */

include $_SERVER['DOCUMENT_ROOT'] . '/modules/preinit.php';

// Check if this is a regular page load (not an AJAX request)
// This section handles the initial page display with confirmation buttons
if (! isset($_POST['ajax'])) {
    // Get the package to remove from the URL parameters
    // The 'full' parameter contains the complete package name (e.g., package-1.0-x86_64-1)
    $package_to_remove = isset($_GET['full']) ? $_GET['full'] : '';

    if (empty($package_to_remove)) {
        exit('No package specified for removal');
    }

    echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Removing</title>';
    echo '<style>' . $css . '</style></head><body>';
    echo '<h1>Removing Packages</h1>';
    echo '<div id="progress"><h2>Removing ' . htmlspecialchars($package_to_remove) . '...</h2></div>';
    echo '<div id="log"></div>';
    echo '<div class="install-header" style="margin-top: 20px;">';
    echo '<button type="button" class="button-accept" onclick="startRemoval(\'' . htmlspecialchars($package_to_remove) . '\')">Accept</button>';
    echo '<button type="button" class="button-cancel" onclick="history.back()">Cancel</button>';
    echo '</div>';
    @ob_flush();
    @flush();

    echo '<script>
    async function startRemoval(pkg) {
        document.querySelector(".install-header").style.display = "none"; // Hide buttons
        let formData = new FormData();
        formData.append("ajax", "remove_one");
        formData.append("pkg", pkg);
        const r = await fetch("/modules/packremove.php", { method: "POST", body: formData });
        const t = await r.text();
        document.getElementById("log").innerHTML += t;
        document.getElementById("progress").innerHTML = "<h2>Removal Complete!</h2><div class=\"logo\" style=\"font-size: 18px;\">Press CLEAR button</div>";
    }
    </script></body></html>';
    exit;
}

// Handle AJAX request for removing a single package
// This section processes the actual package removal when triggered via AJAX
if ($_POST['ajax'] === 'remove_one') {
    // Extract package name and ensure it's safe by using basename to prevent directory traversal attacks
    // This removes any path components and returns just the filename
    $pkg_full = basename($_POST['pkg']);

    // Remove package using Slackware's removepkg command with sudo privileges
    // escapeshellarg ensures safe command line argument handling
    // 2>&1 redirects stderr to stdout to capture all output including errors
    $out = shell_exec("sudo removepkg " . escapeshellarg($pkg_full) . " 2>&1");
    echo "<p style=color:green>Removed {$pkg_full}</p>";
    exit;
}
