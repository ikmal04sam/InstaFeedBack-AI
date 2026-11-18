<?php

namespace App\Models;

use Database\Factories\EssayFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Essay extends Model
{
    /** @use HasFactory<EssayFactory> */
    use HasFactory;

    protected $fillable = [
        'user_id',
        'content',
        'task',
        'word_count',
        'sentence_count',
        'score',
        'score_max',
        'level',
        'analysis',
        'feedback',
        'suggestions',
    ];

    protected $attributes = ['score_max' => 100];

    protected function casts(): array
    {
        return [
            'analysis' => 'array',
            'feedback' => 'array',
            'suggestions' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
