import '../support/commands';

const adminUser = Cypress.env('adminUser');
const adminPassword = Cypress.env('adminPassword');
const serverName = Cypress.env('serverName');
const serverPath = Cypress.env('serverPath') || 'publicknowledge';

let submissionData = {
    submitterRole: 'Author',
    title: 'The History of Coffee',
    abstract: 'A descriptive text',
    keywords: ['Documentary'],
    files: [
        {
            galleyLabel: 'CSV',
            file: 'dummy.pdf',
            fileName: 'Data Table.pdf',
        },
    ],
    additionalAuthors: [
        {
            givenName: 'Íris',
            familyName: 'Castanheiras',
            email: 'iris@lepidus.com.br',
            affiliation: 'Preprints da Lepidus',
            country: 'Argentina',
        },
    ],
};

describe('Deposit Draft Dataset', function() {
    it('Dataverse Plugin Configuration', function() {
        cy.login(adminUser, adminPassword);
        cy.get('a:contains(' + adminUser + '):visible').click();
        cy.get('a:contains("Dashboard"):visible').click();
        cy.configureDataversePlugin();
    });

    it('Creates a moderator user', function() {
        cy.login(adminUser, adminPassword);
        cy.get('a:contains(' + adminUser + '):visible').click();
        cy.get('a:contains("Dashboard"):visible').click();
        cy.get('a:contains("Users & Roles")').click();

        const manager = {
            username: 'hermesf',
            password: 'hermesfhermesf',
            email: 'hermesf@mailinator.com',
            givenName: 'Hermes',
            familyName: 'Fernandes',
            country: 'Brazil',
            affiliation: 'Dataverse Project',
            roles: ['Preprint Server manager'],
        };

        cy.createUser(manager);
        cy.logout();
    });

    it('Create Submission', function() {
        cy.login(adminUser, adminPassword);
        cy.get('a')
            .contains(adminUser)
            .click();
        cy.get('a')
            .contains('Dashboard')
            .click();
        cy.get('.app__nav a')
            .contains('Website')
            .click();
        cy.get('button[id="plugins-button"]').click();
        cy.get(
            '#component-grid-settings-plugins-settingsplugingrid-category-generic-row-dataverseplugin > :nth-child(3) [type="checkbox"]'
        ).check();
        cy.wait(2000);
        cy.get(
            '#component-grid-settings-plugins-settingsplugingrid-category-generic-row-dataverseplugin > :nth-child(3) [type="checkbox"]'
        ).should('be.checked');
        cy.get('.app__nav a')
            .contains('Submissions')
            .click();

        cy.DataverseCreateSubmission(submissionData);
    });
});

