<?php

import('plugins.generic.dataverse.tests.dataverseAPI.endpoints.DataverseEndpointsTestCase');
import('plugins.generic.dataverse.classes.dataverseAPI.endpoints.SWORDAPIEndpoints');

class SWORDAPIEndpointsTest extends DataverseEndpointsTestCase
{
    protected function getDataverseCredentialsData(): array
    {
        return [
            'dataverseUrl' => 'https://demo.dataverse.org/dataverse/example',
            'apiToken' => 'randomToken',
            'termsOfUse' => [
                'en_US' => 'https://demo.dataverse.org/terms-of-use'
            ]
        ];
    }

    protected function createDataverseEndpoints(DataverseInstallation $installation): DataverseEndpoints
    {
        return new SWORDAPIEndpoints($installation);
    }

    public function testReturnsCorrectDataverseServiceDocumentUrl(): void
    {
        $expectedServiceDocumentUrl = 'https://demo.dataverse.org/dvn/api/data-deposit/v1.1/swordv2/service-document';
        $serviceDocumentUrl = $this->endpoints->getDataverseServiceDocumentUrl();

        $this->assertEquals($expectedServiceDocumentUrl, $serviceDocumentUrl);
    }

    public function testReturnsCorrectCollectionUrl(): void
    {
        $expectedCollectionUrl = 'https://demo.dataverse.org/dvn/api/data-deposit/v1.1/swordv2/collection/dataverse/example';
        $collectionUrl = $this->endpoints->getDataverseCollectionUrl();

        $this->assertEquals($expectedCollectionUrl, $collectionUrl);
    }

    public function testReturnsCorrectDataverseEditUrl(): void
    {
        $expectedDataverseEditUrl = 'https://demo.dataverse.org/dvn/api/data-deposit/v1.1/swordv2/edit/dataverse/example';
        $dataverseEditUrl = $this->endpoints->getDataverseEditUrl();

        $this->assertEquals($expectedDataverseEditUrl, $dataverseEditUrl);
    }

    public function testReturnsCorrectDatasetEditUrl(): void
    {
        $persistentId = 'doi:10.12345/ABC/DFGHIJ';
        $expectedDatasetEditUrl = 'https://demo.dataverse.org/dvn/api/data-deposit/v1.1/swordv2/edit/study/' . $persistentId;
        $datasetEditUrl = $this->endpoints->getDatasetEditUrl($persistentId);

        $this->assertEquals($expectedDatasetEditUrl, $datasetEditUrl);
    }

    public function testReturnsCorrectDatasetEditMediaUrl(): void
    {
        $persistentId = 'doi:10.12345/ABC/DFGHIJ';
        $expectedDatasetEditMediaUrl = 'https://demo.dataverse.org/dvn/api/data-deposit/v1.1/swordv2/edit-media/study/' . $persistentId;
        $datasetEditMediaUrl = $this->endpoints->getDatasetEditMediaUrl($persistentId);

        $this->assertEquals($expectedDatasetEditMediaUrl, $datasetEditMediaUrl);
    }

    public function testReturnsCorrectDatasetStatementUrl(): void
    {
        $persistentId = 'doi:10.12345/ABC/DFGHIJ';
        $expectedDatasetStatementUrl = 'https://demo.dataverse.org/dvn/api/data-deposit/v1.1/swordv2/statement/study/' . $persistentId;
        $datasetStatementUrl = $this->endpoints->getDatasetStatementUrl($persistentId);

        $this->assertEquals($expectedDatasetStatementUrl, $datasetStatementUrl);
    }

    public function testReturnsCorrectDatasetFileUrl(): void
    {
        $fileId = 10101;
        $expectedDatasetFileUrl = 'https://demo.dataverse.org/dvn/api/data-deposit/v1.1/swordv2/edit-media/file/' . $fileId;
        $datasetFileUrl = $this->endpoints->getDatasetFileUrl($fileId);

        $this->assertEquals($expectedDatasetFileUrl, $datasetFileUrl);
    }
}
