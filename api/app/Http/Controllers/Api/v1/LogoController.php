<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use App\Services\LogoService;
use Illuminate\Http\Request;

class LogoController extends Controller
{
    public function __construct(private LogoService $service) {}

    public function getLogo(Request $request)
    {
        $domain = (string) $request->query('domain', '');
        $domain = trim($domain);
        if ($domain === '') {
            return response()->json(['domain' => null, 'logo_url' => null], 200);
        }
        $logo = $this->service->getLogo($domain);

        return response()->json(['domain' => $domain, 'logo_url' => $logo], 200);
    }
}
