<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GroupMember extends Model
{
    protected $fillable = ['group_id', 'user_id', 'role'];

    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isEditor(): bool
    {
        return in_array($this->role, ['editor', 'admin'], true);
    }

    public function isViewer(): bool
    {
        return in_array($this->role, ['viewer', 'editor', 'admin'], true);
    }
}
