<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('label');
            $table->text('description')->nullable();
            $table->boolean('is_system')->default(false);
            $table->timestamps();
        });

        Schema::create('permissions', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('label');
            $table->string('group')->default('general');
            $table->timestamps();
        });

        Schema::create('permission_role', function (Blueprint $table) {
            $table->id();
            $table->foreignId('role_id')->constrained()->onDelete('cascade');
            $table->foreignId('permission_id')->constrained()->onDelete('cascade');
            $table->timestamps();

            $table->unique(['role_id', 'permission_id']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('role_id')->nullable()->after('password')->constrained()->nullOnDelete();
            $table->boolean('is_active')->default(true)->after('role_id');
        });

        Schema::create('app_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->json('value')->nullable();
            $table->string('type')->default('string');
            $table->timestamps();
        });

        Schema::create('clinic_settings', function (Blueprint $table) {
            $table->id();
            $table->string('clinic_name')->default('Demo Dental');
            $table->string('legal_name')->nullable();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('whatsapp')->nullable();
            $table->string('address')->nullable();
            $table->string('city')->nullable();
            $table->string('state')->nullable();
            $table->string('primary_color')->default('#003c97');
            $table->string('accent_color')->default('#007bff');
            $table->string('logo_url')->nullable();
            $table->json('business_hours')->nullable();
            $table->json('social_links')->nullable();
            $table->timestamps();
        });

        Schema::create('catalog_items', function (Blueprint $table) {
            $table->id();
            $table->string('type');
            $table->string('name');
            $table->string('label');
            $table->text('description')->nullable();
            $table->decimal('price', 10, 2)->nullable();
            $table->unsignedInteger('duration_minutes')->nullable();
            $table->string('color')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['type', 'name']);
            $table->index(['type', 'is_active']);
        });

        $this->seedDefaults();
    }

    public function down(): void
    {
        Schema::dropIfExists('catalog_items');
        Schema::dropIfExists('clinic_settings');
        Schema::dropIfExists('app_settings');

        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('role_id');
            $table->dropColumn('is_active');
        });

        Schema::dropIfExists('permission_role');
        Schema::dropIfExists('permissions');
        Schema::dropIfExists('roles');
    }

    private function seedDefaults(): void
    {
        $now = now();

        $permissions = [
            ['name' => 'settings.manage', 'label' => 'Administrar configuracion', 'group' => 'Configuracion'],
            ['name' => 'users.manage', 'label' => 'Administrar usuarios', 'group' => 'Usuarios'],
            ['name' => 'roles.manage', 'label' => 'Administrar roles', 'group' => 'Usuarios'],
            ['name' => 'clinic.manage', 'label' => 'Administrar clinica', 'group' => 'Clinica'],
            ['name' => 'catalogs.manage', 'label' => 'Administrar catalogos', 'group' => 'Catalogos'],
            ['name' => 'appointments.manage', 'label' => 'Administrar citas', 'group' => 'Operacion'],
            ['name' => 'patients.manage', 'label' => 'Administrar pacientes', 'group' => 'Operacion'],
            ['name' => 'medical_records.manage', 'label' => 'Administrar expedientes', 'group' => 'Operacion'],
            ['name' => 'payments.manage', 'label' => 'Administrar pagos', 'group' => 'Finanzas'],
            ['name' => 'statistics.view', 'label' => 'Ver estadisticas', 'group' => 'Reportes'],
        ];

        foreach ($permissions as $permission) {
            DB::table('permissions')->insert([...$permission, 'created_at' => $now, 'updated_at' => $now]);
        }

        $roles = [
            ['name' => 'admin', 'label' => 'Administrador', 'description' => 'Acceso completo al sistema.', 'is_system' => true],
            ['name' => 'doctor', 'label' => 'Doctor', 'description' => 'Gestiona pacientes, citas y expedientes.', 'is_system' => true],
            ['name' => 'reception', 'label' => 'Recepcion', 'description' => 'Gestiona agenda y pacientes.', 'is_system' => true],
            ['name' => 'finance', 'label' => 'Finanzas', 'description' => 'Gestiona pagos y reportes financieros.', 'is_system' => true],
        ];

        foreach ($roles as $role) {
            DB::table('roles')->insert([...$role, 'created_at' => $now, 'updated_at' => $now]);
        }

        $permissionIds = DB::table('permissions')->pluck('id', 'name');
        $roleIds = DB::table('roles')->pluck('id', 'name');
        $rolePermissions = [
            'admin' => $permissionIds->keys()->all(),
            'doctor' => ['appointments.manage', 'patients.manage', 'medical_records.manage', 'statistics.view'],
            'reception' => ['appointments.manage', 'patients.manage'],
            'finance' => ['payments.manage', 'statistics.view'],
        ];

        foreach ($rolePermissions as $roleName => $permissionNames) {
            foreach ($permissionNames as $permissionName) {
                DB::table('permission_role')->insert([
                    'role_id' => $roleIds[$roleName],
                    'permission_id' => $permissionIds[$permissionName],
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }

        DB::table('users')->whereNull('role_id')->update([
            'role_id' => $roleIds['admin'],
            'is_active' => true,
        ]);

        DB::table('app_settings')->insert([
            'key' => 'public_registration_enabled',
            'value' => json_encode(false),
            'type' => 'boolean',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::table('clinic_settings')->insert([
            'clinic_name' => 'Demo Dental',
            'phone' => '444 312 2257',
            'whatsapp' => '4443122257',
            'email' => 'contacto@demo-dental.com',
            'address' => 'Av. Principal 123',
            'city' => 'San Luis Potosi',
            'state' => 'SLP',
            'business_hours' => json_encode([
                'monday' => '09:00-18:00',
                'tuesday' => '09:00-18:00',
                'wednesday' => '09:00-18:00',
                'thursday' => '09:00-18:00',
                'friday' => '09:00-18:00',
                'saturday' => '09:00-14:00',
                'sunday' => 'Cerrado',
            ]),
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $catalogs = [
            ['type' => 'treatment', 'name' => 'brackets_metalicos', 'label' => 'Brackets Metalicos', 'duration_minutes' => 60, 'sort_order' => 10],
            ['type' => 'treatment', 'name' => 'brackets_esteticos', 'label' => 'Brackets Esteticos', 'duration_minutes' => 60, 'sort_order' => 20],
            ['type' => 'treatment', 'name' => 'ortodoncia_invisible', 'label' => 'Ortodoncia Invisible', 'duration_minutes' => 45, 'sort_order' => 30],
            ['type' => 'treatment', 'name' => 'ortodoncia_infantil', 'label' => 'Ortodoncia Infantil', 'duration_minutes' => 45, 'sort_order' => 40],
            ['type' => 'payment_method', 'name' => 'cash', 'label' => 'Efectivo', 'sort_order' => 10],
            ['type' => 'payment_method', 'name' => 'card', 'label' => 'Tarjeta', 'sort_order' => 20],
            ['type' => 'payment_method', 'name' => 'transfer', 'label' => 'Transferencia', 'sort_order' => 30],
            ['type' => 'payment_method', 'name' => 'check', 'label' => 'Cheque', 'sort_order' => 40],
            ['type' => 'payment_method', 'name' => 'other', 'label' => 'Otro', 'sort_order' => 50],
            ['type' => 'appointment_status', 'name' => 'pending', 'label' => 'Pendiente', 'color' => '#f59e0b', 'sort_order' => 10],
            ['type' => 'appointment_status', 'name' => 'confirmed', 'label' => 'Confirmada', 'color' => '#10b981', 'sort_order' => 20],
            ['type' => 'appointment_status', 'name' => 'cancelled', 'label' => 'Cancelada', 'color' => '#ef4444', 'sort_order' => 30],
            ['type' => 'appointment_status', 'name' => 'completed', 'label' => 'Completada', 'color' => '#3b82f6', 'sort_order' => 40],
            ['type' => 'record_type', 'name' => 'consultation', 'label' => 'Consulta', 'sort_order' => 10],
            ['type' => 'record_type', 'name' => 'treatment', 'label' => 'Tratamiento', 'sort_order' => 20],
            ['type' => 'record_type', 'name' => 'follow_up', 'label' => 'Seguimiento', 'sort_order' => 30],
            ['type' => 'record_type', 'name' => 'diagnosis', 'label' => 'Diagnostico', 'sort_order' => 40],
        ];

        foreach ($catalogs as $catalog) {
            DB::table('catalog_items')->insert([...$catalog, 'created_at' => $now, 'updated_at' => $now]);
        }
    }
};
