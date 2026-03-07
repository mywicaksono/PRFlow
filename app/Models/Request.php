<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\RequestStatusEnum;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Request extends Model
{
    use HasFactory;

    /**
     * @var array<int, string>
     */
    protected $guarded = [];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'status' => RequestStatusEnum::class,
        'amount' => 'decimal:2',
        'submitted_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function approvals(): HasMany
    {
        return $this->hasMany(Approval::class);
    }

    public function activities(): HasMany
    {
        return $this->hasMany(RequestActivity::class)
            ->orderBy('created_at');
    }


    public function attachments(): HasMany
    {
        return $this->hasMany(RequestAttachment::class)
            ->orderByDesc('id');
    }


    public function comments(): HasMany
    {
        return $this->hasMany(RequestComment::class)
            ->orderBy('created_at');
    }
}
