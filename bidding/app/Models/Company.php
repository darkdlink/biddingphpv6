<?php
// app/Models/Company.php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Company extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name', 'cnpj', 'address', 'city', 'state',
        'zip_code', 'phone', 'email', 'description'
    ];

    public function biddings()
    {
        return $this->hasMany(Bidding::class);
    }

    public function documents()
    {
        return $this->morphMany(Document::class, 'documentable');
    }
}
