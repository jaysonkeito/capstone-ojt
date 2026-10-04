<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A college this installation recognizes (e.g. CAS, later CBA). Each college
 * owns its own document template set — scoped by code — and appears as a tab
 * on the admin Templates manager. The college whose templates interns
 * actually receive is the one configured under Settings (college_code).
 */
class College extends Model
{
    protected $fillable = ['code', 'name'];

    public function getRouteKeyName(): string
    {
        return 'code';
    }
}
