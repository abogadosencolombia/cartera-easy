<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class WebPushController extends Controller
{
    public function subscribe(Request $request)
    {
        $request->validate([
            'endpoint'    => ['required','string'],
            'keys.auth'   => ['required','string'],
            'keys.p256dh' => ['required','string'],
        ]);

        // Borra otras suscripciones del mismo usuario con endpoint distinto
        $request->user()
            ->pushSubscriptions()
            ->where('endpoint', '!=', $request->input('endpoint'))
            ->delete();

        $request->user()->updatePushSubscription(
            $request->input('endpoint'),
            $request->input('keys.p256dh'),
            $request->input('keys.auth')
        );

        return response()->noContent();
}

    public function unsubscribe(Request $request)
    {
        $request->validate(['endpoint' => ['required','string']]);
        $request->user()->deletePushSubscription($request->input('endpoint'));
        return response()->noContent();
    }
}
