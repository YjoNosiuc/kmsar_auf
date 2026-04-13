<?php

namespace App\Policies;

use App\Models\Document;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

class DocumentPolicy
{
    /**
     * Download / view file contents: user must pass ResearchPolicy::view on the parent.
     */
    public function view(User $user, Document $document): bool
    {
        $research = $document->research;
        if ($research === null) {
            return false;
        }

        return Gate::forUser($user)->allows('view', $research);
    }
}
