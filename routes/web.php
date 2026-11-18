<?php
// Role du fichier: Declaration des routes web et API.
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DocumentController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\TierController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Cache\RateLimiting\Limit;

RateLimiter::for('prototype-login', fn (\Illuminate\Http\Request $request) =>
    Limit::perMinute(10)->by($request->ip().'|'.strtolower((string) $request->input('email')))
);

// Les adresses d’accueil et de connexion affichent la même vue, qui contient les deux écrans.
// Page publique racine : affiche la vue qui contient l’accueil et le formulaire de connexion.
Route::get('/', fn () => view('index'));
Route::get('/login', fn () => view('index'))->name('login');
Route::get('/forgot-password', fn () => view('auth.forgot-password'))->name('password.request');
// Affiche le formulaire de réinitialisation dans une page autonome, sans fermer la session du portail.
Route::get('/reset-password/{token}', function (\Illuminate\Http\Request $request, string $token) {
    return view('auth.reset-password', ['token'=>$token,'email'=>$request->query('email')]);
})->name('password.reset');

// Transmet les identifiants du formulaire au contrôleur, avec une limite de tentatives.
// Reçoit les identifiants du formulaire ; le limiteur réduit les tentatives répétées.
Route::post('/login', [AuthController::class,'login'])->middleware('throttle:prototype-login');
Route::post('/forgot-password', [AuthController::class,'forgotPassword'])->middleware('throttle:6,1');
Route::post('/reset-password', [AuthController::class,'resetPassword'])->middleware('throttle:6,1');

Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthController::class,'logout']);
    Route::get('/api/bootstrap', [UserController::class,'bootstrap']);
    Route::get('/api/user-statuses', [UserController::class,'statuses']);
    Route::post('/api/heartbeat', [UserController::class,'heartbeat']);
    Route::patch('/api/presence', [UserController::class,'presence']);
    Route::patch('/api/profile', [UserController::class,'profile']);
    Route::post('/api/profile', [UserController::class,'profile']);
    Route::get('/profile-photo/{user}', function (\App\Models\User $user) { abort_unless($user->photo && \Illuminate\Support\Facades\Storage::disk('public')->exists($user->photo), 404); return response()->file(\Illuminate\Support\Facades\Storage::disk('public')->path($user->photo), ['Content-Disposition'=>'inline','Cache-Control'=>'no-cache, no-store, must-revalidate','Pragma'=>'no-cache','Expires'=>'0']); })->name('profile.photo');
    Route::post('/api/document-options/{kind}', [UserController::class,'addDocumentOption']);
    Route::delete('/api/document-options/{kind}/{name}', [UserController::class,'deleteDocumentOption']);
    Route::put('/api/password', [UserController::class,'changePassword']);
    Route::get('/api/documents', [DocumentController::class,'index']);
    Route::post('/api/documents', [DocumentController::class,'store']);
    Route::patch('/api/documents/{document}/identify', [DocumentController::class,'identify']);
    Route::get('/api/documents/export-month', [DocumentController::class,'exportMonth']);
    Route::delete('/api/documents/{document}', [DocumentController::class,'destroy']);
    Route::post('/api/documents/{document}/consult', [DocumentController::class,'consult']);
    Route::get('/documents/{document}/file', [DocumentController::class,'file'])->name('documents.file');
    Route::get('/api/tiers', [TierController::class,'index']);
    Route::post('/api/tiers', [TierController::class,'store']);
    Route::put('/api/tiers/{tier}', [TierController::class,'update']);
    Route::get('/api/users', function (\Illuminate\Http\Request $request) { abort_unless($request->user()->role==='admin',403); return response()->json(['users'=>\App\Models\User::orderBy('id')->get()->map(fn($u)=>AuthController::userPayload($u))]); });
    Route::post('/api/users', [UserController::class,'store']);
    Route::put('/api/users/{user}', [UserController::class,'update']);
    Route::delete('/api/users/{user}', [UserController::class,'destroy']);
    Route::get('/api/notifications', [NotificationController::class,'index']);
    Route::post('/api/notifications/read', [NotificationController::class,'read']);
    Route::post('/api/notifications/{notification}/read', [NotificationController::class,'markRead']);
});
