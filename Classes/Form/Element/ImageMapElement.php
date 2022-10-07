<?php

declare(strict_types=1);

/*
 * This file is part of the TYPO3 CMS project.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 * The TYPO3 project - inspiring people to share!
 */

namespace Qcandymanp\SweetImagemap\Form\Element;

use TYPO3\CMS\Backend\Form\Element\AbstractFormElement;
use TYPO3\CMS\Backend\Form\NodeFactory;
use TYPO3\CMS\Backend\Routing\UriBuilder;
use TYPO3\CMS\Core\Imaging\ImageManipulation\Area;
use TYPO3\CMS\Core\Imaging\ImageManipulation\CropVariantCollection;
use TYPO3\CMS\Core\Imaging\ImageManipulation\InvalidConfigurationException;
use TYPO3\CMS\Core\Page\JavaScriptModuleInstruction;
use TYPO3\CMS\Core\Resource\Exception\FileDoesNotExistException;
use TYPO3\CMS\Core\Resource\File;
use TYPO3\CMS\Core\Resource\ResourceFactory;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Utility\MathUtility;
use TYPO3\CMS\Core\Utility\StringUtility;
use TYPO3\CMS\Fluid\View\StandaloneView;

/**
 * Generation of image manipulation FormEngine element.
 * This is typically used in FAL relations to cut images.
 */
class ImageMapElement extends AbstractFormElement
{
	/**
	 * @var string
	 */
	private $wizardRouteName = 'ajax_wizard_imagemap';

	/**
	 * Default element configuration
	 *
	 * @var array
	 */
	protected static $defaultConfig = [
		'file_field' => 'uid_local',
		'allowedExtensions' => null, // default: $GLOBALS['TYPO3_CONF_VARS']['GFX']['imagefile_ext']
	];

	/**
	 * Default field information enabled for this element.
	 *
	 * @var array
	 */
	protected $defaultFieldInformation = [
		'tcaDescription' => [
			'renderType' => 'tcaDescription',
		],
	];

	/**
	 * Default field wizards enabled for this element.
	 *
	 * @var array
	 */
	protected $defaultFieldWizard = [
		'localizationStateSelector' => [
			'renderType' => 'localizationStateSelector',
		],
		'otherLanguageThumbnails' => [
			'renderType' => 'otherLanguageThumbnails',
			'after' => [
				'localizationStateSelector',
			],
		],
		'defaultLanguageDifferences' => [
			'renderType' => 'defaultLanguageDifferences',
			'after' => [
				'otherLanguageThumbnails',
			],
		],
	];

	/**
	 * @var StandaloneView
	 */
	protected $templateView;

	/**
	 * @var UriBuilder
	 */
	protected $uriBuilder;

	/**
	 * @param NodeFactory $nodeFactory
	 * @param array $data
	 */
	public function __construct(NodeFactory $nodeFactory, array $data)
	{
		parent::__construct($nodeFactory, $data);
		// Would be great, if we could inject the view here, but since the constructor is in the interface, we can't
		$this->templateView = GeneralUtility::makeInstance(StandaloneView::class);
		$this->templateView->setLayoutRootPaths([GeneralUtility::getFileAbsFileName('EXT:sweet_imagemap/Resources/Private/Layouts/')]);
		$this->templateView->setPartialRootPaths([GeneralUtility::getFileAbsFileName('EXT:sweet_imagemap/Resources/Private/Partials/ImageMap/')]);
		$this->templateView->setTemplatePathAndFilename(GeneralUtility::getFileAbsFileName('EXT:sweet_imagemap/Resources/Private/Templates/ImageMap/ImageMapElement.html'));
		$this->uriBuilder = GeneralUtility::makeInstance(UriBuilder::class);
	}

