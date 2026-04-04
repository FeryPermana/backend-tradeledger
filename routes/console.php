<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('portfolio:sync-prices')->everyMinute();
