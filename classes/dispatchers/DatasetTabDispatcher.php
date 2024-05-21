<?php

namespace APP\plugins\generic\dataverse\classes\dispatchers;

use PKP\plugins\Hook;
use APP\core\Application;
use APP\template\TemplateManager;
use APP\submission\Submission;
use PKP\db\DAORegistry;
use PKP\security\Role;
use APP\plugins\generic\dataverse\classes\dispatchers\DataverseDispatcher;
use APP\plugins\generic\dataverse\classes\dataverseStudy\DataverseStudy;
use APP\plugins\generic\dataverse\classes\entities\Dataset;
use APP\plugins\generic\dataverse\classes\components\forms\DatasetMetadataForm;
use APP\plugins\generic\dataverse\dataverseAPI\DataverseClient;
use APP\plugins\generic\dataverse\classes\factories\SubmissionDatasetFactory;
use APP\plugins\generic\dataverse\classes\facades\Repo;
use PKP\components\forms\FormComponent;

class DatasetTabDispatcher extends DataverseDispatcher
{
    protected function registerHooks(): void
    {
        // Hook::add('Template::Workflow::Publication', [$this, 'addResearchDataTab']);
        Hook::add('TemplateManager::display', [$this, 'loadResourcesToWorkflow']);
    }

    private function getSubmissionStudy(int $submissionId): ?DataverseStudy
    {
        return Repo::dataverseStudy()->getBySubmissionId($submission->getId());
    }

    public function addResearchDataTab(string $hookName, array $params): bool
    {
        $templateMgr = &$params[1];
        $output = &$params[2];

        $submission = $templateMgr->get_template_vars('submission');

        $content = $this->getDatasetTabContent($submission);

        $output .= sprintf(
            '<tab id="datasetTab" label="%s" :badge="researchDataCount">%s</tab>',
            __("plugins.generic.dataverse.researchData"),
            $templateMgr->fetch($content)
        );

        return false;
    }

    private function getDatasetTabContent(Submission $submission): string
    {
        $study = $this->getSubmissionStudy($submission->getId());

        if (is_null($study)) {
            return $this->plugin->getTemplateResource('datasetTab/noResearchData.tpl');
        }

        $dataverseClient = new DataverseClient();
        try {
            $dataset = $dataverseClient->getDatasetActions()->get($study->getPersistentId());
            return $this->plugin->getTemplateResource('datasetTab/datasetData.tpl');
        } catch (DataverseException $e) {
            if ($e->getCode() === 404) {
                DAORegistry::getDAO('DataverseStudyDAO')->deleteStudy($study);
            }

            error_log('Dataverse API error: ' . $e->getMessage());

            $templateMgr = TemplateManager::getManager(Application::get()->getRequest());
            $templateMgr->assign('errorMessage', $e->getMessage());
            return $this->plugin->getTemplateResource('datasetTab/researchDataError.tpl');
        }
    }

    public function loadResourcesToWorkflow(string $hookName, array $params): bool
    {
        $templateMgr = $params[0];
        $template = $params[1];

        if (
            $template != 'workflow/workflow.tpl'
            && $template != 'authorDashboard/authorDashboard.tpl'
        ) {
            return false;
        }

        $templateMgr->addStyleSheet(
            'datasetTab',
            $this->plugin->getPluginFullPath() . '/styles/datasetDataTab.css',
            ['contexts' => ['backend']]
        );

        /*$templateMgr->addJavaScript(
            'dataverseWorkflowPage',
            $this->plugin->getPluginFullPath() . '/js/DataverseWorkflowPage.js',
            [
                'priority' => TemplateManager::STYLE_SEQUENCE_LAST,
                'contexts' => ['backend']
            ]
        );

        $templateMgr->assign([
            'pageComponent' => 'DataverseWorkflowPage',
        ]);*/

        $submission = $templateMgr->getTemplateVars('submission');
        $study = $this->getSubmissionStudy($submission->getId());

        if (is_null($study)) {
            $this->setupResearchDataDeposit($submission);
            return false;
        }

        $this->setupResearchDataUpdate($submission, $study);

        return false;
    }

