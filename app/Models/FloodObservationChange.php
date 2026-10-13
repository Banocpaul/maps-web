<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class FloodObservationChange extends Model {
    protected $guarded = ['id'];
    protected function casts(): array { return ['observed_at' => 'datetime', 'notification_summary' => 'array']; }
}
