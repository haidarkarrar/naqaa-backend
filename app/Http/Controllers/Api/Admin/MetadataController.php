<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Doctor;
use App\Models\Permission;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MetadataController extends Controller
{
    public function permissions(Request $request): JsonResponse
    {
        if (!$request->user()?->can('roles.view') && !$request->user()?->can('users.assign_permissions')) {
            abort(403, 'Forbidden');
        }

        $query = Permission::query()
            ->when($request->query('q'), function ($builder, $value) {
                $search = trim((string) $value);
                if ($search === '') {
                    return $builder;
                }

                return $builder->where('name', 'like', '%' . $search . '%');
            })
            ->orderBy('name')
            ;

        $permissions = $query->paginate(
            max(1, min(100, (int) $request->query('per_page', 20))),
            ['*'],
            'page',
            max(1, (int) $request->query('page', 1))
        );

        return response()->json([
            'permissions' => collect($permissions->items())->pluck('name')->values(),
            'pagination' => [
                'page' => $permissions->currentPage(),
                'per_page' => $permissions->perPage(),
                'total' => $permissions->total(),
                'last_page' => $permissions->lastPage(),
            ],
        ]);
    }

    public function doctorsSearch(Request $request): JsonResponse
    {
        if (
            !$request->user()?->can('users.view')
            && !$request->user()?->can('users.create')
            && !$request->user()?->can('users.update')
        ) {
            abort(403, 'Forbidden');
        }

        $query = trim((string) $request->query('q', ''));
        $limit = max(1, min(100, (int) $request->query('limit', 20)));

        $doctors = Doctor::query()
            ->when($query !== '', function ($builder) use ($query) {
                $like = '%' . $query . '%';
                return $builder->where(function ($inner) use ($like) {
                    $inner
                        ->where('FullName', 'like', $like)
                        ->orWhere('FirstName', 'like', $like)
                        ->orWhere('LastName', 'like', $like)
                        ->orWhere('Username', 'like', $like)
                        ->orWhere('Email', 'like', $like);
                });
            })
            ->orderBy('FullName')
            ->limit($limit)
            ->get([
                'Id',
                'FullName',
                'FirstName',
                'LastName',
                'Username',
                'Email',
                'SpecialtyId',
            ])
            ->map(function (Doctor $doctor) {
                return [
                    'id' => $doctor->Id,
                    'FullName' => $doctor->FullName ?? trim("{$doctor->FirstName} {$doctor->LastName}"),
                    'Username' => $doctor->Username,
                    'Email' => $doctor->Email,
                    'SpecialtyId' => $doctor->SpecialtyId,
                ];
            })
            ->values();

        return response()->json(['doctors' => $doctors]);
    }
}
