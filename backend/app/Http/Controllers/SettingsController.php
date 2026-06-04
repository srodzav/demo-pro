<?php

namespace App\Http\Controllers;

use App\Models\AppSetting;
use App\Models\CatalogItem;
use App\Models\ClinicSetting;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class SettingsController extends Controller
{
    public function publicConfig()
    {
        return response()->json([
            'clinic' => ClinicSetting::firstOrCreate([]),
            'catalogs' => CatalogItem::active()
                ->orderBy('type')
                ->orderBy('sort_order')
                ->orderBy('label')
                ->get()
                ->groupBy('type'),
        ]);
    }

    public function overview()
    {
        return response()->json([
            'clinic' => ClinicSetting::firstOrCreate([]),
            'app_settings' => [
                'public_registration_enabled' => AppSetting::getValue('public_registration_enabled', false),
            ],
            'roles' => Role::with('permissions')->orderBy('label')->get(),
            'permissions' => Permission::orderBy('group')->orderBy('label')->get(),
            'catalogs' => CatalogItem::orderBy('type')->orderBy('sort_order')->orderBy('label')->get(),
        ]);
    }

    public function updateClinic(Request $request)
    {
        $validated = $request->validate([
            'clinic_name' => 'required|string|max:255',
            'legal_name' => 'nullable|string|max:255',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:30',
            'whatsapp' => 'nullable|string|max:30',
            'address' => 'nullable|string|max:255',
            'city' => 'nullable|string|max:100',
            'state' => 'nullable|string|max:100',
            'primary_color' => ['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'accent_color' => ['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'logo_url' => 'nullable|string|max:255',
            'business_hours' => 'nullable|array',
            'social_links' => 'nullable|array',
        ]);

        $clinic = ClinicSetting::firstOrCreate([]);
        $clinic->update($validated);

        return response()->json(['clinic' => $clinic->fresh()]);
    }

    public function uploadClinicLogo(Request $request)
    {
        $validated = $request->validate([
            'logo' => 'required|file|image|mimes:jpg,jpeg,png,webp,svg|max:2048',
        ]);

        $path = $validated['logo']->store('clinic', 'public');
        $clinic = ClinicSetting::firstOrCreate([]);
        $clinic->update([
            'logo_url' => Storage::disk('public')->url($path),
        ]);

        return response()->json(['clinic' => $clinic->fresh()]);
    }

    public function updateAppSettings(Request $request)
    {
        $validated = $request->validate([
            'public_registration_enabled' => 'required|boolean',
        ]);

        AppSetting::setValue('public_registration_enabled', $validated['public_registration_enabled'], 'boolean');

        return response()->json([
            'app_settings' => [
                'public_registration_enabled' => $validated['public_registration_enabled'],
            ],
        ]);
    }

    public function users()
    {
        return response()->json(
            User::with('role')
                ->orderBy('name')
                ->get(['id', 'name', 'email', 'role_id', 'is_active', 'created_at'])
        );
    }

    public function storeUser(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:users,email',
            'password' => 'required|string|min:8',
            'role_id' => 'required|exists:roles,id',
            'is_active' => 'boolean',
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'role_id' => $validated['role_id'],
            'is_active' => $validated['is_active'] ?? true,
        ]);

        return response()->json(['user' => $user->load('role')], 201);
    }

    public function updateUser(Request $request, User $user)
    {
        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'email' => ['sometimes', 'required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'password' => 'nullable|string|min:8',
            'role_id' => 'sometimes|required|exists:roles,id',
            'is_active' => 'sometimes|required|boolean',
        ]);

        if (!empty($validated['password'])) {
            $validated['password'] = Hash::make($validated['password']);
        } else {
            unset($validated['password']);
        }

        $user->update($validated);

        return response()->json(['user' => $user->fresh('role')]);
    }

    public function roles()
    {
        return response()->json(Role::with('permissions')->orderBy('label')->get());
    }

    public function storeRole(Request $request)
    {
        $request->merge([
            'name' => $request->input('name') ?: Str::slug($request->input('label', ''), '_'),
        ]);

        $validated = $request->validate([
            'name' => 'required|string|max:80|alpha_dash|unique:roles,name',
            'label' => 'required|string|max:120',
            'description' => 'nullable|string',
            'permission_ids' => 'array',
            'permission_ids.*' => 'exists:permissions,id',
        ]);

        $role = Role::create([
            'name' => $validated['name'],
            'label' => $validated['label'],
            'description' => $validated['description'] ?? null,
            'is_system' => false,
        ]);
        $role->permissions()->sync($validated['permission_ids'] ?? []);

        return response()->json(['role' => $role->load('permissions')], 201);
    }

    public function updateRole(Request $request, Role $role)
    {
        $validated = $request->validate([
            'label' => 'sometimes|required|string|max:120',
            'description' => 'nullable|string',
            'permission_ids' => 'array',
            'permission_ids.*' => 'exists:permissions,id',
        ]);

        $role->update($request->only(['label', 'description']));

        if (array_key_exists('permission_ids', $validated)) {
            $role->permissions()->sync($validated['permission_ids']);
        }

        return response()->json(['role' => $role->fresh('permissions')]);
    }

    public function catalogs(Request $request)
    {
        $query = CatalogItem::query();

        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }

        return response()->json($query->orderBy('type')->orderBy('sort_order')->orderBy('label')->get());
    }

    public function storeCatalog(Request $request)
    {
        $validated = $this->validateCatalog($request);

        $catalog = CatalogItem::create($validated);

        return response()->json(['catalog' => $catalog], 201);
    }

    public function updateCatalog(Request $request, CatalogItem $catalogItem)
    {
        $validated = $this->validateCatalog($request, $catalogItem);
        $catalogItem->update($validated);

        return response()->json(['catalog' => $catalogItem->fresh()]);
    }

    public function destroyCatalog(CatalogItem $catalogItem)
    {
        $catalogItem->delete();

        return response()->json(['message' => 'Catalogo eliminado']);
    }

    private function validateCatalog(Request $request, ?CatalogItem $catalog = null): array
    {
        $name = $request->input('name') ?: Str::slug($request->input('label', ''), '_');
        $request->merge(['name' => $name]);

        return $request->validate([
            'type' => 'required|string|max:80',
            'name' => [
                'required',
                'string',
                'max:120',
                'alpha_dash',
                Rule::unique('catalog_items', 'name')
                    ->where('type', $request->type)
                    ->ignore($catalog?->id),
            ],
            'label' => 'required|string|max:160',
            'description' => 'nullable|string',
            'price' => 'nullable|numeric|min:0',
            'duration_minutes' => 'nullable|integer|min:1',
            'color' => ['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'is_active' => 'boolean',
            'sort_order' => 'nullable|integer|min:0',
        ]);
    }
}
