<?php

use App\Models\Caso;
use App\Models\User;
use Illuminate\Support\Facades\DB;

$anggie = User::query()->where('name', 'ANGGIE COUTIN')->firstOrFail();
$fredy = User::query()->where('name', 'FREDY ANDRES BONFANTE GONZALEZ')->firstOrFail();

$caseIds = Caso::withTrashed()
    ->where(function ($query) use ($anggie) {
        $query->where('user_id', $anggie->id)
            ->orWhereHas('users', fn ($users) => $users->where('users.id', $anggie->id));
    })
    ->pluck('id');

DB::transaction(function () use ($caseIds, $fredy) {
    Caso::withTrashed()
        ->whereKey($caseIds)
        ->update(['user_id' => $fredy->id]);

    Caso::withTrashed()
        ->whereKey($caseIds)
        ->each(fn (Caso $case) => $case->users()->sync([$fredy->id]));
});

return [
    'from' => ['id' => $anggie->id, 'name' => $anggie->name],
    'to' => ['id' => $fredy->id, 'name' => $fredy->name],
    'transferred' => $caseIds->count(),
    'remaining_with_anggie' => Caso::withTrashed()
        ->where(function ($query) use ($anggie) {
            $query->where('user_id', $anggie->id)
                ->orWhereHas('users', fn ($users) => $users->where('users.id', $anggie->id));
        })
        ->count(),
    'not_exclusive_to_fredy' => Caso::withTrashed()
        ->whereKey($caseIds)
        ->get()
        ->filter(function (Caso $case) use ($fredy) {
            $assignedIds = $case->users()->pluck('users.id')->map(fn ($id) => (int) $id)->sort()->values()->all();

            return (int) $case->user_id !== (int) $fredy->id || $assignedIds !== [(int) $fredy->id];
        })
        ->count(),
];
