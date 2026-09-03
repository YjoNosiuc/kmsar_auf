<?php

namespace App\Policies;

use App\Models\Research;
use App\Models\User;
use App\Support\ResearchStatus;

class ResearchPolicy
{
    public function viewAny(User $user): bool
    {
        if ($user->hasRole('registrar')) {
            return false;
        }

        return $user->can('research.view_own')
            || $user->can('research.view_college')
            || $user->can('research.view_all');
    }

    public function view(User $user, Research $research): bool
    {
        if ($user->hasRole('registrar')) {
            return $user->can('research.view_all') && $research->status === ResearchStatus::RESEARCH_ACCEPTED;
        }

        if ($user->hasRole('super_admin')) {
            return true;
        }

        if (ResearchStatus::isPreSubmission((string) $research->status)
            && $user->hasAnyRole(['ovpri_admin', 'cdaic_admin', 'college_dean', 'unit_head'])) {
            return false;
        }

        if ($user->hasAnyRole(['ovpri_admin', 'cdaic_admin'])) {
            return true;
        }

        if ($user->can('research.view_all')) {
            return true;
        }

        if ($user->hasAnyRole(['college_dean', 'unit_head']) || $user->can('research.view_college')) {
            if (ResearchStatus::isPreSubmission((string) $research->status)) {
                return false;
            }

            return (int) $research->mother_college_id === (int) $user->college_id;
        }

        if (! $user->can('research.view_own')) {
            return false;
        }

        return $this->isPrimaryOrCoAuthor($user, $research);
    }

    public function create(User $user): bool
    {
        return $user->can('research.create');
    }

    public function update(User $user, Research $research): bool
    {
        if (! ResearchStatus::isFullyEditable((string) $research->status)) {
            return false;
        }

        if ((int) $research->primary_author_id === (int) $user->id && $user->can('research.update')) {
            return true;
        }

        return $this->coAuthorCanEdit($user, $research);
    }

    public function manageRegistrationWizard(User $user, Research $research): bool
    {
        if (! ResearchStatus::isFullyEditable((string) $research->status)) {
            return false;
        }

        if ((int) $research->primary_author_id === (int) $user->id) {
            return $user->can('research.create') || $user->can('research.update');
        }

        return $this->coAuthorCanEdit($user, $research);
    }

    public function updateOutcomes(User $user, Research $research): bool
    {
        if (! ResearchStatus::isOutcomeEditable((string) $research->status)) {
            return false;
        }

        if ((int) $research->primary_author_id === (int) $user->id && $user->can('research.update')) {
            return true;
        }

        return $this->coAuthorCanEdit($user, $research);
    }

    /**
     * @deprecated Alias for submitCompletion policy checks.
     */
    public function updateProgress(User $user, Research $research): bool
    {
        return $this->submitCompletion($user, $research);
    }

    public function submitCompletion(User $user, Research $research): bool
    {
        if (! ResearchStatus::canSubmitCompletion((string) $research->status)) {
            return false;
        }

        if ((int) $research->primary_author_id === (int) $user->id && $user->can('research.update')) {
            return true;
        }

        return $this->coAuthorCanEdit($user, $research);
    }

    public function uploadDocuments(User $user, Research $research): bool
    {
        if ($this->update($user, $research)) {
            return true;
        }

        if (ResearchStatus::isDocumentsEditable((string) $research->status)) {
            if ((int) $research->primary_author_id === (int) $user->id && $user->can('research.update')) {
                return true;
            }

            return $this->coAuthorCanEdit($user, $research);
        }

        return $this->updateOutcomes($user, $research);
    }

    public function manageRegistrationDocuments(User $user, Research $research): bool
    {
        if (! $this->uploadDocuments($user, $research)) {
            return false;
        }

        $status = (string) $research->status;

        return ResearchStatus::isFullyEditable($status)
            || ResearchStatus::isDocumentsEditable($status);
    }

    public function submit(User $user, Research $research): bool
    {
        if (! $user->can('research.submit')) {
            return false;
        }

        if (! ResearchStatus::isPreSubmission((string) $research->status)) {
            return false;
        }

        return $this->update($user, $research);
    }

    public function revise(User $user, Research $research): bool
    {
        return $this->resubmitInitial($user, $research) || $this->resubmitFinal($user, $research);
    }

    public function resubmitInitial(User $user, Research $research): bool
    {
        if (! $user->can('research.revise')) {
            return false;
        }

        if ($research->status !== ResearchStatus::INITIAL_REJECTED) {
            return false;
        }

        if ((int) $research->primary_author_id === (int) $user->id) {
            return true;
        }

        return $this->coAuthorCanEdit($user, $research);
    }

    public function resubmitFinal(User $user, Research $research): bool
    {
        if (! $user->can('research.revise')) {
            return false;
        }

        if ($research->status !== ResearchStatus::FINAL_REJECTED) {
            return false;
        }

        if ((int) $research->primary_author_id === (int) $user->id) {
            return true;
        }

        return $this->coAuthorCanEdit($user, $research);
    }

    private function isPrimaryOrCoAuthor(User $user, Research $research): bool
    {
        if ((int) $research->primary_author_id === (int) $user->id) {
            return true;
        }

        return $research->researchAuthors()->matchingUser($user)->exists();
    }

    private function coAuthorCanEdit(User $user, Research $research): bool
    {
        return $research->researchAuthors()->matchingUser($user)->where('can_edit', true)->exists();
    }
}
