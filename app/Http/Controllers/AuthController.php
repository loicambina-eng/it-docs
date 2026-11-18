<?php
// Role du fichier: Controleur des connexions, deconnexions et reinitialisations de mot de passe.
namespace App\Http\Controllers;

use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    // Vérifie les identifiants avec le système d’authentification Laravel,
    // ouvre la session et renvoie les informations nécessaires à l’interface.
    public function login(Request $request)
    {
        // Valide la présence et le format des identifiants reçus du formulaire.
        $credentials = $request->validate(['email' => ['required','email'], 'password' => ['required','string']]);
        // Laravel compare les identifiants avec le compte enregistré ; en cas d’échec, renvoie un message en français.
        if (!Auth::attempt($credentials, $request->boolean('remember'))) {
            return response()->json(['message' => 'Adresse e-mail ou mot de passe incorrect.'], 422);
        }
        // Renouvelle l’identifiant de session après la connexion pour protéger la session.
        $request->session()->regenerate();
        $user = $request->user();
        // Met à jour l’état de présence et l’heure d’activité du compte.
        $user->forceFill(['derniere_activite' => now(),'en_ligne'=>true,'statut'=>'actif'])->save();
        // Informe les administrateurs lorsqu’un compte autre que le leur se connecte.
        if($user->role!=='admin') foreach(User::where('role','admin')->get() as $admin) Notification::create(['utilisateur_id'=>$admin->id,'type'=>'Connexion utilisateur','message'=>$user->prenom.' '.$user->nom.' vient de se connecter.']);
        // Renvoie à l’interface les données utilisateur nécessaires, dont le rôle et le jeton CSRF.
        return response()->json(['user' => $this->userPayload($user), 'csrf' => csrf_token()]);
    }

    public function logout(Request $request)
    {
        $user=$request->user();
        if($user){$user->forceFill(['derniere_activite'=>now(),'en_ligne'=>false,'statut'=>'inactif'])->save();if($user->role!=='admin')foreach(User::where('role','admin')->get() as $admin)Notification::create(['utilisateur_id'=>$admin->id,'type'=>'Déconnexion utilisateur','message'=>$user->prenom.' '.$user->nom.' vient de se déconnecter.']);}
        Auth::logout(); $request->session()->invalidate(); $request->session()->regenerateToken();
        return response()->json(['message' => 'Vous êtes déconnecté(e).', 'csrf' => csrf_token()]);
    }

    public function forgotPassword(Request $request)
    {
        $request->validate(['email' => ['required','email']]);
        $account = User::where('email', trim((string) $request->input('email')))->first();
        if (!$account) return response()->json(['message' => 'Aucun compte n’est associé à cette adresse e-mail.'], 422);
        $request->merge(['email' => $account->email]);
        $smtpReady = config('mail.default') === 'smtp'
            && config('mail.mailers.smtp.host')
            && config('mail.mailers.smtp.username')
            && config('mail.mailers.smtp.password')
            && config('mail.from.address')
            && config('mail.from.address') !== 'hello@example.com';
        if (!$smtpReady) return response()->json(['message' => 'L’envoi du mail est temporairement indisponible. Réessayez plus tard ou contactez l’administrateur.'], 503);
        try {
            $status = Password::sendResetLink($request->only('email'));
        } catch (\Throwable $error) {
            report($error);
            return response()->json(['message' => 'L’e-mail n’a pas pu être envoyé. Vérifiez les paramètres de messagerie SMTP.'], 503);
        }
        if ($status !== Password::RESET_LINK_SENT) return response()->json(['message' => __($status)], 422);
        return response()->json(['message' => 'Un e-mail de réinitialisation vient d’être envoyé à cette adresse. Vérifiez aussi votre dossier de courriers indésirables.']);
    }

    public function resetPassword(Request $request)
    {
        $request->validate(
            ['token' => ['required'], 'email' => ['required','email'], 'password' => ['required','confirmed','min:8']],
            [
                'password.confirmed' => 'La confirmation ne correspond pas au nouveau mot de passe.',
                'password.min' => 'Le nouveau mot de passe doit contenir au moins :min caractères.',
                'password.required' => 'Saisissez votre nouveau mot de passe.',
            ]
        );
        $status = Password::reset($request->only('email','password','password_confirmation','token'), function ($user, $password) {
            // setRememberToken ne renvoie pas le modèle : chaque opération doit rester séparée.
            $user->forceFill(['password' => Hash::make($password)]);
            $user->setRememberToken(Str::random(60));
            $user->save();
            event(new PasswordReset($user));
        });
        if ($status !== Password::PASSWORD_RESET) {
            $message = match ($status) {
                Password::INVALID_USER => 'Aucun compte n’est associé à cette adresse e-mail.',
                Password::INVALID_TOKEN => 'Ce lien de réinitialisation est invalide ou expiré. Demandez-en un nouveau.',
                Password::RESET_THROTTLED => 'Veuillez patienter avant de demander une nouvelle réinitialisation.',
                default => 'La réinitialisation n’a pas abouti. Vérifiez le lien et réessayez.',
            };
            return response()->json(['message' => $message], 422);
        }
        $resetUser = User::where('email', $request->input('email'))->first();
        if ($resetUser) {
            $message = 'Le mot de passe de '.$resetUser->prenom.' '.$resetUser->nom.' ('.$resetUser->role.') a été réinitialisé.';
            foreach (User::where('role', 'admin')->get() as $admin) {
                Notification::create(['utilisateur_id' => $admin->id, 'type' => 'Réinitialisation du mot de passe', 'message' => $message]);
            }
        }
        return response()->json(['message' => 'Votre mot de passe a été réinitialisé avec succès. Vous pouvez maintenant vous connecter.']);
    }

    public static function userPayload($user): array
    {
        return ['id'=>$user->id,'email'=>$user->email,'name'=>trim($user->prenom.' '.$user->nom),'firstName'=>$user->prenom,'lastName'=>$user->nom,'phone'=>$user->telephone,'function'=>$user->fonction,'address'=>$user->adresse,'nationality'=>$user->nationalite,'role'=>$user->role,'status'=>$user->statut,'presence'=>(bool)$user->en_ligne,'lastSeen'=>$user->derniere_activite?->toIso8601String(),'photo'=>$user->photo ? route('profile.photo', $user).'?v='.rawurlencode(hash('sha256',$user->photo)) : null];
    }
}
