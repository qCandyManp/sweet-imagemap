<?php

defined('TYPO3') or die();

use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;

(function ($table = 'sys_file_reference') {

$newColumns = [
	'tx_sweetimagemap_imagemap' => [
		'exclude' => true,
		'label' => 'LLL:EXT:core/Resources/Private/Language/locallang_tca.xlf:sys_file_reference.crop',
		'config' => [
			'type' => 'imageManipulation',
			'renderType' => 'imagemap'
		]
	]
];

ExtensionManagementUtility::addTCAcolumns(
	$table,
	$newColumns
);

ExtensionManagementUtility::addFieldsToPalette(
	$table,
	'imageoverlayPalette',
	'tx_sweetimagemap_imagemap',
	'after:crop'
);

})();