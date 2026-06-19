<?php

namespace App\Models;

use App\Support\TextNormalizer;
use Database\Factories\ResearchAuthorFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ResearchAuthor extends Model
{
    /** @use HasFactory<ResearchAuthorFactory> */
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'research_id',
        'user_id',
        'author_type',
        'employee_number',
        'first_name',
        'last_name',
        'middle_name',
        'suffix',
        'name',
        'college_id',
        'college_text',
        'program',
        'program_id',
        'affiliated_college_id',
        'institution',
        'email',
        'is_primary',
        'can_edit',
    ];

    protected function casts(): array
    {
        return [
            'is_primary' => 'boolean',
            'can_edit' => 'boolean',
        ];
    }

    protected function name(): Attribute
    {
        return Attribute::make(
            set: fn (?string $value) => TextNormalizer::upper($value),
        );
    }

    protected function firstName(): Attribute
    {
        return Attribute::make(
            set: fn (?string $value) => TextNormalizer::upperNullable($value),
        );
    }

    protected function lastName(): Attribute
    {
        return Attribute::make(
            set: fn (?string $value) => TextNormalizer::upperNullable($value),
        );
    }

    protected function middleName(): Attribute
    {
        return Attribute::make(
            set: fn (?string $value) => TextNormalizer::upperNullable($value),
        );
    }

    protected function suffix(): Attribute
    {
        return Attribute::make(
            set: fn (?string $value) => TextNormalizer::upperNullable($value),
        );
    }

    protected function employeeNumber(): Attribute
    {
        return Attribute::make(
            set: fn (?string $value) => TextNormalizer::upperNullable($value),
        );
    }

    protected function institution(): Attribute
    {
        return Attribute::make(
            set: fn (?string $value) => TextNormalizer::upperNullable($value),
        );
    }

    protected function collegeText(): Attribute
    {
        return Attribute::make(
            set: fn (?string $value) => TextNormalizer::upperNullable($value),
        );
    }

    public function research(): BelongsTo
    {
        return $this->belongsTo(Research::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function college(): BelongsTo
    {
        return $this->belongsTo(College::class);
    }

    public function program(): BelongsTo
    {
        return $this->belongsTo(Program::class);
    }

    public function affiliatedCollege(): BelongsTo
    {
        return $this->belongsTo(College::class, 'affiliated_college_id');
    }

    /**
     * Non-primary author rows that correspond to the given KMSAR user.
     *
     * @param  Builder<ResearchAuthor>  $query
     * @return Builder<ResearchAuthor>
     */
    public function scopeMatchingUser(Builder $query, User $user): Builder
    {
        $normalizedEmail = $user->email ? strtolower(trim($user->email)) : null;
        $normalizedEmployeeNumber = $user->employee_number
            ? TextNormalizer::upperNullable($user->employee_number)
            : null;
        $normalizedName = trim((string) $user->name) !== ''
            ? TextNormalizer::upper($user->name)
            : null;

        return $query
            ->where('is_primary', false)
            ->where(function (Builder $authorQ) use ($user, $normalizedEmail, $normalizedEmployeeNumber, $normalizedName) {
                $authorQ->where('user_id', $user->id);

                if ($normalizedEmail !== null) {
                    $authorQ->orWhereRaw('LOWER(email) = ?', [$normalizedEmail]);
                }

                if ($normalizedEmployeeNumber !== null) {
                    $authorQ->orWhere('employee_number', $normalizedEmployeeNumber);
                }

                if ($normalizedName !== null) {
                    $authorQ->orWhere('name', $normalizedName);
                }
            });
    }

    public static function resolveLinkedUserId(?string $email, ?string $employeeNumber): ?int
    {
        if ($email !== null && trim($email) !== '') {
            $id = User::query()
                ->whereRaw('LOWER(email) = ?', [strtolower(trim($email))])
                ->value('id');

            if ($id !== null) {
                return (int) $id;
            }
        }

        if ($employeeNumber !== null && trim($employeeNumber) !== '') {
            $normalized = TextNormalizer::upperNullable($employeeNumber);
            $id = User::query()
                ->where('employee_number', $normalized)
                ->value('id');

            if ($id !== null) {
                return (int) $id;
            }
        }

        return null;
    }

    public static function canEditForUserId(?int $userId): bool
    {
        if ($userId === null) {
            return false;
        }

        $user = User::query()->find($userId);

        if ($user === null) {
            return false;
        }

        return $user->can('research.update') || $user->hasRole('co_author');
    }
}
