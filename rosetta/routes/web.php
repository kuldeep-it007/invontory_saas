<?php

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/
Route::get('/get-ss-stock-all', function () {
  ini_set('max_execution_time', 0);
  $email = request()->query('send_mail_to_email');
  if (!$email) {
    return response()->json(['error' => 'You are not authorized to perform this action'], 400);
  }

  // Optional reporting date range. When start_date / end_date are omitted the
  // command falls back to its existing default (the previous month).
  $params = ['email' => $email];
  if ($startDate = request()->query('start_date')) {
    $params['start_date'] = $startDate;
  }
  if ($endDate = request()->query('end_date')) {
    $params['end_date'] = $endDate;
  }

  Artisan::call('superstokist:sales-report', $params);

  // The command outputs the generated report file path on its last line.
  $output = trim(Artisan::output());
  $lines = array_filter(array_map('trim', explode("\n", $output)));
  $file = end($lines);

  if (!$file || !file_exists($file)) {
    return response()->json(['error' => 'No report was generated for the selected period'], 404);
  }

  return response()->download($file, basename($file))->deleteFileAfterSend(false);
});
Route::get('/{vue_capture?}', function () {
  return view('welcome');
})->where('vue_capture', '[\/\w\.-]*');
