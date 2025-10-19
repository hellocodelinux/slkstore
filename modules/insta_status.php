<?php
// Get an array of all installed packages.
$installed_packages = explode("\n", trim(shell_exec("ls /var/log/packages 2>/dev/null")));

// Function to check if a package is installed.
function is_installed($pkgname)
{
    global $installed_packages;
    // Create a regex pattern to match the package name.
    $pattern = '/^' . preg_quote($pkgname, '/') . '-[0-9]/i';
    // Loop through the installed packages.
    foreach ($installed_packages as $line) {
        // If a match is found, return true.
        if (preg_match($pattern, $line)) {
            return true;
        }

    }
    // If no match is found, return false.
    return false;
}
