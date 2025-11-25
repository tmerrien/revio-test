<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;

/**
 * Support Ticket Model
 *
 * Stores classified support tickets with their predicted categories and responses
 */
class Ticket extends Model
{
    /**
     * The connection name for the model.
     *
     * @var string
     */
    protected $connection = 'mongodb';

    /**
     * The collection associated with the model.
     *
     * @var string
     */
    protected $collection = 'tickets';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<string>
     */
    protected $fillable = [
        'ticket_text',
        'predicted_category',
        'predicted_response',
        'confidence_score',
        'processing_time_ms',
        'model_used',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'confidence_score' => 'float',
        'processing_time_ms' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array<string>
     */
    protected $hidden = [];

    /**
     * Get the ticket's category display name.
     *
     * @return string
     */
    public function getCategoryDisplayNameAttribute(): string
    {
        return ucwords(str_replace('_', ' ', $this->predicted_category));
    }

    /**
     * Scope a query to only include tickets of a specific category.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @param  string  $category
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeOfCategory($query, string $category)
    {
        return $query->where('predicted_category', $category);
    }

    /**
     * Scope a query to only include recent tickets.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @param  int  $days
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeRecent($query, int $days = 7)
    {
        return $query->where('created_at', '>=', now()->subDays($days));
    }
}
