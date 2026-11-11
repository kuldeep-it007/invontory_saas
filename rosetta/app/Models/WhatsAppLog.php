<?php

namespace Rosetta\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class WhatsAppLog extends Model
{
    use SoftDeletes;

    protected $table = 'whats_app_logs';

    protected $fillable = [
        'request',
        'response',
        'status_code',
    ];

    protected $casts = [
        'request' => 'array',
        'response' => 'array',
    ];
}
