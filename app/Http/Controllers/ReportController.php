<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Models\Order;
use App\Models\Commission;
use Illuminate\Http\Request;
use Carbon\Carbon;

class ReportController extends Controller
{
    public function index(Request $request)
    {
        try {
            $barbershopId = $request->user()->barbershop_id;
            $period = $request->get('period', 'month');

            switch ($period) {
                case 'day':
                    $start = Carbon::today();
                    $end   = Carbon::today()->endOfDay();
                    break;
                case 'week':
                    $start = Carbon::now()->startOfWeek();
                    $end   = Carbon::now()->endOfWeek();
                    break;
                case 'year':
                    $start = Carbon::now()->startOfYear();
                    $end   = Carbon::now()->endOfYear();
                    break;
                default: // month
                    $start = Carbon::now()->startOfMonth();
                    $end   = Carbon::now()->endOfMonth();
            }

            // Agendamentos do período
            $appointments = Appointment::where('barbershop_id', $barbershopId)
                ->whereBetween('appointment_date', [$start, $end])
                ->whereNotIn('status', ['cancelled'])
                ->with(['barber', 'service'])
                ->get();

            $totalRevenue = $appointments->sum('price');
            $avgTicket    = $appointments->count() > 0
                ? $totalRevenue / $appointments->count()
                : 0;

            // Comandas (Orders)
            $totalOrders      = $appointments->count();
            $barbershopProfit = $totalRevenue;
            // Comissões
            $commissionsPaid    = 0;
            $commissionsPending = 0;

            // Faturamento por dia
            $dailyRevenue = $appointments
                ->groupBy(fn($a) => Carbon::parse($a->appointment_date)->format('d/m'))
                ->map(fn($group, $date) => [
                    'date'  => $date,
                    'total' => $group->sum('price'),
                    'count' => $group->count(),
                ])
                ->values();

            // Ranking de barbeiros
            $barberRanking = $appointments
                ->whereNotNull('barber_id')
                ->groupBy('barber_id')
                ->map(function($group) {
                    $barber = $group->first()->barber;
                    return [
                        'id'     => $barber?->id,
                        'name'   => $barber?->name ?? 'Sem nome',
                        'orders' => $group->count(),
                        'total'  => $group->sum('price'),
                    ];
                })
                ->sortByDesc('total')
                ->values();

            // Top serviços
            $topItems = $appointments
                ->whereNotNull('service_id')
                ->groupBy('service_id')
                ->map(function($group) {
                    $service = $group->first()->service;
                    return [
                        'name'     => $service?->name ?? $group->first()->service_name ?? 'Serviço',
                        'type'     => 'service',
                        'quantity' => $group->count(),
                        'total'    => $group->sum('price'),
                    ];
                })
                ->sortByDesc('total')
                ->values();

            return response()->json([
                'start'              => $start->format('d/m/Y'),
                'end'                => $end->format('d/m/Y'),
                'total_revenue'      => $totalRevenue,
                'total_orders'       => $totalOrders ?: $appointments->count(),
                'avg_ticket'         => $avgTicket,
                'barbershop_profit'  => $barbershopProfit,
                'commissions_paid'   => $commissionsPaid,
                'commissions_pending'=> $commissionsPending,
                'daily_revenue'      => $dailyRevenue,
                'barber_ranking'     => $barberRanking,
                'top_items'          => $topItems,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Erro ao gerar relatório',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }
}