<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class PriorityProjectBaseline extends Model
{
    protected $fillable = ['target_period_id', 'project_id', 'initial_accumulated_investment', 'historical_quarterly_realization', 'captured_at'];
    protected function casts(): array { return ['captured_at' => 'datetime']; }
}
