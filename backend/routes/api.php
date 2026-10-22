use App\Http\Controllers\KioskController;

Route::get('/kiosk/katalog', [KioskController::class, 'index']);
Route::post('/kiosk/pinjam', [KioskController::class, 'store']);