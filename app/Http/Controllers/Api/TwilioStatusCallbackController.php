<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\TwilioCampaignService;
use App\Services\TwilioRequestValidator;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class TwilioStatusCallbackController extends Controller
{
    public function __invoke(
        Request $request,
        TwilioRequestValidator $validator,
        TwilioCampaignService $campaigns,
    ): Response {
        abort_unless($validator->isValid($request), 403);

        $validated = $request->validate([
            'MessageSid' => ['required', 'string', 'regex:/^SM[0-9a-fA-F]{32}$/'],
            'MessageStatus' => ['required', 'string', 'max:32'],
            'AccountSid' => ['nullable', 'string', 'max:64'],
            'From' => ['nullable', 'string', 'max:32'],
            'To' => ['nullable', 'string', 'max:32'],
            'ErrorCode' => ['nullable', 'string', 'max:16'],
            'ErrorMessage' => ['nullable', 'string', 'max:1000'],
        ]);

        $campaigns->recordStatusCallback([
            ...$request->all(),
            ...$validated,
        ]);

        return response()->noContent();
    }
}
