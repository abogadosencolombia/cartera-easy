<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\TwilioInboundOptOutService;
use App\Services\TwilioInboundSignatureValidator;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Validator;
use InvalidArgumentException;

class TwilioInboundController extends Controller
{
    public function __invoke(
        Request $request,
        TwilioInboundSignatureValidator $signatureValidator,
        TwilioInboundOptOutService $optOutService,
    ): Response {
        abort_unless($signatureValidator->isValid($request), 403);

        $validation = Validator::make($request->all(), [
            'From' => ['required', 'string', 'max:64'],
            'Body' => ['nullable', 'string', 'max:1600'],
            'OptOutType' => ['nullable', 'string', 'max:32'],
            'MessageSid' => ['nullable', 'string', 'regex:/^(SM|MM)[0-9a-fA-F]{32}$/'],
        ]);

        abort_if($validation->fails(), 422);

        $validated = $validation->validated();

        try {
            $optOutService->handle(
                $validated['From'],
                $validated['Body'] ?? null,
                $validated['OptOutType'] ?? null,
            );
        } catch (InvalidArgumentException $exception) {
            abort(422, $exception->getMessage());
        }

        return response(
            '<?xml version="1.0" encoding="UTF-8"?><Response></Response>',
            200,
            ['Content-Type' => 'text/xml; charset=UTF-8'],
        );
    }
}
