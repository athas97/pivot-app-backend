<?php

namespace App\Organization\Models;

use App\Organization\Database\Factories\OrganizationMembershipFactory;
use App\Organization\Enums\MembershipStatus;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrganizationMembership extends Model
{
    use HasFactory;

    protected $fillable = [
        'organization_id',
        'user_id',
        'status',
        'joined_via',
        'joined_at',
        'activated_at',
        'deactivated_at',
        'expires_at',
        'deactivation_reason',
        'provisioning_source_id',
    ];

    protected function casts(): array
    {
        return [
            'status' => MembershipStatus::class,
            'joined_at' => 'datetime',
            'activated_at' => 'datetime',
            'deactivated_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    protected static function newFactory(): OrganizationMembershipFactory
    {
        return OrganizationMembershipFactory::new();
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
