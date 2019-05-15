<?php

if (!defined('TYPO3_MODE')) {
	die ('Access denied.');
}

$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['ed_migrate']['LocalLangServiceFileConverter'][] = 'EssentialDots\\EdMigrate\\Service\\Converter\\LocallangXml2XlifConverter';
$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['ed_migrate']['LocalLangServiceFileConverter'][] = 'EssentialDots\\EdMigrate\\Service\\Converter\\Xlif2LocallangXmlConverter';
