<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * App\Models\AuthData
 *
 * @method static \Illuminate\Database\Eloquent\Builder|AuthData newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|AuthData newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|AuthData query()
 * @property int $account_id
 * @property string $access_token
 * @property string $refresh_token
 * @property string $expires
 * @property string $base_domain
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @method static \Illuminate\Database\Eloquent\Builder|AuthData whereAccessToken($value)
 * @method static \Illuminate\Database\Eloquent\Builder|AuthData whereAccountId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|AuthData whereBaseDomain($value)
 * @method static \Illuminate\Database\Eloquent\Builder|AuthData whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|AuthData whereExpires($value)
 * @method static \Illuminate\Database\Eloquent\Builder|AuthData whereRefreshToken($value)
 * @method static \Illuminate\Database\Eloquent\Builder|AuthData whereUpdatedAt($value)
 */
class AuthData extends Model
{
    public const ACCOUNT_ID = 'account_id';
    public const ACCESS_TOKEN = 'access_token';
    public const REFRESH_TOKEN = 'refresh_token';
    public const EXPIRES = 'expires';
    public const BASE_DOMAIN = 'base_domain';
    protected $table = 'auth_data';

    protected $fillable = ['access_token', 'refresh_token', 'expires', 'base_domain', 'account_id'];

    protected $primaryKey = self::ACCOUNT_ID;

}
