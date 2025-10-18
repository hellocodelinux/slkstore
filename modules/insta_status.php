<?php
$installed_packages = explode("\n", trim(shell_exec("ls /var/log/packages 2>/dev/null")));

function is_installed($pkgname) {
    global $installed_packages;
    $pattern = '/^' . preg_quote($pkgname, '/') . '-[0-9]/i';
    foreach ($installed_packages as $line) {
        if (preg_match($pattern, $line)) return true;
    }
    return false;
}
?>