<?php

/**
 * @file plugins/generic/dataverse/classes/form/DataverseAuthForm.inc.php
 *
 * Copyright (c) 2019-2021 Lepidus Tecnologia
 * Copyright (c) 2020-2021 SciELO
 * Distributed under the GNU GPL v3. For full terms see LICENSE or https://www.gnu.org/licenses/gpl-3.0.txt
 *
 * @class DataverseAuthForm
 * @ingroup plugins_generic_dataverse
 *
 * @brief Plugin settings: connect to a Dataverse Network 
 */
import('lib.pkp.classes.form.Form');
import('plugins.generic.dataversePlugin.classes.DataverseDAO');
import('plugins.generic.dataversePlugin.classes.DataverseRepository');

class DataverseAuthForm extends Form {

	/** @var $_plugin DataversePlugin */
	var $_plugin;

	/** @var $_journalId int */
	var $_journalId;

	/**
	 * Constructor. 
	 * @param $plugin DataversePlugin
	 * @param $journalId int
	 * @see Form::Form()
	 */
	function DataverseAuthForm(&$plugin, $journalId) {
		$this->_plugin =& $plugin;
		$this->_journalId = $journalId;

		parent::__construct($plugin->getTemplateResource('dataverseAuthForm.tpl'));
		$this->addCheck(new FormValidatorUrl($this, 'dvnUri', FORM_VALIDATOR_REQUIRED_VALUE, 'plugins.generic.dataverse.settings.dvnUriRequired'));
		$this->addCheck(new FormValidator($this, 'apiToken', FORM_VALIDATOR_REQUIRED_VALUE, 'plugins.generic.dataverse.settings.tokenRequired'));
		$this->addCheck(new FormValidatorCustom($this, '', FORM_VALIDATOR_REQUIRED_VALUE, 'plugins.generic.dataverse.settings.dvnUriNotValid', array(&$this, '_getServiceDocument')));
		$this->addCheck(new FormValidatorPost($this));
	}

	/**
	 * @see Form::initData()
	 */
	function initData() {
		$plugin =& $this->_plugin;

		// Initialize from plugin settings
		$this->setData('dvnUri', $plugin->getSetting($this->_journalId, 'dvnUri'));		 
		$this->setData('apiToken', $plugin->getSetting($this->_journalId, 'apiToken'));
	}

	/**
	 * @see Form::readInputData()
	 */
	function readInputData() {
		$this->readUserVars(array('dvnUri', 'apiToken'));
		$request = PKPApplication::getRequest();
		$this->setData('dvnUri', preg_replace("/\/+$/", '', $this->getData('dvnUri')));
	}

	function fetch($request, $template = NULL, $display = false)
	{
		$templateMgr = TemplateManager::getManager($request);
		$templateMgr->assign('pluginName', $this->_plugin->getName());
		return parent::fetch($request);
	}

	/**
	 * @see Form::execute()
	 */
	function execute(...$functionArgs) {
		$plugin =& $this->_plugin;
		$plugin->updateSetting($this->_journalId, 'dvnUri', $this->getData('dvnUri'), 'string');
		$plugin->updateSetting($this->_journalId, 'apiToken', $this->getData('apiToken'), 'string');
		$plugin->updateSetting($this->_journalId, 'apiVersion', $this->getData('apiVersion'), 'string');
		parent::execute(...$functionArgs);
	}

	/**
	 * Form validator: verify service document can be retrieved from specified 
	 * Dataverse with given username & password.
	 * @return boolean 
	 */
	function _getServiceDocument() {
		$this->setData('apiVersion', '1');
		
		$serviceDocumentRequest = preg_match('/\/dvn$/', $this->getData('dvnUri')) ? '' : '/dvn';
		$serviceDocumentRequest .= '/api/data-deposit/v'. $this->getData('apiVersion') . '/swordv2/service-document';

		$dataverseRepository = new DataverseRepository();
		$dataverseConnectionStatus = $dataverseRepository->validateCredentials($this, $serviceDocumentRequest);
		
		if ($dataverseConnectionStatus) {
			$dataverseDAO = new DataverseDAO();
			$dataverseDAO->insertCredentialsOnDatabase($this->_journalId, $this->getData('dvnUri'), $this->getData('apiToken'));
		}

		return ($dataverseConnectionStatus);
	}
}
