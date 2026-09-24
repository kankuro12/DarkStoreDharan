<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\City;
use App\Models\Order;
use App\Models\Warehouse;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class HealthCheckController extends Controller
{
    /**
     * System Health & Observability Endpoint (§44, §46).
     */
    public function index(): JsonResponse
    {
        $checks = [
            'database' => false,
            'cache' => false,
            'cities_count' => 0,
            'warehouses_count' => 0,
            'pending_orders' => 0,
        ];

        try {
            DB::connection()->getPdo();
            $checks['database'] = true;
            $checks['cities_count'] = City::count();
            $checks['warehouses_count'] = Warehouse::count();
            $checks['pending_orders'] = Order::where('order_status', 'pending')->count();
        } catch (Exception $e) {
            $checks['database_error'] = $e->getMessage();
        }

        try {
            Cache::put('health_check', true, 10);
            $checks['cache'] = Cache::get('health_check') === true;
        } catch (Exception $e) {
            $checks['cache_error'] = $e->getMessage();
        }

        $isHealthy = $checks['database'] && $checks['cache'];

        return response()->json([
            'status' => $isHealthy ? 'ok' : 'degraded',
            'timestamp' => now()->toIso8601String(),
            'app_env' => app()->environment(),
            'php_version' => PHP_VERSION,
            'checks' => $checks,
        ], $isHealthy ? 200 : 503);
    }
}
