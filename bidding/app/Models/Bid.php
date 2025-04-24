<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Bid extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'description',
        'bid_number',
        'source_url',
        'bid_category_id',
        'estimated_value',
        'opening_date',
        'closing_date',
        'status',
        'requirements',
        'notes',
    ];

    protected $casts = [
        'opening_date' => 'datetime',
        'closing_date' => 'datetime',
        'estimated_value' => 'decimal:2',
    ];

    public function category()
    {
        return $this->belongsTo(BidCategory::class, 'bid_category_id');
    }

    public function proposals()
    {
        return $this->hasMany(Proposal::class);
    }

    public function attachments()
    {
        return $this->hasMany(BidAttachment::class);
    }

    public function alerts()
    {
        return $this->hasMany(BidAlert::class);
    }
}
