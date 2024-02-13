<?php

namespace App\Http\Controllers\API;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Routing\Controller as RoutingController;
use Illuminate\Http\Request;
use Spatie\ResponseCache\Facades\ResponseCache;

class Controller extends RoutingController
{
    use AuthorizesRequests, DispatchesJobs, ValidatesRequests;

    protected $service, $validator;

    public function __construct(Request $request)
    {
        if (!$request->route()) {
            return;
        }

        $this->initService($request);

        // Try to instantiante validator using the model name
        $validatorClass = "App\\Validators\\".class_basename($this->service->model)."Validator";
        if (class_exists($validatorClass) && !empty($this->service->model)) {
            $this->validator = new $validatorClass($request);
        }
    }

    private function initService(Request $request) : void {
        $baseClassName = substr(class_basename($this), 0, strpos(class_basename($this), 'Controller'));

        // Makes the service using the current class name
        $serviceClass = "App\\Services\\{$baseClassName}Service";
        if (class_exists($serviceClass) && !empty($baseClassName)) {
            $this->service = new $serviceClass();
            return;
        }

        // Make the service class
        if (get_class($this) === __CLASS__) {
            $model = $this->guessModel($request);

            $this->service = new \App\Services\Service(null, \get_class($model));
        }
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        if (isset($this->validator) && !$this->validator->validate()) {
            return response(['message' => $this->validator->errors()], 400);
        }

        $model = $this->service->update($request, $id);
        if (!$model) {
            return response(null, 500);
        }
        
        return response($model);
    }

    public function destroy($id)
    {
        if (!$this->service->destroy($id)) {
            return response('Error deleting a record.', 500);
        }

        return response('OK');
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        if (isset($this->validator) && !$this->validator->validate()) {
            return response($this->validator->errors(), 400);
        }

        $model = $this->service->store($request);

        if (!$model) {
            return response('Error saving data.', 500);
        }

        return response($model, 201);
    }

    public function show($id)
    {
        $model = $this->service->show($id);

        if (!$model) {
            return response(null, 404);
        }

        return response($model);
    }

    public function index(Request $request)
    {
        return response($this->service->list($request));
    }

    /**
     * Guess the model using route name
     *
     * @param Request $request
     * @return string Data Model Name
     */
    private function guessModel(Request $request) : Model {
        $routeSegments = collect(explode('/', $request->route()->uri))
            ->filter(fn($value) => $value != str_replace('/', '', $request->route()->action['prefix']) && !str($value)->startsWith('{'))
            ->map(fn($value) => str($value)->singular()->studly()->toString());

        $modelName = implode('\\', $routeSegments->all());

        return new ("App\\Models\\{$modelName}");
    }
}
