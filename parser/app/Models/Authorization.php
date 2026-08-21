<?php
declare(strict_types=1);

namespace parser\Models;

use Illuminate\Database\Eloquent\Model;

class Authorization extends Model
{
	protected string $table = 'tac_log_authorization';

	protected array $fillable = [
		'server',
		'date',
		'NAS',
		'username',
		'line',
		'NAC',
		'action',
		'cmd',
	];
}
