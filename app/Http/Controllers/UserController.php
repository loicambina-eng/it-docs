<?php
// Role du fichier: Controleur des comptes, profils, presences et options de documents.
namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Notification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    public function bootstrap(Request $request)
    {
        $user=$request->user();
        $documents=app(DocumentController::class)->index($request)->getData(true)['documents'];
        $tiers=\App\Models\Tiers::orderBy('raison_sociale')->get()->map(fn($t)=>['id'=>$t->id,'name'=>$t->raison_sociale ?: $t->nom,'first'=>$t->prenom,'type'=>$t->type_tiers,'function'=>$t->fonction,'address'=>$t->adresse,'phone'=>$t->telephone,'email'=>$t->email,'active'=>$t->statut==='actif']);
        $notifications=Notification::where('utilisateur_id',$user->id)->latest()->limit(40)->get()->map(function($n){$message=$n->message;$documentId=null;if(preg_match('/^\[document:(\d+)\]\s*/u',$message,$match)){$documentId=(int)$match[1];$message=substr($message,strlen($match[0]));}return ['id'=>$n->id,'type'=>$n->type,'title'=>$n->type,'message'=>$message,'documentId'=>$documentId,'read'=>(bool)$n->lu,'time'=>$n->created_at?->diffForHumans()];});
        $typeCategoryMap=config('document_categories.types',[]);
        if(\Illuminate\Support\Facades\Schema::hasTable('type_document_categories')){
            $typeCategoryMap=array_merge($typeCategoryMap,\Illuminate\Support\Facades\DB::table('type_document_categories')->join('types_documents','types_documents.id','=','type_document_categories.type_document_id')->join('document_categories','document_categories.id','=','type_document_categories.document_category_id')->pluck('document_categories.name','types_documents.libelle')->all());
        }
        foreach(\App\Models\TypeDocument::orderBy('libelle')->pluck('libelle') as $typeLabel){
            if(isset($typeCategoryMap[$typeLabel]))continue;
            $category=\App\Models\DocumentCategory::whereRaw('LOWER(TRIM(name)) = ?', [mb_strtolower(trim($typeLabel))])->value('name');
            if(!$category)$category=\App\Models\Document::whereNotNull('categorie')->whereRaw('LOWER(TRIM(type_libelle)) = ?', [mb_strtolower(trim($typeLabel))])->orderByDesc('date_archivage')->value('categorie');
            if($category)$typeCategoryMap[$typeLabel]=$category;
        }
        $payload=['user'=>AuthController::userPayload($user),'documents'=>$documents,'tiers'=>$tiers,'documentTypes'=>\App\Models\TypeDocument::orderBy('libelle')->pluck('libelle'),'documentCategories'=>\App\Models\DocumentCategory::orderBy('name')->pluck('name')->concat(\App\Models\Document::whereNotNull('categorie')->distinct()->pluck('categorie'))->unique()->values(),'documentTypeCategories'=>$typeCategoryMap,'users'=>[],'recipients'=>User::where('role','ceo')->get(['id','prenom','nom','role'])->map(fn($u)=>['id'=>$u->id,'name'=>trim($u->prenom.' '.$u->nom),'role'=>$u->role]),'unreadNotificationCount'=>Notification::where('utilisateur_id',$user->id)->where('lu',false)->count(),'notifications'=>$notifications];
        if($user->role==='admin'){
            $payload['users']=User::orderBy('id')->get()->map(fn($u)=>AuthController::userPayload($u));
        }
        return response()->json($payload);
    }

    public function statuses(Request $request)
    {
        abort_unless($request->user()->role === 'admin', 403);
        $stale=User::where('en_ligne',true)->where('role','!=','admin')->where('derniere_activite','<',now()->subMinute())->get();
        foreach($stale as $user){$user->forceFill(['en_ligne'=>false,'statut'=>'inactif'])->save();Notification::create(['utilisateur_id'=>$request->user()->id,'type'=>'Déconnexion utilisateur','message'=>$user->prenom.' '.$user->nom.' est passé hors ligne après une interruption de session.']);}
        return response()->json(['users' => User::orderBy('id')->get()->map(fn ($user) => AuthController::userPayload($user))]);
    }

    public function heartbeat(Request $request)
    {
        $user=$request->user();$wasOffline=!$user->en_ligne;
        $user->forceFill(['derniere_activite'=>now(),'en_ligne'=>true,'statut'=>'actif'])->save();
        if($wasOffline&&$user->role!=='admin')foreach(User::where('role','admin')->get() as $admin)Notification::create(['utilisateur_id'=>$admin->id,'type'=>'Présence utilisateur','message'=>$user->prenom.' '.$user->nom.' est maintenant en ligne.']);
        return response()->json(['ok'=>true]);
    }

    public function presence(Request $request)
    {
        $data=$request->validate(['online'=>['required','boolean']]);
        $user=$request->user();$changed=(bool)$user->en_ligne !== (bool)$data['online'];
        $user->forceFill(['en_ligne'=>$data['online'],'statut'=>$data['online']?'actif':'inactif','derniere_activite'=>now()])->save();
        if($changed&&$user->role!=='admin')foreach(User::where('role','admin')->get() as $admin)Notification::create(['utilisateur_id'=>$admin->id,'type'=>'Présence utilisateur','message'=>$user->prenom.' '.$user->nom.($data['online']?' est maintenant en ligne.':' est maintenant hors ligne.')]);
        return response()->json(['user'=>AuthController::userPayload($user->fresh())]);
    }

    public function profile(Request $request)
    {
        $data = $request->validate([
            'nom'=>['required','string','max:100'],
            'prenom'=>['required','string','max:100'],
            'telephone'=>['nullable','string','max:30'],
            'fonction'=>['nullable','string','max:100'],
            'adresse'=>['nullable','string','max:255'],
            'nationalite'=>['nullable','string','max:100'],
            'photo'=>['nullable','image','max:5120'],
            'remove_photo'=>['nullable','boolean'],
        ], [
            'photo.uploaded'=>'La photo n’a pas pu être reçue. Vérifiez les limites PHP et réessayez.',
            'photo.image'=>'Le fichier doit être une image JPG, PNG ou WEBP.',
            'photo.max'=>'La photo ne peut pas dépasser 5 Mo.',
        ]);

        $actor = $request->user();
        $oldPhoto = $actor->photo;
        $activity = [];
        if ($actor->nom !== $data['nom'] || $actor->prenom !== $data['prenom']) $activity[] = 'a modifié son nom';
        $photoChanged = $request->hasFile('photo') || $request->boolean('remove_photo');
        if ($photoChanged) $activity[] = 'a modifié sa photo de profil';
        if (!$activity && collect(['telephone','fonction','adresse','nationalite'])->contains(fn($field)=>$actor->{$field} !== ($data[$field] ?? null))) $activity[] = 'a modifié son profil';

        unset($data['remove_photo']);
        if ($request->boolean('remove_photo')) $data['photo'] = null;
        if ($request->hasFile('photo')) {
            $newPhoto = $request->file('photo')->store('profiles', 'public');
            if (!$newPhoto) return response()->json(['message'=>'La photo n’a pas pu être enregistrée. Vérifiez les droits du dossier de stockage.'], 500);
            $data['photo'] = $newPhoto;
        }

        $actor->fill($data);
        $actor->save();

        // Retain the old image until the new profile value has been saved.
        if ($photoChanged && $oldPhoto && $oldPhoto !== $actor->photo) {
            \Illuminate\Support\Facades\Storage::disk('public')->delete($oldPhoto);
        }

        if ($activity) foreach (User::where('role','admin')->where('id','<>',$actor->id)->get() as $admin) {
            Notification::create(['utilisateur_id'=>$admin->id,'type'=>'Modification du profil','message'=>$actor->prenom.' '.$actor->nom.' '.$activity[0].'.']);
        }
        return response()->json(['user'=>AuthController::userPayload($actor->fresh())]);
    }

    public function addDocumentOption(Request $request, string $kind)
    {
        abort_unless($kind === 'type', 404);
        $data=$request->validate(['name'=>['required','string','max:100']]);
        $item=\App\Models\TypeDocument::withTrashed()->firstOrNew(['libelle'=>trim($data['name'])]);
        $wasExisting=$item->exists;
        if($item->trashed()) $item->restore();
        elseif(!$item->exists) $item->save();
        if($request->user()->role!=='admin')foreach(User::where('role','admin')->get() as $admin)Notification::create(['utilisateur_id'=>$admin->id,'type'=>'Nouveau type de document','message'=>$request->user()->prenom.' '.$request->user()->nom.' a ajouté le type « '.$item->libelle.' ».']);
        return response()->json(['name'=>$item->libelle,'message'=>$wasExisting?'Type de document déjà enregistré.':'Type de document ajouté.'],$wasExisting?200:201);
    }

    public function deleteDocumentOption(Request $request, string $kind, string $name)
    {
        abort_unless($request->user()->role === 'admin', 403);
        abort_unless($kind === 'type', 404);
        $type=\App\Models\TypeDocument::where('libelle',urldecode($name))->firstOrFail();
        abort_if(\App\Models\Document::withTrashed()->where('type_document_id',$type->id)->exists(),409,'Ce type est déjà utilisé par des documents et ne peut pas être supprimé.');
        $type->delete();
        return response()->json(['message'=>'Type de document supprimé.','name'=>$type->libelle]);
    }

    public function changePassword(Request $request)
    {
        $data=$request->validate(['current_password'=>['required','current_password'],'password'=>['required','confirmed','min:8']]);
        $request->user()->update(['password'=>Hash::make($data['password'])]);
        if($request->user()->role!=='admin')foreach(User::where('role','admin')->get() as $admin)Notification::create(['utilisateur_id'=>$admin->id,'type'=>'Mot de passe modifié','message'=>$request->user()->prenom.' '.$request->user()->nom.' a modifié son mot de passe.']);
        return response()->json(['message'=>'Mot de passe modifié avec succès.']);
    }

    public function store(Request $request)
    {
        abort_unless($request->user()->role==='admin',403);
        $data=$request->validate([
            'nom'=>['required','string','max:100'],
            'prenom'=>['required','string','max:100'],
            'email'=>['required','email','max:150'],
            'telephone'=>['nullable','string','max:30'],
            'adresse'=>['nullable','string','max:255'],
            'role'=>['required',Rule::in(['assistante','ceo'])],
            'password'=>['required','string','min:8'],
        ], [
            'email.required'=>'Saisissez l’adresse e-mail du nouvel utilisateur.',
            'email.email'=>'Saisissez une adresse e-mail valide.',
        ]);
        $existing=User::withTrashed()->whereRaw('LOWER(email) = ?', [mb_strtolower(trim($data['email']))])->first();
        if($existing && !$existing->trashed()) return response()->json(['message'=>'Cette adresse e-mail est déjà utilisée par un compte actif. Choisissez une autre adresse.','errors'=>['email'=>['Cette adresse e-mail est déjà utilisée.']]],422);

        // L'enregistrement et sa notification sont atomiques pour éviter un compte créé sans retour de succès.
        [$user,$notification,$restored]=DB::transaction(function() use ($data,$existing,$request) {
            if($existing){
                $existing->restore();
                $existing->fill($data+['statut'=>'inactif','en_ligne'=>false])->forceFill(['deleted_at'=>null])->save();
                $user=$existing->fresh();
            }else{
                $user=User::create($data+['statut'=>'inactif','en_ligne'=>false]);
            }
            $roleLabel=['assistante'=>'Assistante de direction','ceo'=>'CEO'][$user->role] ?? $user->role;
            $type=$existing?'Compte utilisateur réactivé':'Nouvel utilisateur créé';
            $message=($existing?'Compte utilisateur réactivé : ':'Nouvel utilisateur créé : ').$user->prenom.' '.$user->nom.' ('.$roleLabel.').';
            $ownNotification=null;
            foreach(User::where('role','admin')->get() as $admin){
                $created=Notification::create(['utilisateur_id'=>$admin->id,'type'=>$type,'message'=>$message]);
                if($admin->id===$request->user()->id)$ownNotification=$created;
            }
            return [$user,$ownNotification,(bool)$existing];
        });
        return response()->json(['user'=>AuthController::userPayload($user),'notification'=>$this->notificationPayload($notification),'message'=>$restored?'Compte utilisateur réactivé.':'Compte utilisateur créé.'], $restored?200:201);
    }

    private function notificationPayload(?Notification $notification): ?array
    {
        if(!$notification)return null;
        return ['id'=>$notification->id,'type'=>$notification->type,'title'=>$notification->type,'message'=>$notification->message,'documentId'=>null,'read'=>(bool)$notification->lu,'time'=>$notification->created_at?->diffForHumans()];
    }

    public function update(Request $request, User $user)
    {
        abort_unless($request->user()->role==='admin' && $user->role!=='admin',403);
        $data=$request->validate(['nom'=>['required','string','max:100'],'prenom'=>['required','string','max:100'],'email'=>['required','email','max:150',Rule::unique('users')->ignore($user->id)],'role'=>['required',Rule::in(['assistante','ceo'])]]);
        $user->update($data);
        return response()->json(['user'=>AuthController::userPayload($user->fresh())]);
    }

    public function destroy(Request $request, User $user)
    {
        abort_unless($request->user()->role==='admin' && $user->role!=='admin',403);
        [$notification]=DB::transaction(function() use ($request,$user) {
            $name=trim($user->prenom.' '.$user->nom);
            $roleLabel=['assistante'=>'Assistante de direction','ceo'=>'CEO'][$user->role] ?? $user->role;
            $user->forceFill(['en_ligne'=>false,'statut'=>'inactif'])->save();
            $user->delete();
            $message='Utilisateur supprimé : '.$name.' ('.$roleLabel.').';
            $ownNotification=null;
            foreach(User::where('role','admin')->get() as $admin){
                $created=Notification::create(['utilisateur_id'=>$admin->id,'type'=>'Utilisateur supprimé','message'=>$message]);
                if($admin->id===$request->user()->id)$ownNotification=$created;
            }
            return [$ownNotification];
        });
        return response()->json(['message'=>'Compte supprimé.','notification'=>$this->notificationPayload($notification)]);
    }
}
