<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OAuthClient extends Model
{
    use HasFactory;

    protected $table = 'oauth_clients';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'user_id',
        'name',
        'secret',
        'redirect_uri',
    ];

    protected $hidden = [
        'secret',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function authCodes(): HasMany
    {
        return $this->hasMany(OAuthAuthCode::class, 'client_id');
    }

    public function refreshTokens(): HasMany
    {
        return $this->hasMany(OAuthRefreshToken::class, 'client_id');
    }

    /**
     * Checks if the provided redirect URI matches the registered redirect URI.
     */
    public function matchesRedirectUri(string $uri): bool
    {
        $registered = trim($this->redirect_uri);
        $provided = trim($uri);

        if ($registered === $provided) {
            return true;
        }

        // Also allow matching if registered is a prefix or base host with exact path
        $regParts = parse_url($registered);
        $provParts = parse_url($provided);

        return isset($regParts['host'], $provParts['host'])
            && $regParts['host'] === $provParts['host']
            && ($regParts['path'] ?? '') === ($provParts['path'] ?? '');
    }
}
