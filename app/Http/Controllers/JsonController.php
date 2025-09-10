<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class JsonController extends Controller
{
    public function fetchJson(Request $request)
    {
        $input = $request->input('input');

        // If input is a URL
        if (filter_var($input, FILTER_VALIDATE_URL)) {
            try {
                $response = Http::timeout(10)->get($input);
                if ($response->successful()) {
                    return response()->json([
                        'success' => true,
                        'data' => $response->json(),
                    ]);
                }
                return response()->json(['success' => false, 'error' => 'Failed to fetch URL'], 400);
            } catch (\Exception $e) {
                return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
            }
        }

        // If input is raw JSON
        $decoded = json_decode($input, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            return response()->json([
                'success' => true,
                'data' => $decoded,
            ]);
        }

        return response()->json(['success' => false, 'error' => 'Invalid JSON string'], 400);
    }
}
