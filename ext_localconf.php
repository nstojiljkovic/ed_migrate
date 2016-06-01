<?php

if (!defined('TYPO3_MODE')) {
	die ('Access denied.');
}

if (TYPO3_MODE === 'BE') {
	$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['extbase']['commandControllers'][] = 'EssentialDots\\EdMigrate\\Command\\EdMigrationCommandController';
}

$composerAutoloadPath = \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::extPath($_EXTKEY, 'Packages/vendor/autoload.php');
$composerPath = \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::extPath($_EXTKEY, 'Packages/vendor');

if (@is_dir($composerPath) && @is_file($composerAutoloadPath)) {
	if (set_include_path($composerPath . PATH_SEPARATOR . get_include_path()) !== FALSE) {
		require_once($composerAutoloadPath);
	}
}
