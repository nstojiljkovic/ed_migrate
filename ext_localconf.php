<?php

if (!defined('TYPO3_MODE')) {
	die ('Access denied.');
}

$boot = function () {
	$_EXTKEY = 'ed_migrate';
	$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['ed_migrate']['LocalLangServiceFileConverter'][] = 'EssentialDots\\EdMigrate\\Service\\Converter\\LocallangXml2XlifConverter';
	$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['ed_migrate']['LocalLangServiceFileConverter'][] = 'EssentialDots\\EdMigrate\\Service\\Converter\\Xlif2LocallangXmlConverter';
};

$boot();
unset($boot);