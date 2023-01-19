<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TaskComment extends Model
{
    use HasFactory;

    protected $fillable = [
        'comment',
    ];

    /**
     * Get the User that owns the TaskComment
     *
     * @return BelongsTo
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'id');
    }

    /**
     * Get the Task that owns the TaskComment
     *
     * @return BelongsTo
     */
    public function task()
    {
        return $this->belongsTo(Task::class, 'id');
    }
}
