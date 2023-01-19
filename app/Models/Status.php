<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Status extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'board_id'

    ];

    protected $visible = [
        'id', 'name', 'board_id'
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'timestamps'
    ];

    public function board()
    {
        return $this->belongsTo(Board::class, 'board_id');
    }

    public function tasks()
    {
        return $this->hasMany(Task::class, 'status_id');
    }
}
