<?php

namespace App\Http\Controllers;

use App\Models\Postcode;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PostcodeController extends Controller
{
    public function lookup(Request $request): JsonResponse
    {
        $data = $request->validate([
            'postcode' => ['required', 'string', 'size:5'],
        ]);

        $result = Postcode::find($data['postcode']);

        if (! $result) {
            return response()->json([
                'found' => false,
                'message' => 'Poskod tidak dijumpai.',
            ]);
        }

        return response()->json([
            'found' => true,
            'city' => $result->city,
            'state' => $result->state,
            'country' => $result->country,
        ]);
    }
}
