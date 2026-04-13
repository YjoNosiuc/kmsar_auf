<?php

namespace App\Policies;

use App\Models\Research;
use App\Models\User;

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
            return $user->can('research.view_all') && $research->approval_stage === 'approved';
        }

        if ($user->can('research.view_all')) {
            return true;
        }

        if ($user->can('research.view_college')) {
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
        if (! in_array($research->approval_stage, ['draft', 'rejected'], true)) {
            return false;
        }

        if ((int) $research->primary_author_id === (int) $user->id && $user->can('research.update')) {
            return true;
        }

        return $this->coAuthorCanEdit($user, $research);
    }

    /**
     * Faculty may submit a progress update on fully approved research (restarts dean endorsement).
     */
    public function updateProgress(User $user, Research $research): bool
    {
        if ($research->approval_stage !== 'approved') {
            return false;
        }

        if ((int) $research->primary_author_id === (int) $user->id && $user->can('research.update')) {
            return true;
        }

        return $this->coAuthorCanEdit($user, $research);
    }

    public function submit(User $user, Research $research): bool
    {
        if (! $user->can('research.submit')) {
            return false;
        }

        return (int) $research->primary_author_id === (int) $user->id
            && $research->approval_stage === 'draft';
    }

    public function revise(User $user, Research $research): bool
    {
        if (! $user->can('research.revise')) {
            return false;
        }

        return (int) $research->primary_author_id === (int) $user->id
            && $research->approval_stage === 'rejected';
    }

    private function isPrimaryOrCoAuthor(User $user, Research $research): bool
    {
        if ((int) $research->primary_author_id === (int) $user->id) {
            return true;
        }

        return $research->researchAuthors()->where('user_id', $user->id)->exists();
    }

    private function coAuthorCanEdit(User $user, Research $research): bool
    {
        return $research->researchAuthors()
            ->where('user_id', $user->id)
            ->where('can_edit', true)
            ->exists();
    }
}