describe('Edit Dataset Metadata Draft', function() {
    it('Check dataset metadata form exists', function() {
        cy.login('hermesf', 'hermesfhermesf');
        cy.visit(
            'index.php/' + serverPath + '/workflow/access/' + submissionData.id
        );
        cy.get('button[aria-controls="publication"]').click();
        cy.get('button[aria-controls="datasetTab"]').click();
        cy.get('div[aria-labelledby="dataset_metadata-button"] > form').should(
            'be.visible'
        );
    });

    it('Check dataset metadata edit is enabled when preprint is unpublished', function() {
        cy.login('hermesf', 'hermesfhermesf');
        cy.visit(
            'index.php/' + serverPath + '/workflow/access/' + submissionData.id
        );
        cy.get('button[aria-controls="publication"]').click();
        cy.get('button[aria-controls="datasetTab"]').click();
        cy.get('div[aria-labelledby="dataset_metadata-button"] > form').should(
            'be.visible'
        );
        cy.get('.pkpPublication__status span').contains('Unposted');
        cy.get(
            'div[aria-labelledby="dataset_metadata-button"] > form button[label="Save"]'
        ).should('not.be.disabled');
    });

    it('Change dataset metadata if preprint is unpublished', function() {
        cy.login('hermesf', 'hermesfhermesf');
        cy.visit(
            'index.php/' + serverPath + '/workflow/access/' + submissionData.id
        );
        cy.get('button[aria-controls="publication"]').click();
        cy.get('button[aria-controls="datasetTab"]').click();
        cy.get('div[aria-labelledby="dataset_metadata-button"] > form').should(
            'be.visible'
        );
        cy.get('.pkpPublication__status span').contains('Unposted');
        cy.get(
            'div[aria-labelledby="dataset_metadata-button"] > form button[label="Save"]'
        ).should('not.be.disabled');
        cy.get('input[id^="datasetMetadata-datasetTitle-control"').clear();
        cy.get(
            'input[id^="datasetMetadata-datasetTitle-control"'
        ).type('The Rise of the Empire Machine', { delay: 0 });
        cy.get('div[id^="datasetMetadata-datasetDescription-control"').clear();
        cy.get(
            'div[id^="datasetMetadata-datasetDescription-control"'
        ).type('An example abstract', { delay: 0 });
        cy.get('#datasetMetadata-datasetKeywords-control').type(
            'Modern History',
            {
                delay: 0,
            }
        );
        cy.wait(500);
        cy.get('#datasetMetadata-datasetKeywords-control').type('{enter}', {
            delay: 0,
        });
        cy.get('#datasetMetadata-datasetSubject-control').select(
            'Computer and Information Science'
        );
        cy.get(
            'div[aria-labelledby="dataset_metadata-button"] > form button[label="Save"]'
        ).click();
        cy.wait(5000);
    });

    it('Check dataset metadata has been changed', function() {
        cy.login('hermesf', 'hermesfhermesf');
        cy.visit(
            'index.php/' + serverPath + '/workflow/access/' + submissionData.id
        );
        cy.get('button[aria-controls="publication"]').click();
        cy.get('button[aria-controls="datasetTab"]').click();
        cy.get('div[aria-labelledby="dataset_metadata-button"] > form').should(
            'be.visible'
        );
        cy.get('input[id^="datasetMetadata-datasetTitle-control"').should(
            'have.value',
            'The Rise of the Empire Machine'
        );
        cy.get(
            'div[id^="datasetMetadata-datasetDescription-control"] > p'
        ).contains('An example abstract');
        cy.get('#datasetMetadata-datasetKeywords-selected').contains(
            'Modern History'
        );
        cy.get('#datasetMetadata-datasetSubject-control').should(
            'have.value',
            'Computer and Information Science'
        );
    });

    it('Check update event was registered in activity log', function() {
        cy.login('hermesf', 'hermesfhermesf');
        cy.visit(
            'index.php/' + serverPath + '/workflow/access/' + submissionData.id
        );
        cy.get('button[aria-controls="publication"]').click();
        cy.contains('Activity Log').click();
        cy.get('#submissionHistoryGridContainer tbody tr:first td').should(
            ($cell) => {
                expect($cell[1]).to.contain('Hermes Fernandes');
                expect($cell[2]).to.contain('Research data metadata updated');
            }
        );
    });

    it('Removes keyword metadata from dataset', function() {
        cy.login('hermesf', 'hermesfhermesf');
        cy.visit(
            'index.php/' + serverPath + '/workflow/access/' + submissionData.id
        );
        cy.get('button[aria-controls="publication"]').click();
        cy.get('button[aria-controls="datasetTab"]').click();
        cy.get('div[aria-labelledby="dataset_metadata-button"] > form').should(
            'be.visible'
        );
        cy.get('.pkpPublication__status span').contains('Unposted');
        cy.get(
            'div[aria-labelledby="dataset_metadata-button"] > form button[label="Save"]'
        ).should('not.be.disabled');
        cy.get('span:contains(Modern History) button').click();
        cy.get(
            'div[aria-labelledby="dataset_metadata-button"] > form button[label="Save"]'
        ).click();
        cy.wait(3000);
    });

    it('Check keyword metadata has empty', function() {
        cy.login('hermesf', 'hermesfhermesf');
        cy.visit(
            'index.php/' + serverPath + '/workflow/access/' + submissionData.id
        );
        cy.get('button[aria-controls="publication"]').click();
        cy.get('button[aria-controls="datasetTab"]').click();
        cy.get('div[aria-labelledby="dataset_metadata-button"] > form').should(
            'be.visible'
        );
        cy.get('#datasetMetadata-datasetKeywords-selected').should(
            'not.include.text',
            'Modern History'
        );
    });

    it('Adds keyword metadata to dataset', function() {
        cy.login('hermesf', 'hermesfhermesf');
        cy.visit(
            'index.php/' + serverPath + '/workflow/access/' + submissionData.id
        );
        cy.get('button[aria-controls="publication"]').click();
        cy.get('button[aria-controls="datasetTab"]').click();
        cy.get('div[aria-labelledby="dataset_metadata-button"] > form').should(
            'be.visible'
        );
        cy.get('.pkpPublication__status span').contains('Unposted');
        cy.get(
            'div[aria-labelledby="dataset_metadata-button"] > form button[label="Save"]'
        ).should('not.be.disabled');
        cy.get('#datasetMetadata-datasetKeywords-control').type('Documentary', {
            delay: 0,
        });
        cy.wait(500);
        cy.get('#datasetMetadata-datasetKeywords-control').type('{enter}', {
            delay: 0,
        });
        cy.get(
            'div[aria-labelledby="dataset_metadata-button"] > form button[label="Save"]'
        ).click();
        cy.wait(3000);
    });

    it('Check keyword metadata has value', function() {
        cy.login('hermesf', 'hermesfhermesf');
        cy.visit(
            'index.php/' + serverPath + '/workflow/access/' + submissionData.id
        );
        cy.get('button[aria-controls="publication"]').click();
        cy.get('button[aria-controls="datasetTab"]').click();
        cy.get('div[aria-labelledby="dataset_metadata-button"] > form').should(
            'be.visible'
        );
        cy.get('#datasetMetadata-datasetKeywords-selected').contains(
            'Documentary'
        );
    });
});

