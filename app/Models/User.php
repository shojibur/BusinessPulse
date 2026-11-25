<?php

namespace App\Models;

use App\Enums\Role;
use App\Models\Concerns\ScopedToDepartment;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

/**
 * @property int $id
 * @property int $company_id
 * @property int|null $department_id
 * @property int|null $manager_id
 * @property string $name
 * @property string $email
 * @property string $password
 * @property Role $role
 * @property string|null $job_title
 * @property bool $is_active
 */
// company_id, is_active and id are deliberately not mass assignable: actions set them explicitly.
#[Fillable(['name', 'email', 'password', 'role', 'job_title', 'department_id', 'manager_id'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, ScopedToDepartment;

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'role' => Role::class,
            'is_active' => 'boolean',
        ];
    }

    /**
     * Emails are stored lowercase and are unique application-wide.
     *
     * @return Attribute<string, string>
     */
    protected function email(): Attribute
    {
        return Attribute::make(set: fn (string $value): string => mb_strtolower(trim($value)));
    }

    public function isOwner(): bool
    {
        return $this->role === Role::Owner;
    }

    public function isManager(): bool
    {
        return $this->role === Role::Manager;
    }

    public function isStaff(): bool
    {
        return $this->role === Role::Staff;
    }

    /** @return BelongsTo<Company, $this> */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /** @return BelongsTo<Department, $this> */
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    /** @return BelongsTo<User, $this> */
    public function manager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'manager_id');
    }

    /** @return HasMany<User, $this> */
    public function reports(): HasMany
    {
        return $this->hasMany(User::class, 'manager_id');
    }
}
