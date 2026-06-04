<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Models\CatalogItem;
use App\Models\Patient;
use App\Models\Payment;
use App\Models\PaymentPlan;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class StatisticsController extends Controller
{
    /**
     * Get dashboard statistics
     */
    public function dashboard(Request $request)
    {
        $userId = auth()->id();
        $currentMonth = Carbon::now()->startOfMonth();
        $lastMonth = Carbon::now()->subMonth()->startOfMonth();

        // Total patients
        $totalPatients = Patient::count();
        $newPatientsThisMonth = Patient::where('created_at', '>=', $currentMonth)->count();

        // Appointments statistics
        $totalAppointments = Appointment::where('user_id', $userId)->count();
        $appointmentsThisMonth = Appointment::where('user_id', $userId)
            ->where('created_at', '>=', $currentMonth)
            ->count();
        $upcomingAppointments = Appointment::where('user_id', $userId)
            ->where('appointment_date', '>=', now())
            ->where('status', '!=', 'cancelled')
            ->count();
        $pendingAppointments = Appointment::where('user_id', $userId)
            ->where('status', 'pending')
            ->count();

        // Financial statistics
        $incomeThisMonth = Payment::where('status', 'completed')
            ->where('payment_date', '>=', $currentMonth)
            ->sum('amount');
        $incomeLastMonth = Payment::where('status', 'completed')
            ->whereBetween('payment_date', [$lastMonth, $currentMonth])
            ->sum('amount');
        
        $totalIncome = Payment::where('status', 'completed')->sum('amount');
        $accountsReceivable = PaymentPlan::where('status', 'active')->sum('remaining_amount');

        // Calculate percentage change
        $incomeChange = 0;
        if ($incomeLastMonth > 0) {
            $incomeChange = (($incomeThisMonth - $incomeLastMonth) / $incomeLastMonth) * 100;
        }

        return response()->json([
            'patients' => [
                'total' => $totalPatients,
                'new_this_month' => $newPatientsThisMonth,
            ],
            'appointments' => [
                'total' => $totalAppointments,
                'this_month' => $appointmentsThisMonth,
                'upcoming' => $upcomingAppointments,
                'pending' => $pendingAppointments,
            ],
            'finance' => [
                'income_this_month' => round($incomeThisMonth, 2),
                'income_last_month' => round($incomeLastMonth, 2),
                'income_change_percentage' => round($incomeChange, 2),
                'total_income' => round($totalIncome, 2),
                'accounts_receivable' => round($accountsReceivable, 2),
            ],
        ]);
    }

    /**
     * Get monthly income report
     */
    public function monthlyIncome(Request $request)
    {
        $year = $request->get('year', Carbon::now()->year);

        $monthlyIncome = Payment::select(
            DB::raw('MONTH(payment_date) as month'),
            DB::raw('SUM(amount) as total')
        )
            ->where('status', 'completed')
            ->whereYear('payment_date', $year)
            ->groupBy('month')
            ->orderBy('month')
            ->get();

        // Fill missing months with 0
        $result = [];
        for ($i = 1; $i <= 12; $i++) {
            $found = $monthlyIncome->firstWhere('month', $i);
            $result[] = [
                'month' => $i,
                'month_name' => Carbon::create()->month($i)->format('F'),
                'total' => $found ? round($found->total, 2) : 0,
            ];
        }

        return response()->json($result);
    }

    /**
     * Get daily income report for a specific month
     */
    public function dailyIncome(Request $request)
    {
        $month = $request->get('month', Carbon::now()->month);
        $year = $request->get('year', Carbon::now()->year);

        $dailyIncome = Payment::select(
            DB::raw('DATE(payment_date) as date'),
            DB::raw('SUM(amount) as total')
        )
            ->where('status', 'completed')
            ->whereYear('payment_date', $year)
            ->whereMonth('payment_date', $month)
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        return response()->json($dailyIncome);
    }

    /**
     * Get treatment types statistics
     */
    public function treatmentStats(Request $request)
    {
        [$fromDate, $toDate] = $this->dateRange($request, Carbon::now()->subYear());

        $treatmentStats = Appointment::query()
            ->leftJoin('payments', function ($join) {
                $join->on('appointments.id', '=', 'payments.appointment_id')
                    ->where('payments.status', '=', 'completed');
            })
            ->select(
                'appointments.treatment_type',
                DB::raw('COUNT(DISTINCT appointments.id) as count'),
                DB::raw('COALESCE(SUM(payments.amount), 0) as total_revenue')
            )
            ->whereBetween('appointment_date', [$fromDate, $toDate])
            ->groupBy('appointments.treatment_type')
            ->orderBy('count', 'desc')
            ->get();

        $totalAppointments = $treatmentStats->sum('count');
        $treatmentLabels = CatalogItem::where('type', 'treatment')->pluck('label', 'name');

        return response()->json($treatmentStats->map(function ($item) use ($totalAppointments, $treatmentLabels) {
            return [
                'treatment' => $treatmentLabels[$item->treatment_type] ?? ucwords(str_replace('_', ' ', $item->treatment_type)),
                'count' => (int) $item->count,
                'total_revenue' => round((float) $item->total_revenue, 2),
                'percentage' => $totalAppointments > 0 ? round(($item->count / $totalAppointments) * 100, 2) : 0,
            ];
        }));
    }

    /**
     * Get appointment status statistics
     */
    public function appointmentStats(Request $request)
    {
        [$fromDate, $toDate] = $this->dateRange($request, Carbon::now()->startOfMonth());

        $statusStats = Appointment::select(
            'status',
            DB::raw('COUNT(*) as count')
        )
            ->whereBetween('appointment_date', [$fromDate, $toDate])
            ->groupBy('status')
            ->get();

        $totalAppointments = $statusStats->sum('count');
        $confirmed = (int) ($statusStats->firstWhere('status', 'confirmed')->count ?? 0);
        $pending = (int) ($statusStats->firstWhere('status', 'pending')->count ?? 0);
        $cancelled = (int) ($statusStats->firstWhere('status', 'cancelled')->count ?? 0);
        $completed = (int) ($statusStats->firstWhere('status', 'completed')->count ?? 0);
        $cancellationRate = $totalAppointments > 0 ? ($cancelled / $totalAppointments) * 100 : 0;

        return response()->json([
            'total' => (int) $totalAppointments,
            'confirmed' => $confirmed,
            'pending' => $pending,
            'cancelled' => $cancelled,
            'completed' => $completed,
            'cancellation_rate' => round($cancellationRate, 2),
        ]);
    }

    /**
     * Get payment method statistics
     */
    public function paymentMethodStats(Request $request)
    {
        [$fromDate, $toDate] = $this->dateRange($request, Carbon::now()->startOfMonth());

        $paymentMethodStats = Payment::select(
            'payment_method',
            DB::raw('COUNT(*) as count'),
            DB::raw('SUM(amount) as total')
        )
            ->where('status', 'completed')
            ->whereBetween('payment_date', [$fromDate, $toDate])
            ->groupBy('payment_method')
            ->get();

        return response()->json($paymentMethodStats);
    }

    /**
     * Get accounts receivable details
     */
    public function accountsReceivable()
    {
        $activePaymentPlans = PaymentPlan::with(['patient'])
            ->where('status', 'active')
            ->where('remaining_amount', '>', 0)
            ->orderBy('remaining_amount', 'desc')
            ->get();

        $totalReceivable = $activePaymentPlans->sum('remaining_amount');

        return response()->json([
            'total_receivable' => round($totalReceivable, 2),
            'active_plans_count' => $activePaymentPlans->count(),
            'plans' => $activePaymentPlans,
        ]);
    }

    /**
     * Get top patients by spending
     */
    public function topPatientsBySpending(Request $request)
    {
        $limit = $request->get('limit', 10);

        $topPatients = Patient::query()
            ->withCount('appointments')
            ->withSum(['payments as total_spent' => function ($query) {
                $query->where('status', 'completed');
            }], 'amount')
            ->with(['latestAppointment:id,patient_id,appointment_date'])
            ->orderByDesc('total_spent')
            ->limit($limit)
            ->get();

        return response()->json($topPatients->map(function ($patient) {
            return [
                'patient_id' => (int) $patient->id,
                'patient_name' => $patient->name,
                'total_appointments' => (int) $patient->appointments_count,
                'total_spent' => round((float) $patient->total_spent, 2),
                'last_appointment' => $patient->latestAppointment?->appointment_date,
            ];
        }));
    }

    /**
     * Get peak hours for appointments
     */
    public function peakHours(Request $request)
    {
        [$fromDate, $toDate] = $this->dateRange($request, Carbon::now()->subMonth());

        $peakHours = Appointment::select(
            DB::raw('HOUR(appointment_date) as hour'),
            DB::raw('COUNT(*) as count')
        )
            ->whereNotNull('appointment_date')
            ->where('status', '!=', 'cancelled')
            ->whereBetween('appointment_date', [$fromDate, $toDate])
            ->groupBy('hour')
            ->orderBy('hour')
            ->get();

        $totalAppointments = $peakHours->sum('count');

        return response()->json($peakHours->map(function ($hour) use ($totalAppointments) {
            return [
                'hour' => (int) $hour->hour,
                'count' => (int) $hour->count,
                'percentage' => $totalAppointments > 0 ? round(($hour->count / $totalAppointments) * 100, 2) : 0,
            ];
        }));
    }

    /**
     * Get busiest weekdays for appointments
     */
    public function peakDays(Request $request)
    {
        [$fromDate, $toDate] = $this->dateRange($request, Carbon::now()->subMonth());

        $peakDays = Appointment::select(
            DB::raw('DAYOFWEEK(appointment_date) as day_number'),
            DB::raw('COUNT(*) as count')
        )
            ->whereNotNull('appointment_date')
            ->where('status', '!=', 'cancelled')
            ->whereBetween('appointment_date', [$fromDate, $toDate])
            ->groupBy('day_number')
            ->orderBy('day_number')
            ->get();

        $totalAppointments = $peakDays->sum('count');
        $dayNames = [
            1 => 'Domingo',
            2 => 'Lunes',
            3 => 'Martes',
            4 => 'Miercoles',
            5 => 'Jueves',
            6 => 'Viernes',
            7 => 'Sabado',
        ];

        return response()->json($peakDays->map(function ($day) use ($totalAppointments, $dayNames) {
            return [
                'day' => (int) $day->day_number,
                'day_name' => $dayNames[(int) $day->day_number] ?? 'Sin dia',
                'count' => (int) $day->count,
                'percentage' => $totalAppointments > 0 ? round(($day->count / $totalAppointments) * 100, 2) : 0,
            ];
        }));
    }

    /**
     * Get comprehensive financial report
     */
    public function financialReport(Request $request)
    {
        [$fromDate, $toDate] = $this->dateRange($request, Carbon::now()->startOfMonth());

        // Total income
        $totalIncome = Payment::where('status', 'completed')
            ->whereBetween('payment_date', [$fromDate, $toDate])
            ->sum('amount');
        $completedPayments = Payment::where('status', 'completed')
            ->whereBetween('payment_date', [$fromDate, $toDate])
            ->count();
        $newPatients = Patient::whereBetween('created_at', [$fromDate, $toDate])->count();

        // Payments by type
        $paymentsByType = Payment::select(
            'payment_type',
            DB::raw('COUNT(*) as count'),
            DB::raw('SUM(amount) as total')
        )
            ->where('status', 'completed')
            ->whereBetween('payment_date', [$fromDate, $toDate])
            ->groupBy('payment_type')
            ->get();

        // Payments by method
        $paymentsByMethod = Payment::select(
            'payment_method',
            DB::raw('COUNT(*) as count'),
            DB::raw('SUM(amount) as total')
        )
            ->where('status', 'completed')
            ->whereBetween('payment_date', [$fromDate, $toDate])
            ->groupBy('payment_method')
            ->get();

        // Active payment plans
        $activePaymentPlans = PaymentPlan::where('status', 'active')->count();
        $completedPaymentPlans = PaymentPlan::where('status', 'completed')
            ->whereBetween('end_date', [$fromDate, $toDate])
            ->count();

        // Accounts receivable
        $accountsReceivable = PaymentPlan::where('status', 'active')->sum('remaining_amount');

        return response()->json([
            'period' => [
                'from' => $fromDate,
                'to' => $toDate,
            ],
            'income' => [
                'total' => round($totalIncome, 2),
                'payment_count' => $completedPayments,
                'average_ticket' => $completedPayments > 0 ? round($totalIncome / $completedPayments, 2) : 0,
                'by_type' => $paymentsByType,
                'by_method' => $paymentsByMethod,
            ],
            'patients' => [
                'new' => $newPatients,
            ],
            'payment_plans' => [
                'active' => $activePaymentPlans,
                'completed_in_period' => $completedPaymentPlans,
            ],
            'accounts_receivable' => round($accountsReceivable, 2),
        ]);
    }

    private function dateRange(Request $request, Carbon $defaultFrom): array
    {
        $fromDate = $request->filled('from_date')
            ? Carbon::parse($request->from_date)->startOfDay()
            : $defaultFrom->copy()->startOfDay();
        $toDate = $request->filled('to_date')
            ? Carbon::parse($request->to_date)->endOfDay()
            : Carbon::now()->endOfDay();

        return [$fromDate, $toDate];
    }
}