describe('Edit Draft Dataset Files', function() {
    it('Check dataset files list exists', function() {
        cy.login('hermesf', 'hermesfhermesf');
        cy.visit(
            'index.php/' + serverPath + '/workflow/access/' + submissionData.id
        );
        cy.get('button[aria-controls="publication"]').click();
        cy.get('button[aria-controls="datasetTab"]').click();
        cy.get('button[aria-controls="dataset_files"]').click();
        cy.get('#datasetFiles').should('be.visible');
        cy.get('#datasetFiles .listPanel__items').contains('Data Table.pdf');
    });

    it('Adds file to dataset', function() {
        cy.login('hermesf', 'hermesfhermesf');
        cy.visit(
            'index.php/' + serverPath + '/workflow/access/' + submissionData.id
        );
        cy.get('button[aria-controls="publication"]').click();
        cy.get('button[aria-controls="datasetTab"]').click();
        cy.get('button[aria-controls="dataset_files"]').click();
        cy.get('button')
            .contains('Add research data')
            .click();
        cy.fixture('dummy.pdf', 'base64').then((fileContent) => {
            cy.get('#datasetFileForm-datasetFile-hiddenFileId').upload({
                fileContent,
                fileName: 'riseOfEmpireMachine.pdf',
                mimeType: 'application/pdf',
                encoding: 'base64',
            });
        });
        cy.get('input[name="termsOfUse"').check();
        cy.get('[data-modal="fileForm"] button:contains("Save")').click();
        cy.get('#datasetFiles .listPanel__items').contains(
            'riseOfEmpireMachine.pdf'
        );
        cy.get('#datasetTab-button .pkpBadge').contains('2');
    });

    it('Delete Dataset file', function() {
        cy.login('hermesf', 'hermesfhermesf');
        cy.visit(
            'index.php/' + serverPath + '/workflow/access/' + submissionData.id
        );
        cy.get('button[aria-controls="publication"]').click();
        cy.get('button[aria-controls="datasetTab"]').click();
        cy.get('button[aria-controls="dataset_files"]').click();
        cy.get(
            '.listPanel__item:contains(riseOfEmpireMachine.pdf) button:contains(Delete)'
        ).click();
        cy.get('#datasetFiles .listPanel__items').contains(
            'riseOfEmpireMachine.pdf'
        );
        cy.get('[data-modal="delete"] button:contains(Yes)').click();
        cy.waitJQuery();
        cy.get('#datasetFiles .listPanel__items').should(
            'not.include.text',
            'riseOfEmpireMachine.pdf'
        );
        cy.get('#datasetTab-button .pkpBadge').contains('1');
    });
});

describe('Delete draft dataset', function() {
    it('Check draft dataset button delete', function() {
        cy.login('hermesf', 'hermesfhermesf');
        cy.get('a')
            .contains('hermesf')
            .click();
        cy.get('a')
            .contains('Dashboard')
            .click();
        cy.visit(
            'index.php/' + serverPath + '/workflow/access/' + submissionData.id
        );
        cy.get('button[aria-controls="publication"]').click();
        cy.get('button[aria-controls="datasetTab"]').click();
        cy.get('button')
            .contains('Delete research data')
            .should('be.visible');
    });

    it('Delete draft dataset', function() {
        cy.login('hermesf', 'hermesfhermesf');
        cy.get('a')
            .contains('hermesf')
            .click();
        cy.get('a')
            .contains('Dashboard')
            .click();
        cy.visit(
            'index.php/' + serverPath + '/workflow/access/' + submissionData.id
        );
        cy.get('button[aria-controls="publication"]').click();
        cy.get('button[aria-controls="datasetTab"]').click();
        cy.get('button')
            .contains('Delete research data')
            .click();
        cy.get('[data-modal="delete"] button')
            .contains('Yes')
            .click();
        cy.contains('No research data transferred.');
    });

    it('Check update event was registered in activity log', function() {
        cy.login('hermesf', 'hermesfhermesf');
        cy.visit(
            'index.php/' + serverPath + '/workflow/access/' + submissionData.id
        );
        cy.contains('Activity Log').click();
        cy.get('#submissionHistoryGridContainer tbody tr:first td').should(
            ($cell) => {
                expect($cell[1]).to.contain('Hermes Fernandes');
                expect($cell[2]).to.contain('Research data deleted');
            }
        );
    });
});