<?php
use Illuminate\Support\Facades\Schedule;
Schedule::command('abogados-bot:run')->everyMinute()->withoutOverlapping(3)->name('abogados_bot_isolated');
