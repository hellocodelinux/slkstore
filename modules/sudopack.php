<?php
include $_SERVER['DOCUMENT_ROOT'] . '/modules/preinit.php';
$current_user = trim(shell_exec('whoami'));

// Start building the HTML output
echo '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8">';
echo '<meta name="viewport" content="width=device-width, initial-scale=1.0">';
echo '<title>SlkStore - Sudo Configuration</title>';
echo '<style>' . $css . '</style></head>';
echo '<body class="' . (isset($_GET['theme']) ? $_GET['theme'] : 'dark') . '">';

// Header section using existing styles
echo '<header><div class="page-container"><div class="container">';
echo '<h1 class="logo">Sudo Configuration</h1>';
echo '<p class="tagline">Package Management Setup</p>';
echo '</div></div></header>';

// Main content section
echo '<div class="xpage-body">';
echo '<main style="max-width: 800px; margin: 0 auto;">';

// App detail section using existing styles
echo '<div class="app-detail">';
echo '<div class="app-title">Sudo Configuration Required</div>';

// Description using app-detailm class
echo '<div class="app-detailm">';
echo '<div>For SlkStore to work properly, it needs permissions to execute <b>upgradepkg</b> and <b>removepkg</b> without password.</div>';
echo '</div>';

// Configuration steps
echo '<div class="xapp-req">';
echo '<h3>Configuration Steps:</h3>';
echo '<ol>';
echo '<li>Open a terminal as administrator or root</li>';
echo '<li>Edit the file: <code>/etc/sudoers</code></li>';
echo '<li>Add the following line at the end of the file:</li>';
echo '</ol>';

// Command display using code-block class
echo '<div class="code-block">';
echo htmlspecialchars($current_user) . ' ALL=(ALL) NOPASSWD: /sbin/upgradepkg, /sbin/removepkg';
echo '</div>';

// Important notes
echo '<h3>Important Notes:</h3>';
echo '<ul>';
echo '<li>This configuration is necessary for package management</li>';
echo '<li>It only grants permissions for specific package management commands</li>';
echo '<li>It does not compromise overall system security</li>';
echo '</ul>';
echo '</div>';

// Button section using existing button styles
echo '<div class="app-detail" style="margin-top: 30px;">';
echo '<button onclick="window.parent.document.querySelector(\'.clear-button\').click();" ';
echo 'class="app-install">Reload SlkStore</button>';
echo '</div>';

echo '</div>'; // Close app-detailx
echo '</main></div>';

echo '</body></html>';
?>