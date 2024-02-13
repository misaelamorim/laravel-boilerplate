<?php
namespace App\Services;

use App\Filters\QueryFilter;
use Illuminate\Http\Request;

class Service
{
    public $model;

    public function __construct($searchParams = null, $model = null)
    {
        $baseClassName = substr(class_basename($this), 0, strpos(class_basename($this), 'Service'));
        $modelClass = "\\App\\Models\\$baseClassName";
        $this->model = is_null($model) ? new $modelClass : new $model;

        if (!is_null($searchParams)) {
            $this->loadModel($searchParams);
        }
    }

    public function store(Request $request)
    {
        $this->model->fill($request->all());

        if (!$this->model->save()) {
            return false;
        }

        return $this->model;
    }

    public function update(Request $request, $id)
    {
        $this->model = $this->model->find($id);

        if (!$this->model) {
            return false;
        }
        
        $this->model->fill($request->all());

        foreach ($this->model->getOriginal() as $key => $value) {
            if ($value === true && !$request->has($key) && in_array($key, $this->model->getFillable())) {
                $this->model->$key = false;
            }
        }

        if (!$this->model->save()) {
            return false;
        }

        return $this->model;
    }

    public function destroy($id)
    {
        return $this->model->destroy($id);
    }

    public function show($id)
    {
        return $this->model->find($id);
    }

    public function list(Request $request)
    {
        $query = $this->model::query();

        $baseClassName = class_basename($this->model);
        $filterClass = "App\\Filters\\{$baseClassName}Filter";

        $filter = class_exists($filterClass) ? new $filterClass($request) : new QueryFilter($request);
        
        if ($request->has('doc')) {
            return $this->model->comments;
        }
        
        $query->filter($filter);
        
        if ($request->has('strict_query')) {
            abort_if($query->count() > 1, 400, 'A query retornou mais de um registro. Utilize um parâmetro adicional na seleção.');

            return $query->first();
        }

        return $request->has('nopage') ? $query->get() : $query->paginate();
    }

    private function loadModel($params)
    {
        if (is_array($params)) {
            $this->model = $this->model->firstWhere($params);
            return ;
        }

        $this->model = $this->model->find($params);
    }
}