    private function setupResearchDataDeposit(Submission $submission): void
    {
        $request = Application::get()->getRequest();
        $templateMgr = TemplateManager::getManager($request);
        $context = $request->getContext();
        $user = $request->getUser();

        $metadataFormAction = $request->getDispatcher()->url($request, Application::ROUTE_API, $context->getPath(), 'datasets', null, null, ['submissionId' => $submission->getId()]);
        $datasetFilesApiUrl = $request
            ->getDispatcher()
            ->url($request, Application::ROUTE_API, $context->getPath(), 'draftDatasetFiles', null, null, ['submissionId' => $submission->getId(), 'userId' => $user->getId()]);

        $factory = new SubmissionDatasetFactory($submission);
        $dataset = $factory->getDataset();
        $draftDatasetFiles = Repo::draftDatasetFile()->getBySubmissionId($submission->getId());

        //Colocar download url aqui?
        $datasetFiles = array_map(function ($draftDatasetFile) {
            return $draftDatasetFile->getAllData();
        }, $draftDatasetFiles);
        ksort($datasetFiles);

        $this->initDatasetMetadataForm($templateMgr, $metadataFormAction, 'POST', $dataset);
        // $this->initDatasetFilesList($templateMgr, $datasetFilesApiUrl, $datasetFiles);
        // $this->initDatasetFileForm($templateMgr, $datasetFilesApiUrl);
    }

    private function setupResearchDataUpdate(Submission $submission, DataverseStudy $study): void
    {
        $request = Application::get()->getRequest();
        $templateMgr = TemplateManager::getManager($request);
        $context = $request->getContext();
        $dispatcher = $request->getDispatcher();
        $router = $request->getRouter();
        $userRoles = (array) $router->getHandler()->getAuthorizedContextObject(Application::ASSOC_TYPE_USER_ROLES);
        $configuration = DAORegistry::getDAO('DataverseConfigurationDAO')->get($context->getId());

        try {
            $dataverseClient = new DataverseClient();
            $dataset = $dataverseClient->getDatasetActions()->get($study->getPersistentId());
            $rootDataverseCollection = $dataverseClient->getDataverseCollectionActions()->getRoot();
            $dataverseCollection = $dataverseClient->getDataverseCollectionActions()->get();

            $datasetApiUrl = $dispatcher->url(
                $request,
                Application::ROUTE_API,
                $context->getPath(),
                'datasets/' . $study->getId()
            );
            $fileListApiUrl = $dispatcher->url(
                $request,
                Application::ROUTE_API,
                $context->getPath(),
                'datasets/' . $study->getId() . '/files',
                null,
                null,
                ['persistentId' => $study->getPersistentId()]
            );
            $fileFormAction = $dispatcher->url(
                $request,
                Application::ROUTE_API,
                $context->getPath(),
                'datasets/' . $study->getId() . '/file'
            );
            $datasetStatementUrl = $dispatcher->url(
                $request,
                Application::ROUTE_PAGE,
                null,
                'authorDashboard',
                'submission',
                $submission->getId(),
                null,
                '#publication/dataStatement'
            );

            $datasetFiles = array_map(function ($datasetFile) {
                return $datasetFile->getVars();
            }, $dataset->getFiles());

            $this->initDatasetMetadataForm($templateMgr, $datasetApiUrl, 'PUT', $dataset);
            // $this->initDatasetFilesList($templateMgr, $fileListApiUrl, $datasetFiles);
            // $this->initDatasetFileForm($templateMgr, $fileFormAction);

            // $deleteDatasetForm = $this->getDeleteDatasetForm($datasetApiUrl, $context);
            // $this->addComponent($templateMgr, $deleteDatasetForm);

            $templateMgr->setState([
                'dataset' => $dataset->getAllData(),
                'deleteDatasetLabel' => __('plugins.generic.dataverse.researchData.delete'),
                'confirmDeleteDatasetMessage' => __('plugins.generic.dataverse.modal.confirmDatasetDelete'),
                'publishDatasetLabel' => __('plugins.generic.dataverse.researchData.publish'),
                'confirmPublishDatasetMessage' => __('plugins.generic.dataverse.modal.confirmDatasetPublish', [
                    'serverName' => $rootDataverseCollection->getName(),
                    'serverUrl' => $configuration->getDataverseServerUrl(),
                ]),
                'datasetCitationUrl' => $dispatcher->url($request, Application::ROUTE_API, $context->getPath(), 'datasets/' . $study->getId() . '/citation'),
                'canSendEmail' => in_array(Role::ROLE_ID_MANAGER, $userRoles)
            ]);
        } catch (DataverseException $e) {
            error_log('Dataverse API error: ' . $e->getMessage());
        }
    }

