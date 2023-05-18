<?php

use PKP\components\forms\FieldControlledVocab;
use PKP\components\forms\FieldOptions;
use PKP\components\forms\FieldText;
use PKP\components\forms\FormComponent;

define('FORM_DATA_STATEMENT', 'dataStatement');

class DataStatementForm extends FormComponent
{
    public $id = FORM_DATA_STATEMENT;

    public $method = 'PUT';

    public function __construct($action, $locales, $publication)
    {
        $this->action = $action;
        $this->locales = $locales;

        import('plugins.generic.dataverse.classes.services.DataStatementService');
        $dataStatementService = new DataStatementService();
        $dataStatementTypes = $dataStatementService->getDataStatementTypes();
        unset($dataStatementTypes[DATA_STATEMENT_TYPE_DATAVERSE_SUBMITTED]);

        $dataStatementOptions = array_map(function ($value, $label) {
            return [
                'value' => $value,
                'label' => $label,
            ];
        }, array_keys($dataStatementTypes), array_values($dataStatementTypes));

        $request = Application::get()->getRequest();
        $contextPath = $request->getContext()->getPath();
        $vocabApiUrl = $request->getDispatcher()->url($request, ROUTE_API, $contextPath, 'vocabs');

        $this->addField(new FieldOptions('dataStatementTypes', [
            'label' => __('plugins.generic.dataverse.dataStatement.title'),
            'isRequired' => true,
            'value' => $publication->getData('dataStatementTypes') ?? [],
            'options' => $dataStatementOptions,
        ]))
        ->addField(new FieldControlledVocab('dataStatementUrls', [
            'label' => __('plugins.generic.dataverse.dataStatement.repoAvailable.urls'),
            'description' => __('plugins.generic.dataverse.dataStatement.repoAvailable.urls.description'),
            'apiUrl' => $vocabApiUrl,
            'selected' => $publication->getData('dataStatementUrls') ?? [],
        ]))
        ->addField(new FieldText('dataStatementReason', [
            'label' => __('plugins.generic.dataverse.dataStatement.publiclyUnavailable.reason'),
            'isRequired' => true,
            'isMultilingual' => true,
            'value' => $publication->getData('dataStatementReason'),
            'size' => 'large',
        ]));
    }
}
