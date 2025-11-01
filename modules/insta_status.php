<?php
/**
 * Package Installation Status Checker
 *
 * This module provides functionality to check whether a package is installed
 * in the Slackware system by examining the /var/log/packages directory.
 * It supports version-aware package name matching and handles error cases.
 *
 * @package SlkStore
 * @author Eduardo Castillo
 * @email hellcodelinux@gmail.com
 */

// Retrieve a list of all packages installed in the system
// Redirect stderr to /dev/null to suppress any potential error messages
$installed_packages = explode("\n", trim(shell_exec("ls /var/log/packages 2>/dev/null")));

/**
 * Check if a specific package is installed in the system
 *
 * @param string $pkgname The name of the package to check (without version)
 * @return bool True if the package is installed, false otherwise
 */
function is_installed($pkgname)
{
    global $installed_packages;

    // Create a regex pattern to match the package name followed by a version number
    // The pattern will match:
    // - Start of string (^)
    // - Exact package name (escaped to handle special characters)
    // - Hyphen followed by a number (indicating version)
    // - Case-insensitive matching (/i)
    $pattern = '/^' . preg_quote($pkgname, '/') . '-[0-9]/i';

    // Search through the list of installed packages
    foreach ($installed_packages as $line) {
        // Check if the current package matches our pattern
        // This will match packages like "name-1.2.3" but not "name-dev"
        if (preg_match($pattern, $line)) {
            return true; // Package is installed
        }
    }

    // Package was not found in the installed packages list
    return false;
}
