<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Task extends Model
{
    use HasFactory;


    protected $hidden = ["created_at", "updated_at"];

    public function status()
    {
        return $this->belongsTo(Status::class, 'status_id');
    }

    /**
     * Get all of the comments for the Task
     *
     * @return HasMany
     */
    public function comments()
    {
        return $this->hasMany(TaskComment::class, 'id');
    }

    public function history()
    {
        return $this->hasMany(TaskHistory::class, 'task_id');
    }
}