<?php
declare(strict_types=1);

namespace parser\Models;

use Illuminate\Database\Eloquent\Model;

class PostBuffer extends Model
{
	protected string $table = 'post_buffer';

	protected array $fillable = [
    'server',
    'date',
    'type',
    'username',
    'user_ipaddr',
    'device_ipaddr',
    'count',
  ];
}
