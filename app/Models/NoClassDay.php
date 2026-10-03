<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NoClassDay extends Model
{
    protected $fillable = ['date', 'label', 'created_by'];

    protected function casts(): array
    {
        return [
            'date' => 'date',
        ];
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