    private function initDatasetMetadataForm(TemplateManager $templateMgr, string $action, string $method, Dataset $dataset): void
    {
        $datasetMetadataForm = new DatasetMetadataForm($action, $method, $dataset, 'workflow');
        $this->addComponent($templateMgr, $datasetMetadataForm);
    }

    private function initDatasetFilesList($templateMgr, $apiUrl, $items): void
    {
        $this->plugin->import('classes.components.listPanel.DatasetFilesListPanel');
        $datasetFilesListPanel = new DatasetFilesListPanel(
            'datasetFiles',
            __('plugins.generic.dataverse.researchData.files'),
            [
                'addFileLabel' => __('plugins.generic.dataverse.addResearchData'),
                'apiUrl' => $apiUrl,
                'items' => $items,
                'modalTitle' => __('plugins.generic.dataverse.modal.addFile.title'),
                'title' => __('plugins.generic.dataverse.researchData'),
            ]
        );

        $this->addComponent($templateMgr, $datasetFilesListPanel);

        $templateMgr->setState([
            'deleteDatasetFileLabel' => __('plugins.generic.dataverse.modal.deleteDatasetFile'),
            'confirmDeleteMessage' => __('plugins.generic.dataverse.modal.confirmDelete')
        ]);
    }

    public function initDatasetFileForm($templateMgr, $apiUrl): void
    {
        $request = Application::get()->getRequest();
        $context = $request->getContext();

        $this->plugin->import('classes.components.forms.DraftDatasetFileForm');
        $draftDatasetFileForm = new DraftDatasetFileForm($apiUrl, $context);

        $this->addComponent(
            $templateMgr,
            $draftDatasetFileForm,
            [
                'errors' => [
                    'termsOfUse' => [
                        __('plugins.generic.dataverse.termsOfUse.error')
                    ]
                ]
            ]
        );
    }

    public function getDeleteDatasetForm(
        string $apiUrl,
        Context $context,
        array $locales
    ): FormComponent {
        import('lib.pkp.classes.mail.MailTemplate');

        $mail = new MailTemplate('DATASET_DELETE_NOTIFICATION', null, $context, false);
        $mail->assignParams([
            'submissionTitle' => htmlspecialchars($submission->getLocalizedFullTitle()),
            'dataverseName' => $dataverseCollection->getName(),
            'dataStatementUrl' => $datasetStatementUrl,
        ]);
        $mail->replaceParams();

        $deleteDatasetForm = new FormComponent('deleteDataset', 'DELETE', $apiUrl, $locales);

        $deleteDatasetForm->addPage([
            'id' => 'default',
            'submitButton' => [
                'label' => __('plugins.generic.dataverse.researchData.delete.submitLabel'),
            ],
        ])->addGroup([
            'id' => 'default',
            'pageId' => 'default',
        ])->addField(new \PKP\components\forms\FieldRichTextarea('deleteMessage', [
            'label' => __('plugins.generic.dataverse.researchData.delete.emailNotification'),
            'value' => $mail->getBody(),
            'groupId' => 'default'
        ]));


        return $deleteDatasetForm;
    }

    private function addComponent(TemplateManager $templateMgr, $component, $args = []): void
    {
        $workflowComponents = $templateMgr->getState('components');
        $workflowComponents[$component->id] = $component->getConfig();

        if (!empty($args)) {
            foreach ($args as $prop => $value) {
                $workflowComponents[$component->id][$prop] = $value;
            }
        }

        $templateMgr->setState([
            'components' => $workflowComponents
        ]);
    }
}
