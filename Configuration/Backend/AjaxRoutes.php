<?php

use Qcandymanp\SweetImagemap\Controller\Wizard\ImageMapController;

return [
	'wizard_imagemap' => [
		'path' => 'wizard/imagemap',
		'target' => ImageMapController::class . '::getWizardContent',
	],
];