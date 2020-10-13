<?php
if (!defined('TYPO3_MODE')) {
	die('Access denied.');
}

$boot = function () {
	$_EXTKEY = 'ed_migrate';
	\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addStaticFile($_EXTKEY, 'Configuration/TypoScript', 'Essential Dots Migrate Library');

	\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addLLrefForTCAdescr('tx_edmigrate_domain_model_log', 'EXT:ed_migrate/Resources/Private/Language/locallang_csh_tx_edmigrate_domain_model_log.xlf');
	\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::allowTableOnStandardPages('tx_edmigrate_domain_model_log');
};

$boot();
unset($boot);