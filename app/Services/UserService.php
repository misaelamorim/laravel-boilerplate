<?php
namespace App\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class UserService extends Service
{
    public function update(Request $request, $id)
    {
        $this->model = $this->model->find($id);

        if (!$this->model) {
            return false;
        }
      
        $this->model->name = $request->name;

        if (!$this->model->save()) {
            return false;
        }

        if ($request->has('role')) {
            $this->model->syncRoles($request->role);
        }

        return $this->model;
    }


}
