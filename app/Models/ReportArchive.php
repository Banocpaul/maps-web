<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class ReportArchive extends Model {
    protected $guarded = ['id'];
    protected function casts(): array { return ['snapshot' => 'array']; }
    public function creator() { return $this->belongsTo(User::class, 'created_by'); }
}
