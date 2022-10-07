<?php
use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;
use Qcandymanp\SweetImagemap\Form\Element\ImageMapElement;

defined('TYPO3') or die();

(function () {

	$GLOBALS['TYPO3_CONF_VARS']['SYS']['formEngine']['nodeRegistry'][1443361297] = [
		'nodeName' => 'imagemap',
		'priority' => 40,
		'class' => ImageMapElement::class,
	];

})();