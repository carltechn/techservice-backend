<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Role extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'display_name',
        'description',
    ];

    /**
     * Role constants
     */
    public const ADMIN = 'admin';
    public const INCHARGE = 'incharge';
    public const USER = 'user';

    /**
     * Get users with this role.
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }
}