	/**
	 * This will render an imageManipulation field
	 *
	 * @return array As defined in initializeResultArray() of AbstractNode
	 * @throws \TYPO3\CMS\Core\Imaging\ImageManipulation\InvalidConfigurationException
	 */
	public function render()
	{
		$resultArray = $this->initializeResultArray();
		$parameterArray = $this->data['parameterArray'];
		$config = $this->populateConfiguration($parameterArray['fieldConf']['config']);

		$file = $this->getFile($this->data['databaseRow'], $config['file_field']);
		if (!$file) {
			// Early return in case we do not find a file
			return $resultArray;
		}

		$config = $this->processConfiguration($config, $parameterArray['itemFormElValue'], $file);

		$fieldInformationResult = $this->renderFieldInformation();
		$fieldInformationHtml = $fieldInformationResult['html'];
		$resultArray = $this->mergeChildReturnIntoExistingResult($resultArray, $fieldInformationResult, false);

		$fieldControlResult = $this->renderFieldControl();
		$fieldControlHtml = $fieldControlResult['html'];
		$resultArray = $this->mergeChildReturnIntoExistingResult($resultArray, $fieldControlResult, false);

		$fieldWizardResult = $this->renderFieldWizard();
		$fieldWizardHtml = $fieldWizardResult['html'];
		$resultArray = $this->mergeChildReturnIntoExistingResult($resultArray, $fieldWizardResult, false);

		$arguments = [
			'fieldInformation' => $fieldInformationHtml,
			'fieldControl' => $fieldControlHtml,
			'fieldWizard' => $fieldWizardHtml,
			'isAllowedFileExtension' => in_array(strtolower($file->getExtension()), GeneralUtility::trimExplode(',', strtolower($config['allowedExtensions'])), true),
			'image' => $file,
			'formEngine' => [
				'field' => [
					'value' => $parameterArray['itemFormElValue'],
					'name' => $parameterArray['itemFormElName'],
				],
				'validation' => '[]',
			],
			'config' => $config,
			'wizardUri' => $this->getWizardUri(),
			'wizardPayload' => json_encode($this->getWizardPayload($file)),
			'previewUrl' => $this->getPreviewUrl($this->data['databaseRow'], $file),
		];

		if ($arguments['isAllowedFileExtension']) {
			$resultArray['requireJsModules'][] = JavaScriptModuleInstruction::forRequireJS(
				'TYPO3/CMS/Backend/ImageManipulation'
			)->invoke('initializeTrigger');
			$arguments['formEngine']['field']['id'] = StringUtility::getUniqueId('formengine-image-manipulation-');
			if (GeneralUtility::inList($config['eval'] ?? '', 'required')) {
				$arguments['formEngine']['validation'] = $this->getValidationDataAsJsonString(['required' => true]);
			}
		}
		$this->templateView->assignMultiple($arguments);
		$resultArray['html'] = $this->templateView->render();

		return $resultArray;
	}

	/**
	 * Get file object
	 *
	 * @param array $row
	 * @param string $fieldName
	 * @return File|null
	 */
	protected function getFile(array $row, $fieldName)
	{
		$file = null;
		$fileUid = !empty($row[$fieldName]) ? $row[$fieldName] : null;
		if (is_array($fileUid) && isset($fileUid[0]['uid'])) {
			$fileUid = $fileUid[0]['uid'];
		}
		if (MathUtility::canBeInterpretedAsInteger($fileUid)) {
			try {
				$file = GeneralUtility::makeInstance(ResourceFactory::class)->getFileObject($fileUid);
			} catch (FileDoesNotExistException|\InvalidArgumentException $e) {
			}
		}
		return $file;
	}

	/**
	 * @param array $databaseRow
	 * @param File $file
	 * @return string
	 */
	protected function getPreviewUrl(array $databaseRow, File $file): string
	{
		$previewUrl = '';
		// Hook to generate a preview URL
		$hookParameters = [
			'databaseRow' => $databaseRow,
			'file' => $file,
			'previewUrl' => $previewUrl,
		];
		foreach ($GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['Backend/Form/Element/ImageMapElement']['previewUrl'] ?? [] as $listener) {
			$previewUrl = GeneralUtility::callUserFunction($listener, $hookParameters, $this);
		}
		return $previewUrl;
	}

	/**
	 * @param array $baseConfiguration
	 * @return array
	 * @throws InvalidConfigurationException
	 */
	protected function populateConfiguration(array $baseConfiguration)
	{
		$defaultConfig = self::$defaultConfig;

		$config = array_replace_recursive($defaultConfig, $baseConfiguration);

		// By default we allow all image extensions that can be handled by the GFX functionality
		$config['allowedExtensions'] ??= $GLOBALS['TYPO3_CONF_VARS']['GFX']['imagefile_ext'];
		return $config;
	}

	/**
	 * @param array $config
	 * @param string $elementValue
	 * @param File $file
	 * @return array
	 * @throws \TYPO3\CMS\Core\Imaging\ImageManipulation\InvalidConfigurationException
	 */
	protected function processConfiguration(array $config)
	{
		$config['allowedExtensions'] = implode(', ', GeneralUtility::trimExplode(',', $config['allowedExtensions'], true));
		return $config;
	}

	/**
	 * @return string
	 */
	protected function getWizardUri(): string
	{
		return (string)$this->uriBuilder->buildUriFromRoute($this->wizardRouteName);
	}

	/**
	 * @param File $image
	 * @return array
	 */
	protected function getWizardPayload(File $image): array
	{
		$uriArguments = [];
		$arguments = [
			'image' => $image->getUid(),
		];
		$uriArguments['arguments'] = json_encode($arguments);
		$uriArguments['signature'] = GeneralUtility::hmac((string)($uriArguments['arguments'] ?? ''), $this->wizardRouteName);

		return $uriArguments;
	}
}
