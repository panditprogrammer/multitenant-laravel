<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Shift extends Model
{
    protected $fillable = [
        'library_id',
        'name',
        'start_time',
        'end_time',
    ];

    protected $casts = [
        'start_time' => 'datetime:H:i',
        'end_time' => 'datetime:H:i',
    ];

    public function library()
    {
        return $this->belongsTo(Library::class);
    }

    public function memberships()
    {
        return $this->belongsToMany(Membership::class, 'membership_shift');
    }
}
