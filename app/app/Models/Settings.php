<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Settings extends Model
{

    protected $table = 'settings';

    const ACCOUNT_ID    = 'account_id';
    const STATUS_CHECK  = 'status_check';
    const STATUSES      = 'statuses';
    const LEGAL_EMAIL   = 'legal_email';

    protected $fillable = [
        self::ACCOUNT_ID,
        self::STATUS_CHECK,
        self::STATUSES,
        self::LEGAL_EMAIL,
    ];

    protected $primaryKey = self::ACCOUNT_ID;

}
