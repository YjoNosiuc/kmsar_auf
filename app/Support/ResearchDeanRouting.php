<?php

namespace App\Support;

use App\Models\Research;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Dean approval routing is always keyed to research.mother_college_id,
 * not the primary author's home college.
 */
final class ResearchDeanRouting
{
    /**
     * @return list<int>
     */
    public static function deanUserIdsForCollege(int $collegeId): array
    {
        return User::query()
            ->where('college_id', $collegeId)
            ->whereHas('roles', fn (Builder $q) => $q->whereIn('name', ['college_dean', 'unit_head']))
            ->pluck('id')
            ->all();
    }

    /**
     * @return list<int>
     */
    public static function deanUserIdsFor(Research $research): array
    {
        return self::deanUserIdsForCollege((int) $research->mother_college_id);
    }

    /**
     * @return Collection<int, User>
     */
    public static function deanUsersFor(Research $research): Collection
    {
        $ids = self::deanUserIdsFor($research);

        if ($ids === []) {
            return collect();
        }

        return User::query()->whereIn('id', $ids)->get();
    }

    /**
     * Primary college dean for notification mail (first college_dean on mother college).
     */
    public static function primaryDeanFor(Research $research): ?User
    {
        return User::query()
            ->where('college_id', $research->mother_college_id)
            ->whereHas('roles', fn (Builder $q) => $q->where('name', 'college_dean'))
            ->first();
    }

    public static function deanMayActOnResearch(User $dean, Research $research): bool
    {
        if ($dean->hasRole('super_admin')) {
            return true;
        }

        if (! $dean->hasAnyRole(['college_dean', 'unit_head'])) {
            return false;
        }

        return (int) $dean->college_id === (int) $research->mother_college_id;
    }
}
