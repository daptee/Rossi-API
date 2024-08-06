<?php

namespace App\Http\Token;

use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;
use App\Models\User;

class TokenService
{
    /**
     * Generate a JWT token for the given user.
     *
     * @param User $user
     * @return string
     */
    public function generateToken(User $user)
    {
        // Generate the token with custom claims
        return JWTAuth::claims($this->getCustomClaims($user))->fromUser($user);
    }

    /**
     * Get custom claims for the JWT.
     *
     * @param User $user
     * @return array
     */
    protected function getCustomClaims(User $user)
    {
        return [
            'id' => $user->id,
            'id_user_type' => $user->id_user_type,
            'name' => $user->name,
            'lastname' => $user->lastname,
            'email' => $user->email,
        ];
    }
}
