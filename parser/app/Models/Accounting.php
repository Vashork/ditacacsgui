<?php
declare(strict_types=1);

namespace parser\Models;

use Illuminate\Database\Eloquent\Model;

class Accounting extends Model
{
	protected string $table = 'tac_log_accounting';

	protected array $fillable = [
		'server',
		'date',
		'NAS',
		'username',
		'line',
		'NAC',
		'action',
		'task_id',
		'timezone',
		'service',
		'priv-lvl',
		'cmd',
		'disc-cause',
		'disc-cause-ext',
		'pre-session-time',
		'elapsed_time',
		'stop_time',
		'unknown',
	];
}
