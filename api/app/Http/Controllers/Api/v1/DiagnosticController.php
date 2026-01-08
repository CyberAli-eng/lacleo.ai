<?php

namespace App\Http\Controllers\Api\v1;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class DiagnosticController
{
    public function index(Request $request)
    {
        $data = [
            'database' => [
                'connection' => false,
                'host' => config('database.connections.mysql.host'),
                'database' => config('database.connections.mysql.database'),
                'tables' => [],
                'counts' => [],
            ],
            'session' => [
                'driver' => config('session.driver'),
                'current_session_id' => session()->getId(),
                'session_data' => session()->all(),
                'auth_check' => Auth::check(),
                'auth_user_id' => Auth::id(),
            ],
            'environment' => [
                'app_env' => config('app.env'),
                'app_debug' => config('app.debug'),
                'app_key_set' => !empty(config('app.key')),
                'session_driver' => config('session.driver'),
                'session_domain' => config('session.domain'),
                'session_secure' => config('session.secure'),
                'session_same_site' => config('session.same_site'),
            ],
        ];

        try {
            DB::connection()->getPdo();
            $data['database']['connection'] = true;

            $tables = ['users', 'sessions', 'workspaces', 'credit_transactions', 'personal_access_tokens'];
            foreach ($tables as $table) {
                $data['database']['tables'][$table] = DB::getSchemaBuilder()->hasTable($table);
                if ($data['database']['tables'][$table]) {
                    $data['database']['counts'][$table] = DB::table($table)->count();
                }
            }
        } catch (\Exception $e) {
            $data['database']['error'] = $e->getMessage();
        }

        return response()->json($data);
    }
}
