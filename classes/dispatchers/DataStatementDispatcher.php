<?php

namespace APP\plugins\generic\dataverse\classes\dispatchers;

use PKP\plugins\Hook;
use APP\core\Application;
use APP\template\TemplateManager;
use APP\pages\submission\SubmissionHandler;
use APP\plugins\generic\dataverse\classes\facades\Repo;
use APP\plugins\generic\dataverse\classes\dispatchers\DataverseDispatcher;
use APP\plugins\generic\dataverse\classes\services\DataStatementService;
use APP\plugins\generic\dataverse\classes\components\forms\DataStatementForm;

class DataStatementDispatcher extends DataverseDispatcher
{
    public function registerHooks(): void
    {
        Hook::add('TemplateManager::display', [$this, 'addDataStatementResources']);
        Hook::add('TemplateManager::display', [$this, 'addToDetailsStep']);
        Hook::add('Schema::get::publication', [$this, 'addDataStatementToPublicationSchema']);
        // Hook::add('Publication::validate', [$this, 'validateDataStatementProps']);
        // Hook::add('Templates::Preprint::Details', [$this, 'viewDataStatement']);
        // Hook::add('Templates::Article::Details', [$this, 'viewDataStatement']);
    }

    public function addDataStatementResources(string $hookName, array $params): bool
    {
        $templateMgr = $params[0];
        $template = $params[1];

        if ($template == 'frontend/pages/preprint.tpl' or $template == 'frontend/pages/article.tpl') {
            $templateMgr->addStyleSheet(
                'dataStatementList',
                $this->plugin->getPluginFullPath() . '/styles/dataStatementList.css',
                ['contexts' => ['frontend']]
            );

            return false;
        }

        if ($template != 'submission/form/index.tpl') {
            return false;
        }

        $templateMgr->addStyleSheet(
            'dataStatement',
            $this->plugin->getPluginFullPath() . '/styles/dataStatement.css',
            ['contexts' => ['backend']]
        );

        $templateMgr->setConstants([
            'DATA_STATEMENT_TYPE_IN_MANUSCRIPT',
            'DATA_STATEMENT_TYPE_REPO_AVAILABLE',
            'DATA_STATEMENT_TYPE_DATAVERSE_SUBMITTED',
            'DATA_STATEMENT_TYPE_ON_DEMAND',
            'DATA_STATEMENT_TYPE_PUBLICLY_UNAVAILABLE',
        ]);

        return false;
    }

    public function addToDetailsStep(string $hookName, array $params)
    {
        $request = Application::get()->getRequest();
        $context = $request->getContext();
        $templateMgr = $params[0];

        if ($request->getRequestedPage() !== 'submission' || $request->getRequestedOp() === 'saved') {
            return false;
        }

        $submission = $request
            ->getRouter()
            ->getHandler()
            ->getAuthorizedContextObject(Application::ASSOC_TYPE_SUBMISSION);

        if (!$submission || !$submission->getData('submissionProgress')) {
            return false;
        }

        $templateMgr->addJavaScript(
            'dataStatementForm',
            $this->plugin->getPluginFullPath() . '/js/ui/components/DataStatementForm.js',
            [
                'priority' => TemplateManager::STYLE_SEQUENCE_LAST,
                'contexts' => ['backend']
            ]
        );

        $templateMgr->addJavaScript(
            'field-controlled-vocab-url',
            $this->plugin->getPluginFullPath() . '/js/ui/components/FieldControlledVocabUrl.js',
            [
                'priority' => TemplateManager::STYLE_SEQUENCE_LAST,
                'contexts' => ['backend']
            ]
        );

        $publication = $submission->getLatestPublication();
        $publicationEndpoint = 'submissions/' . $submission->getId() . '/publications/' . $publication->getId();
        $saveFormUrl = $request->getDispatcher()->url($request, Application::ROUTE_API, $context->getPath(), $publicationEndpoint);
        $dataStatementForm = new DataStatementForm($saveFormUrl, $publication, 'submission');

        $steps = $templateMgr->getState('steps');
        $steps = array_map(function ($step) use ($dataStatementForm) {
            if ($step['id'] === 'details') {
                $step['sections'][] = [
                    'id' => 'dataStatement',
                    'name' => __('plugins.generic.dataverse.dataStatement.title'),
                    'description' => __('plugins.generic.dataverse.dataStatement.description'),
                    'type' => SubmissionHandler::SECTION_TYPE_FORM,
                    'form' => $dataStatementForm->getConfig(),
                ];
            }
            return $step;
        }, $steps);

        $templateMgr->setState(['steps' => $steps]);

        return false;
    }

