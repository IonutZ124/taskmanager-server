<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BoardMembers extends Model
{

    use HasFactory;

    const ADMIN = 1;
    const MEMBER = 0;
    protected $hidden = [
        'created_at', 'updated_at', 'id'
    ];

    public function getBoards()
    {
        return $this->belongsTo(Board::class, "board_id", "id");
    }

    public function getUser()
    {
        return $this->belongsTo(User::class, "user_id", "id");
    }
}
