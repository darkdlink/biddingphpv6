<?php
// app/Models/Proposal.php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Proposal extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'bidding_id', 'value', 'description', 'status',
        'profit_margin', 'total_cost', 'submission_date'
    ];

    protected $dates = [
        'submission_date'
    ];

    public function bidding()
    {
        return $this->belongsTo(Bidding::class);
    }

    public function documents()
    {
        return $this->morphMany(Document::class, 'documentable');
    }
}
