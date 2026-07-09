<?php

namespace App\Models\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Support\Facades\Auth;

/**
 * Frontera de seguridad multi-tenant: cuando el usuario autenticado es un
 * `owner`, todas las queries de modelos tenant-owned quedan automáticamente
 * restringidas a su propio negocio. `super_admin` y contextos sin usuario
 * autenticado (consola, seeders) no se filtran — necesitan ver/crear datos
 * de cualquier negocio.
 */
class BusinessScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $user = Auth::user();

        if (! $user || $user->role !== 'owner') {
            return;
        }

        $builder->where($model->qualifyColumn('business_id'), $user->business?->id ?? 0);
    }
}
