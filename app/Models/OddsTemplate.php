<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OddsTemplate extends Model
{
    protected $fillable = ['name', 'type', 'config'];

    protected function casts(): array
    {
        return [
            'config' => 'array',
        ];
    }
}
