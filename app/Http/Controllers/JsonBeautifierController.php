<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;

class JsonBeautifierController extends Controller
{
    // Show the UI
    public function show()
    {
        return view('json-beautifier');
    }

    // Handle form POST (web)
    public function beautify(Request $request)
    {
        $request->validate([
            'json' => ['required', 'string', 'max:131072'], // ~128KB limit; adjust as needed
        ]);

        $input = $request->input('json');

        // Attempt decode
        $decoded = json_decode($input, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            $error = json_last_error_msg();
            return back()->withInput()->withErrors(['json' => "Invalid JSON: $error"]);
        }

        $pretty = json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return view('json-beautifier', [
            'input' => $input,
            'result' => $pretty,
        ]);
    }

    // API endpoint: returns formatted JSON or error
    public function apiBeautify(Request $request)
    {
        $payload = $request->getContent();

        if (empty($payload)) {
            return response()->json(['error' => 'Empty request body'], 422);
        }

        // Some clients may send JSON automatically; try to json_decode raw body
        $decoded = json_decode($payload, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            // If body isn't valid JSON, maybe they sent a form field "json"
            $jsonField = $request->input('json');
            if ($jsonField) {
                $decoded = json_decode($jsonField, true);
            }
        }

        if (json_last_error() !== JSON_ERROR_NONE || $decoded === null) {
            return response()->json(['error' => 'Invalid JSON: ' . json_last_error_msg()], 400);
        }

        $pretty = json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return response($pretty, 200, ['Content-Type' => 'application/json']);
    }
}
