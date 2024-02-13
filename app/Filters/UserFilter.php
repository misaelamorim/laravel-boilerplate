<?php

namespace App\Filters;

class UserFilter extends QueryFilter
{
    protected $searchable = [];

    public function email(string $value)
    {
        $this->builder->where('email', 'ilike', "%$value%");
    }
}
