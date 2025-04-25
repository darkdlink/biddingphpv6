<?php
// app/Models/Bidding.php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Bidding extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'title', 'bidding_number', 'description', 'company_id',
        'modality', 'status', 'estimated_value', 'publication_date',
        'opening_date', 'closing_date', 'url_source'
    ];

    protected $dates = [
        'publication_date', 'opening_date', 'closing_date'
    ];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function proposals()
    {
        return $this->hasMany(Proposal::class);
    }

    public function documents()
    {
        return $this->morphMany(Document::class, 'documentable');
    }
}
