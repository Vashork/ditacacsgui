<?php
declare(strict_types=1);

namespace parser\Models;

use Illuminate\Database\Eloquent\Model;

class Authentication extends Model
{
	protected string $table = 'tac_log_authentication';

	protected array $fillable = [
		'server',
		'date',
		'NAS',
		'username',
		'line',
		'NAC',
		'action',
		'unknown',
	];
}
