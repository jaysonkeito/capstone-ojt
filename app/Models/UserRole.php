<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * One role an account holds beyond (or recording) its primary one — see
 * User::STAFF_ROLES and User::hasRole(). The primary role also lives in
 * users.role; the pivot mirrors it so the account's full role set is
 * queryable in one place.
 */
class UserRole extends Model
{
    protected $fillable = [
        'user_id',
        'role',
    ];
}
