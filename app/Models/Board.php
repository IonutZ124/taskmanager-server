<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Board extends Model
{
    use HasFactory;

    protected $hidden = ["created_at", "updated_at"];

    public function getOwner()
    {
        return $this->belongsTo(User::class, "owner_id", "id");
    }

    public function getMembers()
    {
        return $this->hasMany(BoardMembers::class, 'board_id', 'id');
    }

    public function statuses()
    {
        return $this->hasMany(Status::class, 'board_id');
    }
}