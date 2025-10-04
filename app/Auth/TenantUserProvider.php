<?php

namespace App\Auth;

use Illuminate\Auth\EloquentUserProvider;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Config;

class TenantUserProvider extends EloquentUserProvider
{
    /**
     * Create a new instance of the model.
     */
    public function createModel()
    {
        $class = '\\'.ltrim($this->model, '\\');

        $model = new $class;

        // If tenant connection is configured, use it
        if (Config::get('database.connections.tenant')) {
            $model->setConnection('tenant');
        }

        return $model;
    }

    /**
     * Get a new query builder for the model instance.
     */
    protected function newModelQuery($model = null)
    {
        $model = is_null($model)
                ? $this->createModel()
                : $model;

        return $model->newQuery();
    }
}
