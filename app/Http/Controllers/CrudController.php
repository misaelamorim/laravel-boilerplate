<?php

namespace App\Http\Controllers;

use Exception;
use Illuminate\Support\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class CrudController extends BaseController
{
    use AuthorizesRequests, DispatchesJobs, ValidatesRequests;

    protected $service, $validator;
    public $pageTitle = null;
    public $tableName = null;
    public $baseRoute = null;

    public function __construct(Request $request)
    {
        $baseClassName = substr(class_basename($this), 0, strpos(class_basename($this), 'Controller'));

        $this->initService($request);

        $validatorClass = "App\\Validators\\{$baseClassName}Validator";
        if (!class_exists($validatorClass)) {
            throw new Exception("You should provide a $validatorClass class");
        }

        $this->validator = new $validatorClass($request);
        $this->tableName = $this->service?->model->getTable();
        $this->pageTitle = __(str($this->tableName)->headline()->toString());
        $this->baseRoute = Str::beforeLast($request->route()->action['as'], '.');
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
            return back()->withErrors($this->validator->errors())->withInput();
        }

        if (isset($this->validator) && !$this->validator->validate()) {
            return back()->withErrors($this->validator->errors())->withInput();
        }

        if (!$this->service->update($request, $id)) {
            $request->session()->flash('warning', 'Verifique as informações inseridas.');
            return back()->withInput();
        }
        
        return redirect()->route($this->baseRoute.'.index', ['sort' => '-updated_at'])->with('success', 'Registro Atualizado.');
    }

    public function destroy($id)
    {
        if (!$this->service->destroy($id)) {
            return back()->with('warning', 'Não foi possível excluir.');
        }

        return redirect()->route($this->baseRoute.'.index')->with('success', 'Registro Excluído.');
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
            return back()->withInput()->withErrors($this->validator->errors());
        }

        $model = $this->service->store($request);
        return redirect()->route($this->baseRoute.'.index', ['sort' => '-created_at'])->with('success', 'Registro criado com sucesso.');
    }

    /**
     * Função sobrescrita para preservar as funcionalidades universais desse controller
     * Se precisar receber a instância do modelo na policy, sobrescreva e remova os métodos adicionados aqui.
     * @override Illuminate\Foundation\Auth\Access\AuthorizesRequests resourceMethodsWithoutModels
     *
     * @return void
     */
    protected function resourceMethodsWithoutModels()
    {
        return [ 'index', 'show', 'create', 'store', 'edit', 'update', 'destroy' ];
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $this->authorize("read-{$this->tableName}");
        
        $breadcrumbs = [
            __('Home') => '/',
            $this->pageTitle => ''
        ];

        $request->flash();

        $viewData = [
            'data' => $this->service->list($request),
            'columns' => $this->listingColumns(),
            'breadcrumbs' => $breadcrumbs,
            'pageTitle' => $this->pageTitle,
            'searchable' => self::getSearchFields($this->service->model),
            'baseRoute' => $this->baseRoute,
        ];

        if (!view()->exists(Str::singular($this->tableName).'.index')) {
            return view('crud.index', $viewData);
        }

        return view(Str::singular($this->tableName).'.index', $viewData);
    }

    /**
     * @param  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        $this->authorize("read-{$this->tableName}");
        $model = $this->service->model::find($id);
        $breadcrumbs = [
            __('Home') => '/',
            __(str($this->tableName)->headline()->toString()) => route($this->baseRoute.'.index'),
            '# ' . $model->id => ''
        ];

        $viewData = [
            'fields' => $this->getFieldsConfig($this->service->model),
            'model' => $model,
            'breadcrumbs' => $breadcrumbs,
            'pageTitle' => $this->pageTitle,
        ];

        if (view()->exists(Str::singular($this->tableName).'.show')) {
            return view(Str::singular($this->tableName).'.show', $viewData);
        }

        return view('crud.show', $viewData);
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        $this->authorize("create-{$this->tableName}");
        $viewTitle = "Criar";
        $breadcrumbs = [
            'Início' => '/',
            __(str($this->tableName)->headline()->toString()) => route($this->baseRoute.'.index'),
            $viewTitle => ''
        ];

        $viewData = [
            'fields' => $this->getFieldsConfig($this->service->model),
            'breadcrumbs' => $breadcrumbs,
            'pageTitle' => $viewTitle,
            'model' => $this->service->model,
            'baseRoute' => $this->baseRoute,
        ];

        if (view()->exists(Str::singular($this->tableName).'.create')) {
            return view(Str::singular($this->tableName).'.create', $viewData);
        }

        return view('crud.create', $viewData);
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        $this->authorize("edit-{$this->tableName}");
        $model = $this->service->model::find($id);
        $breadcrumbs = [
            __('Home') => '/',
            __(str($this->tableName)->headline()->toString()) => route($this->baseRoute.'.index'),
            '#' . $model->id => ''
        ];
        $viewData = [
            'fields' => $this->getFieldsConfig($this->service->model),
            'model' => $model,
            'breadcrumbs' => $breadcrumbs,
            'pageTitle' => $model->id,
            'baseRoute' => $this->baseRoute
        ];

        if (view()->exists(Str::singular($this->tableName).'.edit')) {
            return view(Str::singular($this->tableName).'.edit', $viewData);
        }

        return view('crud.edit', $viewData);
    }

    /**
     * Returns fillable fields with field type and description 
     *
     * @param Model $model
     * @return Collection
     */
    public static function getFieldsConfig(Model $model) : Collection {
        $displayNames = $model->comments;

        return collect(Schema::getColumnListing($model->getTable()))
            ->filter(fn($field) => !in_array($field, $model->getHidden()))
            ->mapWithKeys(function($columnName) use ($displayNames, $model) {
                return [
                    $columnName => [
                            'type' => Schema::getColumnType($model->getTable(), $columnName),
                            'displayName' => $displayNames[$columnName] ?? '',
                            'fillable' => in_array($columnName, (array) $model->getFillable())
                        ]
                    ];
            });
    }

    /**
     * Return searchable fields `model::searchable()` with column descriptions `model->comments`
     *
     * @return Collection
     */
    public static function getSearchFields(Model $model) : Collection {
        return self::getFieldsConfig($model)
            ->intersectByKeys(array_flip($model?->searchable()));
    }

    private function initService(Request $request) : void {
        $baseClassName = substr(class_basename($this), 0, strpos(class_basename($this), 'Controller'));

        // Cria o serviço a partir da classe atual (filha)
        $serviceClass = "App\\Services\\{$baseClassName}Service";

        if (class_exists($serviceClass) && !empty($baseClassName)) {
            $this->service = new $serviceClass();
            return;
        }

        // Cria o serviço usando a classe mãe a partir do nome da rota
        if ($request->route()) {
            $model = $this->guessModel($request);
            $this->service = new \App\Services\Service(null, \get_class($model));
        }
    }

    /**
     * Tenta o modelo pela rota da requisição
     *
     * @param Request $request
     * @return string Nome do modelo de dados
     */
    private function guessModel(Request $request) : Model | null {
        $routeSegments = collect(explode('/', $request->route()?->uri))
            ->filter(fn($value) => !str($value)->startsWith('{') && !in_array($value, ['edit','show','create']))
            ->map(fn($value) => str($value)->singular()->studly()->toString());

        $modelName = implode('\\', $routeSegments->all());

        if (!\class_exists("App\\Models\\{$modelName}")) {
            throw new ModelNotFoundException("Model not found $modelName");
        }

        return new ("App\\Models\\{$modelName}");
    }
}
