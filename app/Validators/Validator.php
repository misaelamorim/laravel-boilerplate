<?php
namespace App\Validators;

use Illuminate\Validation\Rule;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator as ValidatorFacade;

abstract class Validator
{
    private $validator;

    public function __construct(Request $request)
    {
        $this->request = $request;
    }

    public function validate()
    {
        $this->validator = ValidatorFacade::make($this->request->all(), $this->rules());
        return $this->validator->passes();
    }

    public function errors()
    {
        return $this->validator->errors();
    }

    abstract protected function rules();
}
