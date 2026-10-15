<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class FireIncidentUpdate extends Model {
    public const ALARMS = ['1st', '2nd', '3rd', '4th', '5th', 'Task Force Alpha', 'Task Force Bravo', 'General Alarm'];
    protected $guarded = ['id'];
    protected function casts(): array { return ['observed_at' => 'datetime']; }
    public function actor() { return $this->belongsTo(User::class, 'actor_id'); }
}
