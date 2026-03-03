<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Persona extends Model
{
    protected $fillable = [
        'user_id',
        'name',
        'description',
        'context',
        'perspective',
        'response_type',
        'response_style',
        'model',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function chatConversations(): HasMany
    {
        return $this->hasMany(ChatConversation::class);
    }

    /**
     * Build the system prompt from persona configuration.
     */
    public function buildSystemPrompt(): string
    {
        $parts = [];

        if ($this->context) {
            $parts[] = "Context: {$this->context}";
        }

        if ($this->perspective) {
            $parts[] = "Perspective: {$this->perspective}";
        }

        if ($this->response_type) {
            $parts[] = "Response Type: {$this->response_type}";
        }

        if ($this->response_style) {
            $parts[] = "Response Style: {$this->response_style}";
        }

        if ($this->description) {
            $parts[] = "Role: {$this->description}";
        }

        return implode("\n", $parts);
    }
}
