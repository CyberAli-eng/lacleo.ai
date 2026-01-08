<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use App\Models\User;
use App\Models\Workspace;

class DiagnosticController extends Controller
{
    public function diag()
    {
        $results = [
            'database' => [
                'connection' => false,
                'tables' => [],
                'counts' => [],
            ],
            'environment' => [
                'app_env' => config('app.env'),
                'app_debug' => config('app.debug'),
                'session_driver' => config('session.driver'),
            ],
        ];

        try {
            DB::connection()->getPdo();
            $results['database']['connection'] = true;

            $tables = ['users', 'sessions', 'workspaces', 'credit_transactions', 'personal_access_tokens'];
            foreach ($tables as $table) {
                $results['database']['tables'][$table] = Schema::hasTable($table);
                if ($results['database']['tables'][$table]) {
                    try {
                        $results['database']['counts'][$table] = DB::table($table)->count();
                    } catch (\Exception $e) {
                        $results['database']['counts'][$table] = 'Error: ' . $e->getMessage();
                    }
                }
            }
        } catch (\Exception $e) {
            $results['database']['error'] = $e->getMessage();
        }

        return response()->json($results);
    }
}