    public function addDataStatementToPublicationSchema(string $hookName, array $args): bool
    {
        $schema = &$args[0];

        $schema->properties->dataStatementTypes = (object) [
            'type' => 'array',
            'items' => (object) [
                'type' => 'integer',
            ]
        ];

        $schema->properties->dataStatementUrls = (object) [
            'type' => 'array',
            'items' => (object) [
                'type' => 'string',
            ]
        ];

        $schema->properties->dataStatementReason = (object) [
            'type' => 'string',
            'multilingual' => true,
            'validation' => ['nullable']
        ];

        return false;
    }

    private function isValidStepForm(int $step, &$stepForm): bool
    {
        if ($step !== 1 || !$stepForm->validate()) {
            return false;
        }

        if (empty($stepForm->getData('dataStatementTypes'))) {
            $stepForm->addError(
                'dataStatementTypes',
                __('plugins.generic.dataverse.dataStatement.required')
            );
            $stepForm->addErrorField('dataStatementTypes');
            return false;
        }

        if (in_array(DATA_STATEMENT_TYPE_REPO_AVAILABLE, $stepForm->getData('dataStatementTypes'))) {
            if(empty($stepForm->getData('keywords')['dataStatementUrls'])) {
                $stepForm->addError(
                    'dataStatementUrls',
                    __('plugins.generic.dataverse.dataStatement.repoAvailable.urls.required')
                );
                $stepForm->addErrorField('dataStatementUrls');
                return false;
            } else {
                foreach($stepForm->getData('keywords')['dataStatementUrls'] as $dataStatementUrl) {
                    if(!$this->inputIsURL($dataStatementUrl)) {
                        $stepForm->addError(
                            'dataStatementUrls',
                            __('plugins.generic.dataverse.dataStatement.repoAvailable.urls.urlFormat')
                        );
                        $stepForm->addErrorField('dataStatementUrls');
                        $stepForm->setData('keywords', null);
                        return false;
                    }
                }
            }
        }

        return true;
    }

    private function createDataStatementParams($stepForm): array
    {
        $dataStatementTypes = $stepForm->getData('dataStatementTypes');
        $dataStatementUrls = null;
        $dataStatementReason = null;

        if (in_array(DATA_STATEMENT_TYPE_REPO_AVAILABLE, $dataStatementTypes)) {
            $dataStatementUrls = $stepForm->getData('keywords')['dataStatementUrls'];
        }

        if (in_array(DATA_STATEMENT_TYPE_PUBLICLY_UNAVAILABLE, $dataStatementTypes)) {
            $dataStatementReason = $stepForm->getData('dataStatementReason');
        }

        return [
            'dataStatementTypes' => $dataStatementTypes,
            'dataStatementUrls' => $dataStatementUrls,
            'dataStatementReason' => $dataStatementReason
        ];
    }

    private function inputIsURL(string $input): bool
    {
        $urlPattern = '/^(https?:\/\/)?[a-z0-9\-]+(\.[a-z0-9\-]+)+([\/?#].*)?$/i';
        return preg_match($urlPattern, $input) === 1;
    }

    public function validateDataStatementProps(string $hookName, array $args): bool
    {
        $errors = &$args[0];
        $props = $args[2];

        if (!isset($props['dataStatementTypes'])) {
            return false;
        }

        if (empty($errors)) {
            $errors = [];
        }

        if (empty($props['dataStatementTypes'])) {
            $errors['dataStatementTypes'] = [__('plugins.generic.dataverse.dataStatement.required')];
        }

        if (
            in_array(DATA_STATEMENT_TYPE_REPO_AVAILABLE, $props['dataStatementTypes'])
            && empty($props['dataStatementUrls'])
        ) {
            $errors['dataStatementUrls'] = [__('plugins.generic.dataverse.dataStatement.repoAvailable.urls.required')];
        }

        return false;
    }

    public function viewDataStatement(string $hookName, array $params): bool
    {
        $templateMgr = &$params[1];
        $output = &$params[2];

        $dataStatementService = new DataStatementService();
        $allDataStatementTypes = $dataStatementService->getDataStatementTypes();
        unset($allDataStatementTypes[DATA_STATEMENT_TYPE_DATAVERSE_SUBMITTED]);

        $templateMgr->assign('allDataStatementTypes', $allDataStatementTypes);

        $output .= $templateMgr->fetch($this->plugin->getTemplateResource('listDataStatement.tpl'));

        return false;
    }
}
