<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\FormService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FormController extends Controller
{
    public function __construct(private readonly FormService $forms) {}

    public function show(string $token): JsonResponse
    {
        $form = $this->forms->findPublic($token);

        $payload = $this->forms->publicFormPayload($form);

        if ($form->event_id) {
            $form->loadMissing('event:id,slug');
            $payload['redirect_to'] = route('events.register', ['event' => $form->event->slug, 'form' => $form->id]);
        }

        return ApiResponse::success(['form' => $payload]);
    }

    public function submit(Request $request, string $token): JsonResponse
    {
        $form = $this->forms->findPublic($token);

        $data = $request->validate($this->forms->validationRules($form));

        $response = $this->forms->storeResponse($form, $data, $request->user());

        return ApiResponse::success([
            'success' => true,
            'response_id' => $response->id,
        ]);
    }
}
