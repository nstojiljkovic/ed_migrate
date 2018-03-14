<?php

namespace EssentialDots\EdMigrate\Service\Converter;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2018 Essential Dots d.o.o. Belgrade
 *  All rights reserved
 *
 *  This script is free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 3 of the License, or
 *  (at your option) any later version.
 *
 *  The GNU General Public License can be found at
 *  http://www.gnu.org/copyleft/gpl.html.
 *
 *  This script is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 *  This copyright notice MUST APPEAR in all copies of the script!
 ***************************************************************/
use TYPO3\CMS\Core\SingletonInterface;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Resource\File;

/**
 * Class AbstractFileConverter
 *
 * @package EssentialDots\EdMigrate\Service
 */
class AbstractFileConverter implements SingletonInterface {

	/**
	 * @var string
	 */
	public $template = 'Xlf/locallang.xlft';

	/**
	 * @var array
	 */
	public $conversion = array('xml' => 'xlf');

	/**
	 * parser class for parsing source files
	 * @var string
	 */
	public $parsetType = \TYPO3\CMS\Core\Localization\Parser\LocallangXmlParser::class;

	/**
	 * @var AbstractFileConverter
	 */
	protected static $singletonInstance;

	/** @var array */
	protected $options = array();

	/**
	 * @return AbstractFileConverter|object
	 */
	public static function getInstance() {
		if (!self::$singletonInstance) {
			self::$singletonInstance = GeneralUtility::makeInstance('TYPO3\\CMS\\Extbase\\Object\\ObjectManager')->get(self::class);
		}

		return self::$singletonInstance;
	}

	/**
	 * @return array
	 */
	public function getOptions() {
		return $this->options;
	}

	/**
	 * @param array $options
	 */
	public function setOptions($options) {
		$this->options = $options;
	}

	/**
	 * @param string $key
	 * @param null $default
	 * @return mixed|null
	 */
	public function getOption($key, $default = NULL) {
		return isset($this->options[$key]) ? $this->options[$key] : $default;
	}

	/**
	 * @param $key
	 * @param $option
	 */
	public function setOption($key, $option) {
		$this->options[$key] = $option;
	}

	/**
	 * @param string $from
	 * @param string $to
	 * @return bool
	 */
	public function isConversionSupported($from, $to) {
		return $this->conversion[strtolower($from)] === strtolower($to);
	}

	/**
	 * @param File $sourceFile
	 * @param File $destinationFile
	 * @return void
	 */
	public function convert(File $sourceFile, File $destinationFile) {
		$content = $this->convertLocallangFile($sourceFile);
		$destinationFile->setContents($content);
	}

	/**
	 *
	 * @param string $xmlFile Absolute path to the locallang.xml/xlf file to convert
	 * @return string
	 */
	protected function convertLocallangFile(File $xmlFile) {

		$langKey = $this->getOption('langKey', '');
		if (strlen($langKey) === 0) {
			throw new \RuntimeException('langKey is not set', 1314187885);
		}

		$localLangArray = $this->getLocalLangArray($xmlFile);
		$extensionKey = $this->getOption('extension', '');
		$sourceLanguageKey = $this->getOption('sourceLanguage');

		$labels = array();
		foreach ($localLangArray[$langKey] as $key => $data) {
			$source = isset($localLangArray['default'][$key][0]['source']) ? $localLangArray['default'][$key][0]['source'] : $data[0]['source'];
			$target = isset($data[0]['target']) ? $data[0]['target'] : NULL;

			$labels[$key] = array(
				'source' => $source,
				'sourceMd5' => GeneralUtility::md5int($source),
				'target' => $target
			);
		}

		$variables = array(
			'sourceLanguage' => $sourceLanguageKey,
			'targetLanguage' => $langKey,
			'extensionKey' => $extensionKey,
			'labels' => $labels

		);
		$ret = $this->renderTemplate($this->template, $variables);
		return $ret;
	}

	/**
	 * Includes locallang files and returns raw $localLangArray array
	 *
	 * @param File $xmlFile the ll-XML locallang file.
	 * @return array LOCAL_LANG array from ll-XML file (with all possible sub-files for languages included)
	 */
	protected function getLocalLangArray(File $xmlFile) {
		$ll = GeneralUtility::xml2array($xmlFile->getContents());
		if (!isset($ll['data'])) {
			throw new \RuntimeException('data section not found in "' . $xmlFile->getCombinedIdentifier() . '"', 1314187884);
		}
		$langKey = $this->getOption('langKey', '');
		if (strlen($langKey) === 0) {
			throw new \RuntimeException('langKey is not set', 1314187885);
		}
		$includedLanguages = array_keys($ll['data']);
		$extensionKey = $this->getOption('extension', '');

		/** @var \TYPO3\CMS\Core\Localization\Parser\LocallangXmlParser $parser */
		$parser = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance($this->parsetType);
		$localLangArray = array();
		$sourceFilePath = $this->getOption('sourceFilePath', '');
		$sourceFileName = $this->getOption('sourceFileName', '');
		$typo3SitePath = GeneralUtility::getIndpEnv('TYPO3_SITE_PATH');
		$locallangPathAndFilename = $typo3SitePath . 'typo3conf/ext/' . $sourceFilePath;
		if (!file_exists(GeneralUtility::getFileAbsFileName($locallangPathAndFilename))) {
			$locallangPathAndFilename = $typo3SitePath . 'typo3conf/ext/' . $extensionKey . '/Resources/Private/Language/' . $sourceFileName;
		}
		if (file_exists(GeneralUtility::getFileAbsFileName($locallangPathAndFilename))) {
			$localLangArray = array_merge($localLangArray, $parser->getParsedData($locallangPathAndFilename, 'default', $GLOBALS['LANG']->charSet));
		}
		foreach ($includedLanguages as $langKey) {
			$localLangArray = array_merge($localLangArray, $parser->getParsedData($xmlFile->getForLocalProcessing(FALSE), $langKey, $GLOBALS['LANG']->charSet));
		}

		return $localLangArray;
	}

	/**
	 * Render a template with variables
	 *
	 * @param string $filePath
	 * @param array $variables
	 */
	public function renderTemplate($filePath, $variables) {
		$rootPath = 'EXT:ed_migrate/Resources/Private/Templates/Converter/';
		$layoutRootPath = $rootPath . 'Layouts/';
		$partialRootPath = $rootPath . 'Partials/';
		$templateRootPath = $rootPath . 'Templates/';
		$templatePathAndFilename = $templateRootPath . $filePath;
		/* @var \TYPO3\CMS\Fluid\View\StandaloneView $standAloneView */
		$standAloneView = GeneralUtility::makeInstance('TYPO3\\CMS\\Extbase\\Object\\ObjectManager')->get('TYPO3\\CMS\\Fluid\\View\\StandaloneView');
		$standAloneView->setLayoutRootPaths(array($layoutRootPath));
		$standAloneView->setPartialRootPaths(array($partialRootPath));
		$standAloneView->setFormat('txt');

		$standAloneView->setTemplatePathAndFilename($templatePathAndFilename);
		$standAloneView->assignMultiple($variables);
		$renderedContent = $standAloneView->render();
		// remove all double empty lines (coming from fluid)
		return preg_replace('/^\\s*\\n[\\t ]*$/m', '', $renderedContent);
	}
}