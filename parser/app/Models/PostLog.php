<?php
declare(strict_types=1);

namespace parser\Models;

use Illuminate\Database\Eloquent\Model;

class PostLog extends Model
{
	protected string $table = 'post_log';

	protected array $fillable = [
    'server',
    'date',
    'type',
    'username',
    'user_ipaddr',
    'device_ipaddr',
    'receivers',
    'status',
  ];
}
