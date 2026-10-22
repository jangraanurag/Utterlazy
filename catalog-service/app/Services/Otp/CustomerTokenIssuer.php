<?php

namespace App\Services\Otp;

use App\Models\User;

/** Finds/creates a customer for a verified phone and issues a Sanctum token. */
class CustomerTokenIssuer
{
    /** @return array{user:User, token:string, is_new_user:bool} */
    public function issue(string $phone, string $tokenName = 'utterlazy-whatsapp'): array
    {
        $user = User::where('phone', $phone)->first();
        $isNew = false;

        if (! $user) {
            $user = User::create([
                'name' => 'Guest User',
                'email' => $phone.'@utterlazy.com',
                'phone' => $phone,
                'password' => password_hash(bin2hex(random_bytes(16)), PASSWORD_BCRYPT),
                'khaalo_coins' => 50,
            ]);
            $isNew = true;
        }

        $token = $user->createToken($tokenName)->plainTextToken;

        return ['user' => $user, 'token' => $token, 'is_new_user' => $isNew];
    }
}
