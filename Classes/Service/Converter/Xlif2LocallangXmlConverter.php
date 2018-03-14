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
use TYPO3\CMS\Core\Localization\LocalizationFactory;

/**
 * Class Xlif2LocallangXmlConverter
 *
 * @package EssentialDots\EdMigrate\Service
 */
class Xlif2LocallangXmlConverter extends \EssentialDots\EdMigrate\Service\Converter\AbstractFileConverter {

	/**
	 * @var string
	 */
	public $template = 'Xml/locallang.xmlt';

	/**
	 * @var array
	 */
	public $conversion = array('xlf' => 'xml');

	/**
	 * parser class for parsing source files
	 * @var string
	 */
	public $parsetType = \TYPO3\CMS\Core\Localization\Parser\XliffParser::class;

	/**
	 * Includes locallang files and returns raw $localLangArray array
	 *
	 * @param File $xmlFile the ll-XML locallang file.
	 * @return array LOCAL_LANG array from ll-XML file (with all possible sub-files for languages included)
	 */
	protected function getLocalLangArray(File $xmlFile) {
		$langKey = $this->getOption('langKey', '');
		/** @var \TYPO3\CMS\Core\Localization\Parser\XliffParser $parser */
		$parser = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance($this->parsetType);
		$localLangArray = array();
		$localLangArray = array_merge($localLangArray, $parser->getParsedData($xmlFile->getForLocalProcessing(FALSE), $langKey, $GLOBALS['LANG']->charSet));

		return $localLangArray;
	}
}