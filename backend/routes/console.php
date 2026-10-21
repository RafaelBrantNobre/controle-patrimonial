<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Apaga do banco os tokens vencidos há mais de um dia (tarefa 2.4).
Schedule::command('sanctum:prune-expired --hours=24')->daily();
