<?php

namespace App\Actions\Fortify;

use App\Models\User;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Laravel\Fortify\Contracts\CreatesNewUsers;

class CreateNewUser implements CreatesNewUsers
{
    use PasswordValidationRules;

    /**
     * Validate and create a newly registered user.
     *
     * @param  array<string, string>  $input
     */
    public function create(array $input): User
    {
        Validator::make($input, [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'string',
                'email',
                'max:255',
                Rule::unique(User::class),
            ],
            'password' => $this->passwordRules(),
        ])->validate();

        $username = Str::slug($input['name'], '_');
        if ($username === '') {
            $username = 'user';
        }

        $candidate = $username;
        $suffix = 1;
        while (User::query()->where('username', $candidate)->exists()) {
            $candidate = "{$username}_{$suffix}";
            $suffix++;
        }

        return User::create([
            'username' => $candidate,
            'display_name' => $input['name'],
            'email' => $input['email'],
            'password' => $input['password'],
            'is_active' => true,
        ]);
    }
}
