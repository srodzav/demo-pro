<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class AppointmentController extends Controller
{
    /**
     * Display a listing of appointments
     */
    public function index(Request $request)
    {
        $query = Appointment::with('user')
            ->where('user_id', $request->user()->id);

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
            'patient_name' => 'required|string|max:255',
            'patient_email' => 'required|email|max:255',
            'patient_phone' => 'required|string|max:20',
            'treatment_type' => 'required|in:brackets_metalicos,brackets_esteticos,ortodoncia_invisible,ortodoncia_infantil',
            'appointment_date' => 'required|date|after:now',
            'notes' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Error de validación',
                'errors' => $validator->errors()
            ], 422);
        }

        $appointment = Appointment::create([
            'user_id' => $request->user()->id,
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
            'appointment' => $appointment->load('user'),
        ], 201);
    }

    /**
     * Display the specified appointment
     */
    public function show(Request $request, Appointment $appointment)
    {
        // Check ownership
        if ($appointment->user_id !== $request->user()->id) {
            return response()->json([
                'message' => 'No autorizado'
            ], 403);
        }

        return response()->json([
            'appointment' => $appointment->load('user'),
        ]);
    }

    /**
     * Update the specified appointment
     */
    public function update(Request $request, Appointment $appointment)
    {
        // Check ownership
        if ($appointment->user_id !== $request->user()->id) {
            return response()->json([
                'message' => 'No autorizado'
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'patient_name' => 'sometimes|required|string|max:255',
            'patient_email' => 'sometimes|required|email|max:255',
            'patient_phone' => 'sometimes|required|string|max:20',
            'treatment_type' => 'sometimes|required|in:brackets_metalicos,brackets_esteticos,ortodoncia_invisible,ortodoncia_infantil',
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
            'appointment' => $appointment->fresh()->load('user'),
        ]);
    }

    /**
     * Remove the specified appointment
     */
    public function destroy(Request $request, Appointment $appointment)
    {
        // Check ownership
        if ($appointment->user_id !== $request->user()->id) {
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
        if ($appointment->user_id !== $request->user()->id) {
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
        if ($appointment->user_id !== $request->user()->id) {
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
        if ($appointment->user_id !== $request->user()->id) {
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

        $appointment->update([
            'appointment_date' => $request->appointment_date,
            'status' => 'pending',
        ]);

        return response()->json([
            'message' => 'Cita reagendada exitosamente',
            'appointment' => $appointment->fresh(),
        ]);
    }
}
