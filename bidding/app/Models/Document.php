<?php
// app/Models/Document.php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Document extends Model
{
    use HasFactory;

    protected $fillable = [
        'name', 'file_path', 'file_type',
        'documentable_id', 'documentable_type'
    ];

    public function documentable()
    {
        return $this->morphTo();
    }
}
