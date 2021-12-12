<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UsersMatch extends Model
{
    use HasFactory;

    protected $table = 'users_match';

    public const ACCOUNT_ID = 'account_id';
    public const AMO_USER_ID = 'amo_user_id';
    public const C_USER_ID = 'c_user_id';

    protected $fillable = [
            self::ACCOUNT_ID,
            self::C_USER_ID,
            self::AMO_USER_ID,
        ];

}
