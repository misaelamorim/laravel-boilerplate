<?php

namespace App\Validators;

use Illuminate\Validation\Rule;

class UserValidator extends Validator
{
    protected function rules()
    {
        return [
            'email' => [
                Rule::unique('users')
            ],
        ];
    }

}