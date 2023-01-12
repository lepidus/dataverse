<?php

import('plugins.generic.dataverse.classes.adapters.SubmissionAdapter');
import('plugins.generic.dataverse.classes.adapters.AuthorAdapter');
import('plugins.generic.dataverse.classes.APACitation');

class SubmissionAdapterCreator
{
    public function createSubmissionAdapter(Submission $submission): SubmissionAdapter
    {
        $locale = $submission->getLocale();
        $publication = $submission->getCurrentPublication();
        $apaCitation = new APACitation();

        $id = $submission->getId();
        $title = $publication->getLocalizedData('title', $locale);
        $abstract = $publication->getLocalizedData('abstract', $locale);
        $subject = $submission->getData('datasetSubject');
        $keywords = $publication->getData('keywords')[$locale] ?? null;
        $citation = $apaCitation->getFormattedCitationBySubmission($submission);
        $authors = $this->retrieveAuthors($publication, $locale);
        $files = $this->retrieveFiles($id);
        $contact = $this->retrieveContact($publication);

        $adapter = new SubmissionAdapter();
        $adapter->setRequiredData($id, $title, $abstract, $subject, $keywords, $citation, $contact, $authors, $files);

        return $adapter;
    }

    private function retrieveAuthors(Publication $publication, string $locale): array
    {
        $authors =  $publication->getData('authors');
        $authorAdapters = [];

        foreach ($authors as $author) {
            $givenName = $author->getLocalizedGivenName($locale);
            $familyName = $author->getLocalizedFamilyName($locale);
            $affiliation = $author->getLocalizedData('affiliation', $locale);
            $email = $author->getData('email');
            $orcid = $author->getOrcid();
            $orcidNumber = null;

            if (preg_match('/.{4}-.{4}-.{4}-.{4}/', $orcid, $matches)) {
                $orcidNumber = $matches[0];
            }

            $affiliation = !is_null($affiliation) ? $affiliation : "";
            $email = !is_null($email) ? $email : "";

            $authorAdapters[] = new AuthorAdapter($givenName, $familyName, $affiliation, $email, $orcidNumber);
        }

        return $authorAdapters;
    }

    private function retrieveContact(Publication $publication): ?array
    {
        $primaryAuthor = $publication->getPrimaryAuthor();
        if (!empty($primaryAuthor)) {
            $locale = $primaryAuthor->getSubmissionLocale();
            $givenName = $primaryAuthor->getLocalizedGivenName($locale);
            $familyName = $primaryAuthor->getLocalizedFamilyName($locale);
            $email = $primaryAuthor->getEmail();
            $affiliation = $primaryAuthor->getLocalizedData('affiliation', $locale);
            return array(
                'name' => $familyName . ', ' . $givenName,
                'email' => $email,
                'affiliation' => $affiliation
            );
        } else {
            return null;
        }
    }

    private function retrieveFiles(int $submissionId): array
    {
        $draftDatasetFileDAO = DAORegistry::getDAO('DraftDatasetFileDAO');
        $draftDatasetFiles = $draftDatasetFileDAO->getBySubmissionId($submissionId);
        return $draftDatasetFiles;
    }
}
