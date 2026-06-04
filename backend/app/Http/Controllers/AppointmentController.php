<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Models\Patient;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class AppointmentController extends Controller
{
    /**
     * Check if there's a time conflict with existing appointments
     */
    private function hasTimeConflict($appointmentDate, $excludeId = null)
    {
        if (!$appointmentDate) {
            return false;
        }

        $checkDate = \Carbon\Carbon::parse($appointmentDate);
        
        // Check for appointments within 30 minutes before or after
        $query = Appointment::where('appointment_date', '!=', null)
            ->where('status', '!=', 'cancelled')
            ->where(function($q) use ($checkDate) {
                $q->whereBetween('appointment_date', [
                    $checkDate->copy()->subMinutes(30),
                    $checkDate->copy()->addMinutes(30)
                ]);
            });

        if ($excludeId) {
            $query->where('id', '!=', $excludeId);
        }

        return $query->exists();
    }

    /**
     * Store appointment from public form (no authentication required)
     */
    public function storePublic(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'patient_name' => 'required|string|max:255',
            'patient_email' => 'required|email|max:255',
            'patient_phone' => 'required|string|max:20',
            'treatment_type' => 'required|string',
            // appointment_date is optional for public requests (user may only request info)
            'appointment_date' => 'nullable|date',
            'notes' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Error de validación',
                'errors' => $validator->errors()
            ], 422);
        }

        // Check for time conflicts
        if ($request->appointment_date && $this->hasTimeConflict($request->appointment_date)) {
            return response()->json([
                'message' => 'El horario seleccionado no está disponible. Por favor elige otro horario.',
                'errors' => ['appointment_date' => ['Horario no disponible']]
            ], 422);
        }

        // Find or create patient
        $patient = Patient::where('email', $request->patient_email)->first();
        
        if (!$patient) {
            $patient = Patient::create([
                'name' => $request->patient_name,
                'email' => $request->patient_email,
                'phone' => $request->patient_phone,
            ]);
        } else {
            // Update phone if different
            if ($patient->phone !== $request->patient_phone) {
                $patient->update(['phone' => $request->patient_phone]);
            }
        }

        // Create appointment linked to patient
        $appointment = Appointment::create([
            'user_id' => null, // No admin user associated yet
            'patient_id' => $patient->id,
            'patient_name' => $request->patient_name,
            'patient_email' => $request->patient_email,
            'patient_phone' => $request->patient_phone,
            'treatment_type' => $request->treatment_type,
            'appointment_date' => $request->appointment_date ?? null,
            'notes' => $request->notes,
            'status' => 'pending',
        ]);

        // TODO: Send email notification to admin

        return response()->json([
            'message' => 'Solicitud de cita recibida. Te contactaremos pronto.',
            'appointment' => $appointment->load('patient'),
        ], 201);
    }

    /**
     * Display a listing of appointments
     */
    public function index(Request $request)
    {
        // Admin sees all appointments (including public ones)
        $query = Appointment::with(['user', 'patient']);

        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        // Filter by date range
        if ($request->has('from_date')) {
            $query->whereDate('appointment_date', '>=', $request->from_date);
        }
        if ($request->has('to_date')) {
            $query->whereDate('appointment_date', '<=', $request->to_date);
        }

        $appointments = $query->orderBy('appointment_date', 'desc')->paginate(15);

        return response()->json($appointments);
    }

    /**
     * Store a newly created appointment
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'patient_id' => 'nullable|exists:patients,id',
            'patient_name' => 'required|string|max:255',
            'patient_email' => 'required|email|max:255',
            'patient_phone' => 'required|string|max:20',
            'treatment_type' => 'required|string',
            'appointment_date' => 'required|date|after:now',
            'notes' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Error de validación',
                'errors' => $validator->errors()
            ], 422);
        }

        // Check for time conflicts
        if ($this->hasTimeConflict($request->appointment_date)) {
            return response()->json([
                'message' => 'El horario seleccionado no está disponible. Por favor elige otro horario.',
                'errors' => ['appointment_date' => ['Horario no disponible']]
            ], 422);
        }

        // If patient_id provided, use it; otherwise find or create patient
        $patientId = $request->patient_id;
        
        if (!$patientId) {
            $patient = Patient::where('email', $request->patient_email)->first();
            
            if (!$patient) {
                $patient = Patient::create([
                    'name' => $request->patient_name,
                    'email' => $request->patient_email,
                    'phone' => $request->patient_phone,
                ]);
            }
            
            $patientId = $patient->id;
        }

        $appointment = Appointment::create([
            'user_id' => $request->user()->id,
            'patient_id' => $patientId,
            'patient_name' => $request->patient_name,
            'patient_email' => $request->patient_email,
            'patient_phone' => $request->patient_phone,
            'treatment_type' => $request->treatment_type,
            'appointment_date' => $request->appointment_date,
            'notes' => $request->notes,
            'status' => 'pending',
        ]);

        return response()->json([
            'message' => 'Cita creada exitosamente',
            'appointment' => $appointment->load(['user', 'patient']),
        ], 201);
    }

    /**
     * Display the specified appointment
     */
    public function show(Request $request, Appointment $appointment)
    {
        // Allow any admin to view public appointments (user_id = null)
        if ($appointment->user_id !== null && $appointment->user_id !== $request->user()->id) {
            return response()->json([
                'message' => 'No autorizado'
            ], 403);
        }

        return response()->json([
            'appointment' => $appointment->load(['user', 'patient']),
        ]);
    }

    /**
     * Update the specified appointment
     */
    public function update(Request $request, Appointment $appointment)
    {
        // Allow any admin to update public appointments (user_id = null)
        if ($appointment->user_id !== null && $appointment->user_id !== $request->user()->id) {
            return response()->json([
                'message' => 'No autorizado'
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'patient_name' => 'sometimes|required|string|max:255',
            'patient_email' => 'sometimes|required|email|max:255',
            'patient_phone' => 'sometimes|required|string|max:20',
            'treatment_type' => 'sometimes|required|string',
            'appointment_date' => 'sometimes|required|date',
            'notes' => 'nullable|string',
            'status' => 'sometimes|required|in:pending,confirmed,cancelled,completed',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Error de validación',
                'errors' => $validator->errors()
            ], 422);
        }

        // Check for time conflicts when updating appointment_date
        if ($request->has('appointment_date') && $this->hasTimeConflict($request->appointment_date, $appointment->id)) {
            return response()->json([
                'message' => 'El horario seleccionado no está disponible. Por favor elige otro horario.',
                'errors' => ['appointment_date' => ['Horario no disponible']]
            ], 422);
        }

        $appointment->update($request->only([
            'patient_name',
            'patient_email',
            'patient_phone',
            'treatment_type',
            'appointment_date',
            'notes',
            'status',
        ]));

        return response()->json([
            'message' => 'Cita actualizada exitosamente',
            'appointment' => $appointment->fresh()->load(['user', 'patient']),
        ]);
    }

    /**
     * Remove the specified appointment
     */
    public function destroy(Request $request, Appointment $appointment)
    {
        // Allow any admin to delete public appointments (user_id = null)
        if ($appointment->user_id !== null && $appointment->user_id !== $request->user()->id) {
            return response()->json([
                'message' => 'No autorizado'
            ], 403);
        }

        $appointment->delete();

        return response()->json([
            'message' => 'Cita eliminada exitosamente',
        ]);
    }

    /**
     * Confirm appointment
     */
    public function confirm(Request $request, Appointment $appointment)
    {
        // Allow any admin to confirm public appointments (user_id = null)
        // Only the creator can confirm their own appointments
        if ($appointment->user_id !== null && $appointment->user_id !== $request->user()->id) {
            return response()->json(['message' => 'No autorizado'], 403);
        }

        $appointment->update(['status' => 'confirmed']);

        return response()->json([
            'message' => 'Cita confirmada exitosamente',
            'appointment' => $appointment->fresh(),
        ]);
    }

    /**
     * Cancel appointment
     */
    public function cancel(Request $request, Appointment $appointment)
    {
        // Allow any admin to cancel public appointments (user_id = null)
        // Only the creator can cancel their own appointments
        if ($appointment->user_id !== null && $appointment->user_id !== $request->user()->id) {
            return response()->json(['message' => 'No autorizado'], 403);
        }

        $appointment->update(['status' => 'cancelled']);

        return response()->json([
            'message' => 'Cita cancelada exitosamente',
            'appointment' => $appointment->fresh(),
        ]);
    }

    /**
     * Reschedule appointment
     */
    public function reschedule(Request $request, Appointment $appointment)
    {
        // Allow any admin to reschedule public appointments (user_id = null)
        // Only the creator can reschedule their own appointments
        if ($appointment->user_id !== null && $appointment->user_id !== $request->user()->id) {
            return response()->json(['message' => 'No autorizado'], 403);
        }

        $validator = Validator::make($request->all(), [
            'appointment_date' => 'required|date|after:now',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Error de validación',
                'errors' => $validator->errors()
            ], 422);
        }

        // Check for time conflicts
        if ($this->hasTimeConflict($request->appointment_date, $appointment->id)) {
            return response()->json([
                'message' => 'El horario seleccionado no está disponible. Por favor elige otro horario.',
                'errors' => ['appointment_date' => ['Horario no disponible']]
            ], 422);
        }

        $appointment->update([
            'appointment_date' => $request->appointment_date,
            'status' => 'pending',
        ]);

        return response()->json([
            'message' => 'Cita reagendada exitosamente',
            'appointment' => $appointment->fresh(),
        ]);
    }

    /**
     * Get weekly calendar appointments
     */
    public function weeklyCalendar(Request $request)
    {
        $startDate = $request->has('start_date') 
            ? \Carbon\Carbon::parse($request->start_date)->startOfDay()
            : \Carbon\Carbon::now()->startOfWeek(\Carbon\Carbon::MONDAY);

        $endDate = $startDate->copy()->addDays(5)->endOfDay(); // Lunes a Sábado

        $appointments = Appointment::with(['user', 'patient'])
            ->whereNotNull('appointment_date')
            ->where('status', '!=', 'cancelled')
            ->whereBetween('appointment_date', [$startDate, $endDate])
            ->orderBy('appointment_date')
            ->get();

        return response()->json([
            'start_date' => $startDate->toDateString(),
            'end_date' => $endDate->toDateString(),
            'appointments' => $appointments,
        ]);
    }
}
