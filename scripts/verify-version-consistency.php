<?php

declare(strict_types=1);

$pluginRoot = dirname(__DIR__);
$versionFile = $pluginRoot . '/VERSION';
$pluginFile = $pluginRoot . '/gls-shipping-for-woocommerce.php';
$readmeFile = $pluginRoot . '/README.txt';

if (! file_exists($versionFile) || ! file_exists($pluginFile) || ! file_exists($readmeFile)) {
    fwrite(STDERR, "Missing VERSION, plugin main file or README.txt.\n");
    exit(1);
}

$version = trim((string) file_get_contents($versionFile));
$pluginSource = (string) file_get_contents($pluginFile);
$readmeSource = (string) file_get_contents($readmeFile);

if ('' === $version) {
    fwrite(STDERR, "VERSION file is empty.\n");
    exit(1);
}

$errors = array();

if (! preg_match('/^\s*\*\s*Version:\s*' . preg_quote($version, '/') . '\s*$/mi', $pluginSource)) {
    $errors[] = 'Plugin header Version does not match VERSION file.';
}

if (! preg_match("/define\\(\\s*'GLS_SHIPPING_VERSION'\\s*,\\s*'" . preg_quote($version, '/') . "'\\s*\\)/", $pluginSource)) {
    $errors[] = 'GLS_SHIPPING_VERSION does not match VERSION file.';
}

if (! preg_match("/private \\\$version = '" . preg_quote($version, '/') . "';/", $pluginSource)) {
    $errors[] = 'Private $version property does not match VERSION file.';
}

if (! preg_match('/^Stable tag:\s*' . preg_quote($version, '/') . '\s*$/mi', $readmeSource)) {
    $errors[] = 'README.txt Stable tag does not match VERSION file.';
}

if (! empty($errors)) {
    foreach ($errors as $error) {
        fwrite(STDERR, $error . "\n");
    }

    exit(1);
}

echo "Version consistency OK ({$version}).\n";
