<?php
return array(
	'ctrl' => array(
		'title'	=> 'LLL:EXT:ed_migrate/Resources/Private/Language/locallang_db.xlf:tx_edmigrate_domain_model_log',
		'label' => 'version',
		'tstamp' => 'tstamp',
		'crdate' => 'crdate',
		'cruser_id' => 'cruser_id',
		'dividers2tabs' => TRUE,

		'enablecolumns' => array(

		),
		'searchFields' => 'version,start_time,end_time,namespace,',
		'iconfile' => \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::extRelPath('ed_migrate') . 'Resources/Public/Icons/tx_edmigrate_domain_model_log.gif'
	),
	'interface' => array(
		'showRecordFieldList' => 'version, start_time, end_time, namespace',
	),
	'types' => array(
		'1' => array('showitem' => 'version, start_time, end_time, namespace, '),
	),
	'palettes' => array(
		'1' => array('showitem' => ''),
	),
	'columns' => array(

		'version' => array(
			'exclude' => 1,
			'label' => 'LLL:EXT:ed_migrate/Resources/Private/Language/locallang_db.xlf:tx_edmigrate_domain_model_log.version',
			'config' => array(
				'type' => 'input',
				'size' => 30,
				'eval' => 'trim'
			),
		),
		'start_time' => array(
			'exclude' => 1,
			'label' => 'LLL:EXT:ed_migrate/Resources/Private/Language/locallang_db.xlf:tx_edmigrate_domain_model_log.start_time',
			'config' => array(
				'type' => 'input',
				'size' => 10,
				'eval' => 'datetime',
				'checkbox' => 1,
				'default' => time()
			),
		),
		'end_time' => array(
			'exclude' => 1,
			'label' => 'LLL:EXT:ed_migrate/Resources/Private/Language/locallang_db.xlf:tx_edmigrate_domain_model_log.end_time',
			'config' => array(
				'type' => 'input',
				'size' => 30,
				'eval' => 'trim'
			),
		),
		'namespace' => array(
			'exclude' => 1,
			'label' => 'LLL:EXT:ed_migrate/Resources/Private/Language/locallang_db.xlf:tx_edmigrate_domain_model_log.namespace',
			'config' => array(
				'type' => 'input',
				'size' => 30,
				'eval' => 'trim'
			),
		),
		
	),
);