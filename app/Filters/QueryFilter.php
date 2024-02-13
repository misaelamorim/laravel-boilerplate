<?php
namespace App\Filters;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class QueryFilter
{
    protected $request;
    protected $builder;
    protected $searchable = [];

    private $internalParams = [
        'strict_query',
        'per_page',
        'nopage',
        'sort',
        'doc',
        'page'
    ];

    public function __construct(Request $request)
    {
        $this->request = $request;
    }

    public function searchable(array $searchableFields)
    {
        $this->searchable = $searchableFields;
    }

    public function apply(Builder $builder)
    {
        $this->builder = $builder;
        foreach ($this->fields() as $field => $value) {
            $method = Str::camel($field);
            if (method_exists($this, $method)) {
                call_user_func([$this, $method], $value);
            } else {
                $this->defaultFilter($field, $value);
            }
        }
    }

    protected function fields(): array
    {
        $arr = array_map(
            function ($value) {
                return is_string($value) ? trim($value) : $value;
            },
            $this->request->all()
        );

        return $arr;
    }

    /**
     * Adiciona uma condição se o campo informado na requisição
     * estiver setado na variável searchable
     */
    public function defaultFilter($field, $value)
    {
        if (!in_array($field, $this->searchable) && !in_array($field, $this->internalParams)) {
            abort(400, "Unknown search filter '$field'");
        }

        if (!in_array($field, $this->searchable)) {
            return ;
        }

        switch (gettype($value)) {
            case 'array':
                $this->builder->whereIn($field, $value);
                break;
            case 'NULL':
                $this->builder->whereNull($field);
                break;
            default:
                $this->builder->where($field, $value);
                break;
        }

    }

    /**
     * Ordena pelo campo sort
     * Exemplo: sort= title,-status || sort=-title || sort=status
     * @param string $value
     */
    protected function sort(string $value)
    {
        collect(explode(',', $value))->mapWithKeys(function (string $field) {
            switch (substr($field, 0, 1)) {
                case '-':
                    return [substr($field, 1) => 'desc'];
                case ' ':
                    return [substr($field, 1) => 'asc'];
                default:
                    return [$field => 'asc'];
            }
        })->each(function (string $order, string $field) {
            $this->builder->orderBy($field, $order);
        });
    }
}
