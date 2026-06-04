<?php

namespace Database\Seeders;

use App\Models\Appointment;
use App\Models\CatalogItem;
use App\Models\MedicalRecord;
use App\Models\Patient;
use App\Models\Payment;
use App\Models\PaymentPlan;
use App\Models\Role;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class DemoDataSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function () {
            $admin = $this->adminUser();
            $this->seedCatalogs();

            $demoEmails = collect($this->patients())->pluck('email')->all();
            Patient::whereIn('email', $demoEmails)->delete();

            foreach ($this->patients() as $index => $patientData) {
                $patient = Patient::create([
                    ...$patientData,
                    'created_at' => Carbon::now()->subDays(45 - ($index * 3)),
                    'updated_at' => now(),
                ]);

                $this->createAppointmentsAndPayments($patient, $admin, $index);
                $this->createMedicalRecords($patient, $admin, $index);

                if ($index < 7) {
                    $this->createPaymentPlan($patient, $admin, $index);
                }
            }
        });
    }

    private function adminUser(): User
    {
        $adminRole = Role::where('name', 'admin')->first();

        return User::firstOrCreate(
            ['email' => 'admin@demo.com'],
            [
                'name' => 'Administrador Demo',
                'password' => Hash::make('password123'),
                'role_id' => $adminRole?->id,
                'is_active' => true,
            ]
        );
    }

    private function seedCatalogs(): void
    {
        foreach (array_values($this->treatments()) as $index => $treatment) {
            CatalogItem::updateOrCreate(
                ['type' => 'treatment', 'name' => $treatment['name']],
                [
                    'label' => $treatment['label'],
                    'description' => $treatment['description'],
                    'price' => $treatment['price'],
                    'duration_minutes' => null,
                    'color' => $treatment['color'],
                    'is_active' => true,
                    'sort_order' => $index + 1,
                ]
            );
        }
    }

    private function createAppointmentsAndPayments(Patient $patient, User $admin, int $patientIndex): void
    {
        $treatments = $this->treatments();
        $treatmentKeys = array_keys($treatments);
        $appointmentCounts = [8, 6, 5, 9, 4, 7, 3, 6, 5, 10, 4, 7];
        $statuses = ['completed', 'completed', 'confirmed', 'pending', 'cancelled', 'completed', 'confirmed', 'pending', 'completed'];
        $methods = ['cash', 'card', 'transfer', 'cash', 'card', 'transfer', 'card'];
        $hours = [8, 9, 10, 10, 11, 12, 13, 15, 16, 17, 18, 18];
        $count = $appointmentCounts[$patientIndex % count($appointmentCounts)];

        for ($i = 0; $i < $count; $i++) {
            $treatmentKey = $treatmentKeys[($patientIndex * 2 + $i) % count($treatmentKeys)];
            $treatment = $treatments[$treatmentKey];
            $status = $statuses[($patientIndex + $i) % count($statuses)];
            $daysAgo = (($patientIndex * 5) + ($i * 3)) % 31;
            $date = Carbon::now()
                ->subDays($daysAgo)
                ->setTime($hours[($patientIndex + $i) % count($hours)], $i % 2 === 0 ? 0 : 30);

            if ($status === 'pending' && $i % 3 === 0) {
                $date = Carbon::today()->setTime($hours[($patientIndex + $i) % count($hours)], 30);
            }

            $appointment = Appointment::create([
                'user_id' => $admin->id,
                'patient_id' => $patient->id,
                'patient_name' => $patient->name,
                'patient_email' => $patient->email,
                'patient_phone' => $patient->phone,
                'treatment_type' => $treatmentKey,
                'appointment_date' => $date,
                'notes' => $this->appointmentNote($status),
                'status' => $status,
                'created_at' => $date->copy()->subDays(2),
                'updated_at' => $date->copy()->subDays(1),
            ]);

            if ($status === 'completed') {
                Payment::create([
                    'patient_id' => $patient->id,
                    'appointment_id' => $appointment->id,
                    'payment_plan_id' => null,
                    'user_id' => $admin->id,
                    'amount' => $treatment['price'] + ($patientIndex * 95) + (($i % 3) * 150),
                    'payment_date' => $date->toDateString(),
                    'payment_method' => $methods[($patientIndex + $i) % count($methods)],
                    'payment_type' => $i % 4 === 0 ? 'partial' : 'full',
                    'reference_number' => 'DEMO-' . $patient->id . '-' . $appointment->id,
                    'notes' => 'Pago demo de ' . $treatment['label'],
                    'status' => 'completed',
                ]);
            } elseif ($status === 'confirmed' && $i % 2 === 0) {
                Payment::create([
                    'patient_id' => $patient->id,
                    'appointment_id' => $appointment->id,
                    'payment_plan_id' => null,
                    'user_id' => $admin->id,
                    'amount' => round($treatment['price'] * 0.35, 2),
                    'payment_date' => $date->copy()->subDays(1)->toDateString(),
                    'payment_method' => $methods[($patientIndex + $i) % count($methods)],
                    'payment_type' => 'partial',
                    'reference_number' => 'ANTICIPO-' . $patient->id . '-' . $appointment->id,
                    'notes' => 'Anticipo demo de ' . $treatment['label'],
                    'status' => 'completed',
                ]);
            }
        }
    }

    private function createMedicalRecords(Patient $patient, User $admin, int $patientIndex): void
    {
        $types = ['consultation', 'diagnosis', 'treatment', 'follow_up'];

        for ($i = 0; $i < 2; $i++) {
            MedicalRecord::create([
                'patient_id' => $patient->id,
                'appointment_id' => null,
                'user_id' => $admin->id,
                'record_date' => Carbon::now()->subDays(20 - $patientIndex - ($i * 5))->toDateString(),
                'record_type' => $types[($patientIndex + $i) % count($types)],
                'title' => $i === 0 ? 'Evaluacion inicial' : 'Seguimiento de tratamiento',
                'description' => 'Registro clinico demo para validar expediente.',
                'diagnosis' => 'Diagnostico demo sin valor clinico real.',
                'treatment_plan' => 'Plan demo de seguimiento mensual.',
                'medications' => null,
                'allergies' => $patient->allergies,
                'notes' => 'Datos generados para demostracion.',
            ]);
        }
    }

    private function createPaymentPlan(Patient $patient, User $admin, int $index): void
    {
        $plans = [
            ['name' => 'Ortodoncia invisible', 'total' => 42000, 'paid' => 12500, 'installments' => 12],
            ['name' => 'Brackets esteticos', 'total' => 28000, 'paid' => 9000, 'installments' => 10],
            ['name' => 'Brackets metalicos', 'total' => 22000, 'paid' => 7600, 'installments' => 10],
            ['name' => 'Endodoncia y corona', 'total' => 14500, 'paid' => 6200, 'installments' => 6],
            ['name' => 'Rehabilitacion con resinas', 'total' => 9800, 'paid' => 3800, 'installments' => 5],
            ['name' => 'Ortodoncia infantil', 'total' => 18500, 'paid' => 5200, 'installments' => 8],
            ['name' => 'Blanqueamiento dental', 'total' => 6500, 'paid' => 3000, 'installments' => 4],
        ];
        $planData = $plans[$index % count($plans)];
        $total = $planData['total'];
        $paid = $planData['paid'];

        $plan = PaymentPlan::create([
            'patient_id' => $patient->id,
            'user_id' => $admin->id,
            'treatment_name' => $planData['name'],
            'total_amount' => $total,
            'paid_amount' => $paid,
            'remaining_amount' => $total - $paid,
            'total_installments' => $planData['installments'],
            'paid_installments' => min(2 + $index, $planData['installments'] - 1),
            'installment_amount' => round($total / $planData['installments'], 2),
            'start_date' => Carbon::now()->subMonths(2)->addDays($index)->toDateString(),
            'end_date' => null,
            'status' => 'active',
            'notes' => 'Plan demo para validar cuentas por cobrar.',
        ]);

        Payment::create([
            'patient_id' => $patient->id,
            'appointment_id' => null,
            'payment_plan_id' => $plan->id,
            'user_id' => $admin->id,
            'amount' => $paid,
            'payment_date' => Carbon::now()->subDays(12 - $index)->toDateString(),
            'payment_method' => ['card', 'transfer', 'cash', 'card', 'transfer', 'cash', 'card'][$index % 7],
            'payment_type' => 'installment',
            'reference_number' => 'PLAN-DEMO-' . $patient->id,
            'notes' => 'Abono demo a plan de pago.',
            'status' => 'completed',
        ]);
    }

    private function patients(): array
    {
        return [
            [
                'name' => 'Mariana Lopez',
                'email' => 'mariana.lopez@demo.local',
                'phone' => '4441001001',
                'birth_date' => '1994-04-12',
                'blood_type' => 'O+',
                'allergies' => 'Penicilina',
                'chronic_conditions' => null,
                'current_medications' => null,
                'emergency_contact_name' => 'Luis Lopez',
                'emergency_contact_phone' => '4442001001',
                'insurance_info' => 'Particular',
                'notes' => 'Paciente demo recurrente.',
            ],
            [
                'name' => 'Carlos Mendez',
                'email' => 'carlos.mendez@demo.local',
                'phone' => '4441001002',
                'birth_date' => '1988-09-20',
                'blood_type' => 'A+',
                'allergies' => null,
                'chronic_conditions' => 'Hipertension controlada',
                'current_medications' => 'Losartan',
                'emergency_contact_name' => 'Ana Torres',
                'emergency_contact_phone' => '4442001002',
                'insurance_info' => 'Seguro dental empresarial',
                'notes' => 'Interesado en tratamiento estetico.',
            ],
            [
                'name' => 'Sofia Hernandez',
                'email' => 'sofia.hernandez@demo.local',
                'phone' => '4441001003',
                'birth_date' => '2012-01-08',
                'blood_type' => 'B+',
                'allergies' => null,
                'chronic_conditions' => null,
                'current_medications' => null,
                'emergency_contact_name' => 'Patricia Ruiz',
                'emergency_contact_phone' => '4442001003',
                'insurance_info' => 'Particular',
                'notes' => 'Paciente pediatrica demo.',
            ],
            [
                'name' => 'Roberto Garcia',
                'email' => 'roberto.garcia@demo.local',
                'phone' => '4441001004',
                'birth_date' => '1979-11-03',
                'blood_type' => 'AB+',
                'allergies' => 'Latex',
                'chronic_conditions' => null,
                'current_medications' => null,
                'emergency_contact_name' => 'Elena Garcia',
                'emergency_contact_phone' => '4442001004',
                'insurance_info' => 'Particular',
                'notes' => 'Paciente demo con plan activo.',
            ],
            [
                'name' => 'Valeria Sanchez',
                'email' => 'valeria.sanchez@demo.local',
                'phone' => '4441001005',
                'birth_date' => '2001-06-15',
                'blood_type' => 'O-',
                'allergies' => null,
                'chronic_conditions' => null,
                'current_medications' => null,
                'emergency_contact_name' => 'Monica Sanchez',
                'emergency_contact_phone' => '4442001005',
                'insurance_info' => 'Particular',
                'notes' => 'Paciente demo de alineadores.',
            ],
            [
                'name' => 'Andres Castillo',
                'email' => 'andres.castillo@demo.local',
                'phone' => '4441001006',
                'birth_date' => '1990-03-27',
                'blood_type' => 'A-',
                'allergies' => null,
                'chronic_conditions' => null,
                'current_medications' => null,
                'emergency_contact_name' => 'Diego Castillo',
                'emergency_contact_phone' => '4442001006',
                'insurance_info' => 'Particular',
                'notes' => 'Paciente demo para estadisticas.',
            ],
            [
                'name' => 'Lucia Fernandez',
                'email' => 'lucia.fernandez@demo.local',
                'phone' => '4441001007',
                'birth_date' => '1985-12-04',
                'blood_type' => 'O+',
                'allergies' => null,
                'chronic_conditions' => null,
                'current_medications' => null,
                'emergency_contact_name' => 'Javier Fernandez',
                'emergency_contact_phone' => '4442001007',
                'insurance_info' => 'Particular',
                'notes' => 'Seguimiento de endodoncia.',
            ],
            [
                'name' => 'Mateo Rios',
                'email' => 'mateo.rios@demo.local',
                'phone' => '4441001008',
                'birth_date' => '2016-08-19',
                'blood_type' => 'B-',
                'allergies' => null,
                'chronic_conditions' => null,
                'current_medications' => null,
                'emergency_contact_name' => 'Claudia Rios',
                'emergency_contact_phone' => '4442001008',
                'insurance_info' => 'Particular',
                'notes' => 'Paciente pediatrico demo.',
            ],
            [
                'name' => 'Daniela Torres',
                'email' => 'daniela.torres@demo.local',
                'phone' => '4441001009',
                'birth_date' => '1998-02-11',
                'blood_type' => 'A+',
                'allergies' => 'Ibuprofeno',
                'chronic_conditions' => null,
                'current_medications' => null,
                'emergency_contact_name' => 'Oscar Torres',
                'emergency_contact_phone' => '4442001009',
                'insurance_info' => 'Seguro dental empresarial',
                'notes' => 'Paciente demo de limpieza y resinas.',
            ],
            [
                'name' => 'Fernando Nava',
                'email' => 'fernando.nava@demo.local',
                'phone' => '4441001010',
                'birth_date' => '1972-05-23',
                'blood_type' => 'O+',
                'allergies' => null,
                'chronic_conditions' => 'Diabetes tipo 2 controlada',
                'current_medications' => 'Metformina',
                'emergency_contact_name' => 'Irene Nava',
                'emergency_contact_phone' => '4442001010',
                'insurance_info' => 'Particular',
                'notes' => 'Paciente demo con varias consultas.',
            ],
            [
                'name' => 'Regina Morales',
                'email' => 'regina.morales@demo.local',
                'phone' => '4441001011',
                'birth_date' => '2007-10-02',
                'blood_type' => 'AB-',
                'allergies' => null,
                'chronic_conditions' => null,
                'current_medications' => null,
                'emergency_contact_name' => 'Laura Morales',
                'emergency_contact_phone' => '4442001011',
                'insurance_info' => 'Particular',
                'notes' => 'Ortodoncia infantil demo.',
            ],
            [
                'name' => 'Hector Salazar',
                'email' => 'hector.salazar@demo.local',
                'phone' => '4441001012',
                'birth_date' => '1968-07-30',
                'blood_type' => 'A-',
                'allergies' => 'Aspirina',
                'chronic_conditions' => 'Hipertension controlada',
                'current_medications' => 'Amlodipino',
                'emergency_contact_name' => 'Martha Salazar',
                'emergency_contact_phone' => '4442001012',
                'insurance_info' => 'Particular',
                'notes' => 'Paciente demo para tratamientos restaurativos.',
            ],
        ];
    }

    private function treatments(): array
    {
        return [
            'consulta_general' => [
                'name' => 'consulta_general',
                'label' => 'Consulta general',
                'description' => 'Valoracion inicial y diagnostico.',
                'price' => 650,
                'color' => '#2563eb',
            ],
            'limpieza_dental' => [
                'name' => 'limpieza_dental',
                'label' => 'Limpieza dental',
                'description' => 'Profilaxis y limpieza preventiva.',
                'price' => 900,
                'color' => '#0891b2',
            ],
            'resinas' => [
                'name' => 'resinas',
                'label' => 'Resinas',
                'description' => 'Restauracion dental con resina.',
                'price' => 1200,
                'color' => '#16a34a',
            ],
            'extraccion_simple' => [
                'name' => 'extraccion_simple',
                'label' => 'Extraccion simple',
                'description' => 'Extraccion dental sin cirugia.',
                'price' => 1500,
                'color' => '#dc2626',
            ],
            'endodoncia' => [
                'name' => 'endodoncia',
                'label' => 'Endodoncia',
                'description' => 'Tratamiento de conducto.',
                'price' => 3800,
                'color' => '#7c3aed',
            ],
            'blanqueamiento_dental' => [
                'name' => 'blanqueamiento_dental',
                'label' => 'Blanqueamiento dental',
                'description' => 'Tratamiento estetico de blanqueamiento.',
                'price' => 2600,
                'color' => '#f59e0b',
            ],
            'brackets_metalicos' => [
                'name' => 'brackets_metalicos',
                'label' => 'Brackets metalicos',
                'description' => 'Cita de ortodoncia con brackets metalicos.',
                'price' => 1800,
                'color' => '#475569',
            ],
            'brackets_esteticos' => [
                'name' => 'brackets_esteticos',
                'label' => 'Brackets esteticos',
                'description' => 'Cita de ortodoncia con brackets esteticos.',
                'price' => 2300,
                'color' => '#0f766e',
            ],
            'ortodoncia_invisible' => [
                'name' => 'ortodoncia_invisible',
                'label' => 'Ortodoncia invisible',
                'description' => 'Seguimiento de alineadores invisibles.',
                'price' => 3200,
                'color' => '#9333ea',
            ],
            'ortodoncia_infantil' => [
                'name' => 'ortodoncia_infantil',
                'label' => 'Ortodoncia infantil',
                'description' => 'Control de ortodoncia pediatrica.',
                'price' => 1600,
                'color' => '#ea580c',
            ],
        ];
    }

    private function appointmentNote(string $status): string
    {
        return match ($status) {
            'completed' => 'Consulta completada con evolucion registrada.',
            'confirmed' => 'Cita confirmada por WhatsApp.',
            'pending' => 'Pendiente de confirmacion del paciente.',
            'cancelled' => 'Paciente solicito reagendar.',
            default => 'Dato demo para validar estadisticas.',
        };
    }
}
