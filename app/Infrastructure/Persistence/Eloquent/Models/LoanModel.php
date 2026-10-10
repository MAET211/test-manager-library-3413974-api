<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class LoanModel extends Model
{
    protected $table = 'library.loan';
    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;

    protected $fillable = [
        'id',
        'member_id',
        'loaned_by_user_id',
        'loaned_at',
    ];

    protected $casts = [
        'loaned_at' => 'datetime',
    ];

    public function member(): BelongsTo
    {
        return $this->belongsTo(MemberModel::class, 'member_id', 'id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(UserModel::class, 'loaned_by_user_id', 'id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(LoanItemModel::class, 'loan_id', 'id');
    }
}
