<?php

import('plugins.generic.dataverse.classes.dispatchers.DataverseDispatcher');
import('plugins.generic.dataverse.classes.creators.DataverseServiceFactory');
import('plugins.generic.dataverse.classes.study.DataverseStudyDAO');

class DataverseServiceDispatcher extends DataverseDispatcher
{
    public function __construct(Plugin $plugin)
	{
		HookRegistry::register('Dispatcher::dispatch', array($this, 'setupDraftDatasetFileHandler'));
		HookRegistry::register('Schema::get::draftDatasetFile', array($this, 'loadDraftDatasetFileSchema'));
		HookRegistry::register('Schema::get::submissionFile', array($this, 'modifySubmissionFileSchema'));
		HookRegistry::register('Schema::get::galley', array($this, 'modifyGalleySchema'));
		HookRegistry::register('submissionsubmitstep4form::validate', array($this, 'dataverseDepositOnSubmission'));
		HookRegistry::register('Publication::publish', array($this, 'publishDeposit'));

		parent::__construct($plugin);
    }

    public function modifySubmissionFileSchema(string $hookName, array $params): bool
	{
		$schema =& $params[0];
        $schema->properties->{'publishData'} = (object) [
            'type' => 'boolean',
            'apiSummary' => true,
            'validation' => ['nullable'],
        ];
        return false;
	}

	public function modifyGalleySchema(string $hookName, array $params): bool
	{
		$schema =& $params[0];
        $schema->properties->{'dataverseGalley'} = (object) [
            'type' => 'boolean',
            'apiSummary' => true,
            'validation' => ['nullable'],
        ];
        return false;
	}

    function dataverseDepositOnSubmission(string $hookName, array $params): void {
		$form =& $params[0];
        $submission = $form->submission;

		$service = $this->getDataverseService();
		$service->setSubmission($submission);
		$service->depositPackage();
	}

	function publishDeposit(string $hookName, array $params): void {
		$submission = $params[2];
		
		$service = $this->getDataverseService();
		$service->setSubmission($submission);
		$service->releaseStudy();
	}

	public function setupDraftDatasetFileHandler(string $hookname, Request $request): bool
	{
		$router = $request->getRouter();
		if ($router instanceof \APIRouter && str_contains($request->getRequestPath(), 'api/v1/draftDatasetFiles')) {
			$this->plugin->import('api.v1.draftDatasetFiles.DraftDatasetFileHandler');
			$handler = new DraftDatasetFileHandler();
			$router->setHandler($handler);
			$handler->getApp()->run();
			exit;
		}
		return false;
	}

	public function loadDraftDatasetFileSchema($hookname, $params): bool
	{
		$schema = &$params[0];
		$draftDatasetFileSchemaFile = BASE_SYS_DIR . '/plugins/generic/dataverse/schemas/draftDatasetFile.json';

		if (file_exists($draftDatasetFileSchemaFile)) {
			$schema = json_decode(file_get_contents($draftDatasetFileSchemaFile));
			if (!$schema) {
				fatalError('Schema failed to decode. This usually means it is invalid JSON. Requested: ' . $draftDatasetFileSchemaFile . '. Last JSON error: ' . json_last_error());
			}
		}

		return false;
	}
}
