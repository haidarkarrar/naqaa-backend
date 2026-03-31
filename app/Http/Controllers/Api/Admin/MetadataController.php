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

        $permissions = Permission::query()
            ->orderBy('name')
            ->pluck('name')
            ->values();

        return response()->json(['permissions' => $permissions]);
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

