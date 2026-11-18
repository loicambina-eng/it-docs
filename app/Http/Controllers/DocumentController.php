<?php
// Role du fichier: Controleur du depot, identification, archivage, consultation et export des documents.
namespace App\Http\Controllers;

use App\Models\Document;
use App\Models\Notification;
use App\Models\Tiers;
use App\Models\TypeDocument;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class DocumentController extends Controller
{
    public function index(Request $request)
    {
        $query = Document::with(['tiers','deposant','destinataire'])->latest('date_depot');
        if ($request->user()->role === 'ceo') $query->where('destinataire_id', $request->user()->id);
        return response()->json(['documents'=>$query->get()->map(fn($d)=>$this->payload($d))]);
    }

    private function categoryFor(?string $type): ?string
    {
        $key = mb_strtolower(trim((string) $type));
        $resolveCategory = function (?string $candidate): ?string {
            $candidate = trim((string) $candidate);
            if ($candidate === '') return null;
            $normalized = mb_strtolower($candidate);
            if (\Illuminate\Support\Facades\Schema::hasTable('document_categories')) {
                $category = \App\Models\DocumentCategory::whereRaw('LOWER(TRIM(name)) = ?', [$normalized])->value('name');
                if ($category) return $category;
            }
            return Document::whereNotNull('categorie')->whereRaw('LOWER(TRIM(categorie)) = ?', [$normalized])->value('categorie');
        };
        $map = collect(config('document_categories.types', []))->mapWithKeys(fn($category, $label) => [mb_strtolower(trim($label)) => $category]);
        if ($category = $resolveCategory($map->get($key))) return $category;
        if (\Illuminate\Support\Facades\Schema::hasTable('type_document_categories')) {
            $mapped = DB::table('type_document_categories')
                ->join('types_documents', 'types_documents.id', '=', 'type_document_categories.type_document_id')
                ->join('document_categories', 'document_categories.id', '=', 'type_document_categories.document_category_id')
                ->whereRaw('LOWER(TRIM(types_documents.libelle)) = ?', [$key])
                ->value('document_categories.name');
            if ($category = $resolveCategory($mapped)) return $category;
        }
        // Réutilise la catégorie déjà validée sur un document antérieur de même type.
        $previousCategory = Document::whereNotNull('categorie')
            ->whereRaw('LOWER(TRIM(type_libelle)) = ?', [$key])
            ->orderByDesc('date_archivage')
            ->value('categorie');
        if ($category = $resolveCategory($previousCategory)) return $category;
        // Un type qui porte le même nom qu'une catégorie existante peut la réutiliser directement.
        return $resolveCategory($type);
    }

    // Ajoute un suffixe au nom complet jusqu'a trouver un nom libre en base.
    // L'identifiant ignore permet de conserver le nom du document qu'on identifie.
    private function uniqueDocumentName(string $baseName, ?int $ignoreId = null): string
    {
        $name = $baseName;
        $suffix = 1;
        while (true) {
            $query = Document::withTrashed()->where('nom', $name);
            if ($ignoreId !== null) $query->where('id', '<>', $ignoreId);
            if (!$query->exists()) return $name;
            $name = $baseName.' '.$suffix++;
        }
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'files' => ['required','array','min:1'], 'files.*' => ['required','file','max:10240','mimes:pdf,doc,docx,xls,xlsx,jpg,jpeg,png','extensions:pdf,doc,docx,xls,xlsx,jpg,jpeg,png'],
            'reference' => ['required','string','max:180'], 'type' => ['nullable','string','max:100'],
            'tier' => ['required','string','max:150'], 'recipient_id' => ['required',Rule::exists('users','id')->where('role','ceo')], 'description' => ['nullable','string','max:5000'],
            'date_mode' => ['required','in:day,year'], 'date_day' => ['required_if:date_mode,day','nullable','date'], 'date_year' => ['required_if:date_mode,year','nullable','integer','between:1900,2100'],
        ], [
            'files.required' => 'Sélectionnez au moins un document à déposer.',
            'files.*.required' => 'Un fichier sélectionné est manquant. Retirez-le puis sélectionnez-le à nouveau.',
            'files.*.uploaded' => 'Le fichier n’a pas pu être reçu. Vérifiez les limites PHP (10 Mo par fichier et la taille totale de l’envoi), puis réessayez.',
            'files.*.max' => 'Chaque fichier ne peut pas dépasser 10 Mo.',
            'files.*.mimes' => 'Format non accepté. Utilisez PDF, DOC, DOCX, XLS, XLSX, JPG ou PNG.',
            'files.*.extensions' => 'Extension non autorisée. Utilisez PDF, DOC, DOCX, XLS, XLSX, JPG ou PNG.',
        ]);
        $selectedType = trim((string) ($data['type'] ?? ''));
        $type = $selectedType !== '' ? TypeDocument::where('libelle', $selectedType)->first() : null;
        if ($selectedType !== '' && !$type) return response()->json(['message'=>'Ce type de document n’existe pas.'], 422);
        if (!$type) $type = TypeDocument::whereRaw('LOWER(libelle) = ?', ['autre'])->first();
        abort_unless($type, 422, 'Le type « Autre » doit exister dans la liste des types pour déposer un document en attente.');
        $knownType = $selectedType !== '' && mb_strtolower($selectedType) !== 'autre';
        $category = $knownType ? $this->categoryFor($selectedType) : null;
        $tier = !empty($data['tier']) ? Tiers::firstOrCreate(['raison_sociale'=>$data['tier']], ['type_tiers'=>'Organisation','statut'=>'actif']) : null;
        $created=[];
        $documentDate = $data['date_mode']==='year' ? \Illuminate\Support\Carbon::create((int)$data['date_year'],1,1) : \Illuminate\Support\Carbon::parse($data['date_day']);
        $tierName=$tier?->raison_sociale ?: $tier?->nom ?: 'Sans tiers';
        // Vérifie tout le lot avant d'écrire le moindre fichier pour éviter les dépôts partiels.
        $typeKey = $knownType ? $selectedType : 'Autre';
        $seenInBatch = [];
        $duplicateNames = [];
        $duplicateLocks = [];
        $hasDuplicateLocks = DB::connection()->getDriverName() === 'mysql';
        // Verrouille les clés de doublon pendant toute la vérification et l’écriture.
        // Cela empêche deux requêtes simultanées d’accepter le même fichier avant que
        // l’une d’elles ait créé son enregistrement.
        try {
            if ($hasDuplicateLocks) {
                $duplicateLockNames = collect($request->file('files'))
                    ->map(function ($file) use ($typeKey) {
                        $originalName = mb_strtolower(trim(basename(str_replace('\\', '/', $file->getClientOriginalName()))));
                        $normalizedType = mb_strtolower(trim($typeKey));
                        return 'itdocs-dup-'.substr(hash('sha256', $originalName.'|'.$normalizedType), 0, 52);
                    })
                    ->unique()
                    ->sort()
                    ->values();

                foreach ($duplicateLockNames as $duplicateLockName) {
                    $lockResult = DB::selectOne('SELECT GET_LOCK(?, 10) AS acquired', [$duplicateLockName]);
                    if ((int) ($lockResult->acquired ?? 0) !== 1) {
                        abort(503, 'Le contrôle des doublons est momentanément occupé. Réessayez dans quelques secondes.');
                    }
                    $duplicateLocks[] = $duplicateLockName;
                }
            }

        foreach ($request->file('files') as $file) {
            $originalName = basename(str_replace('\\', '/', $file->getClientOriginalName()));
            $normalizedName = mb_strtolower(trim($originalName));
            $normalizedType = mb_strtolower(trim($typeKey));
            // Dans un lot, tous les fichiers partagent le même type, tiers et jour de dépôt.
            $duplicateKey = $normalizedName.'|'.$normalizedType;
            // La règle de doublon porte sur le nom d'origine et le type, avec un contexte
            // correspondant soit au même jour de dépôt, soit au même tiers.
            $alreadyStored = Document::withTrashed()
                ->whereRaw('LOWER(TRIM(nom_original)) = ?', [$normalizedName])
                ->whereRaw('LOWER(TRIM(type_libelle)) = ?', [$normalizedType])
                ->where(function ($query) use ($tier) {
                    $query->whereDate('created_at', now()->toDateString());
                    if ($tier) {
                        $query->orWhere('tiers_id', $tier->id);
                    } else {
                        $query->orWhereNull('tiers_id');
                    }
                })
                ->exists();
            if ($alreadyStored || isset($seenInBatch[$duplicateKey])) $duplicateNames[] = $originalName;
            $seenInBatch[$duplicateKey] = true;
        }
        if ($duplicateNames) {
            return response()->json(['message'=>'Dépôt refusé : un fichier portant le même nom d’origine et le même type existe déjà pour ce jour de dépôt ou ce tiers ('.implode(', ', array_unique($duplicateNames)).').'], 409);
        }
        foreach ($request->file('files') as $index=>$file) {
            $originalName = basename(str_replace('\\', '/', $file->getClientOriginalName()));
            $processedAt = now();
            $company=config('app.company_name','IT-CORP');
            $displayType = $knownType ? $selectedType : 'À identifier';
            $baseParts = array_values(array_filter([$company,$displayType,trim($data['reference']),$tierName,$data['date_mode']==='year'?(string)$data['date_year']:$documentDate->format('Y-m-d')]));
            // Le nom reste conforme à la règle métier et ne contient aucun horodatage.
            $baseName = implode(' - ', $baseParts);
            $referencePattern = '/ - '.preg_quote(mb_strtolower(trim($data['reference'])), '/').'([0-9]*) - /u';
            $lockName = 'itdocs-ref-'.substr(hash('sha256', mb_strtolower(trim($data['reference']))), 0, 48);
            $hasReferenceLock = DB::connection()->getDriverName() === 'mysql';
            if ($hasReferenceLock) {
                $lock = DB::selectOne('SELECT GET_LOCK(?, 10) AS acquired', [$lockName]);
                abort_unless((int) ($lock->acquired ?? 0) === 1, 503, 'Le nom du document est en cours de traitement. Réessayez dans quelques secondes.');
            }
            try {
                $doc = DB::transaction(function () use ($baseName, $baseParts, $referencePattern, $processedAt, $tier, $data, $originalName, $knownType, $selectedType, $documentDate, $category, $file, $request, $type) {
                    // Une référence déposée aujourd'hui, ou déjà utilisée pour ce tiers,
                    // entraîne un numéro incrémental. Le verrou sérialise les dépôts concurrents.
                    $relatedNames = Document::withTrashed()->where(function ($query) use ($processedAt, $tier) {
                        $query->whereDate('created_at', $processedAt->toDateString());
                        if ($tier) $query->orWhere('tiers_id', $tier->id);
                        else $query->orWhereNull('tiers_id');
                    })->pluck('nom');
                    $matchingNames = $relatedNames->filter(fn ($name) => preg_match($referencePattern, mb_strtolower($name)) === 1);
                    $sequence = max(1, $matchingNames->count());
                    foreach ($matchingNames as $existingName) {
                        if (preg_match($referencePattern, mb_strtolower($existingName), $match) && ($match[1] ?? '') !== '') {
                            $sequence = max($sequence, (int) $match[1] + 1);
                        } elseif (preg_match('/ - (\d+)$/', $existingName, $match)) {
                            // Compatibilité avec les noms produits par l’ancienne version.
                            $sequence = max($sequence, (int) $match[1] + 1);
                        }
                    }
                    if ($matchingNames->isNotEmpty()) {
                        // Le numéro est ajouté directement à la référence : « Facture client1 ».
                        $numberedParts = $baseParts;
                        $numberedParts[2] .= $sequence;
                        $baseName = implode(' - ', $numberedParts);
                    }
                    $name = $this->uniqueDocumentName($baseName);
                    // Le nom final et son éventuel numéro sont fixés avant l’écriture du fichier.
                    $path = $file->store('documents/'.$processedAt->format('Y/m'), 'local');
                    return Document::create([
                        'nom'=>$name, 'nom_original'=>$originalName, 'fichier'=>$path,
                        'type_libelle'=>$knownType ? $selectedType : 'Autre', 'date_depot'=>$documentDate,
                        'date_precision'=>$data['date_mode'], 'date_archivage'=>$category ? $processedAt : null,
                        'statut'=>$category ? 'archive' : 'en_attente', 'categorie'=>$category,
                        'mois'=>$processedAt->translatedFormat('F'), 'description'=>$data['description'] ?? null,
                        'mime_type'=>$file->getMimeType(), 'taille'=>$file->getSize(), 'utilisateur_id'=>$request->user()->id,
                        'type_document_id'=>$type->id, 'tiers_id'=>$tier?->id, 'destinataire_id'=>$data['recipient_id'],
                    ]);
                });
            } finally {
                if ($hasReferenceLock) DB::selectOne('SELECT RELEASE_LOCK(?) AS released', [$lockName]);
            }
            $created[]=$this->payload($doc->load(['tiers','deposant','destinataire']));
            Notification::create(['utilisateur_id'=>$doc->destinataire_id,'type'=>'Nouveau document','message'=>'[document:'.$doc->id.'] Un nouveau document « '.$doc->nom.' » est disponible.']);
            if ($request->user()->role !== 'admin') foreach(User::where('role','admin')->get() as $admin) Notification::create(['utilisateur_id'=>$admin->id,'type'=>'Document déposé','message'=>'[document:'.$doc->id.'] '.$request->user()->prenom.' '.$request->user()->nom.' a déposé « '.$doc->nom.' ».']);
            if ($category) foreach(User::where('role','assistante')->get() as $assistant) Notification::create(['utilisateur_id'=>$assistant->id,'type'=>'Document traité','message'=>'[document:'.$doc->id.'] Le nommage, le classement et l’archivage de « '.$doc->nom.' » sont terminés.']);
            else foreach(User::where('role','assistante')->get() as $assistant) Notification::create(['utilisateur_id'=>$assistant->id,'type'=>'Document en attente','message'=>'[document:'.$doc->id.'] Le document « '.$doc->nom.' » attend son identification.']);
            if ($request->user()->role === 'admin') foreach(User::where('role','assistante')->get() as $assistant) Notification::create(['utilisateur_id'=>$assistant->id,'type'=>'Dépôt effectué par l’administration','message'=>'[document:'.$doc->id.'] Un document a été déposé par l’administrateur : « '.$doc->nom.' ».']);
            if (!$category) foreach (User::where('role','admin')->get() as $admin) Notification::create(['utilisateur_id'=>$admin->id,'type'=>'Document à identifier','message'=>'[document:'.$doc->id.'] Un document attend son identification.']);
        }
        return response()->json(['documents'=>$created,'message'=>count($created).' document(s) déposé(s).'], 201);
        } finally {
            // Libère toujours les verrous, même si le dépôt est refusé ou rencontre une erreur.
            foreach (array_reverse($duplicateLocks) as $duplicateLockName) {
                DB::selectOne('SELECT RELEASE_LOCK(?) AS released', [$duplicateLockName]);
            }
        }
    }

    public function identify(Request $request, Document $document)
    {
        abort_unless(in_array($request->user()->role, ['admin','assistante'], true), 403);
        abort_unless($document->statut === 'en_attente', 409, 'Ce document ne nécessite plus d’identification.');
        $data = $request->validate([
            'type' => ['nullable','string','max:100',Rule::exists('types_documents','libelle')],
            'category' => ['nullable','string','max:100'],
        ]);
        // Un type peut recevoir une catégorie choisie ou réutiliser son association enregistrée.
        $hasType = !empty($data['type']);
        $hasCategory = !empty(trim((string) ($data['category'] ?? '')));
        abort_if(!$hasType && !$hasCategory, 422, 'Choisissez un type ou une catégorie pour identifier le document.');
        if ($hasType) {
            abort_if(mb_strtolower($data['type']) === 'autre', 422, 'Choisissez un type de document précis.');
            $type = TypeDocument::where('libelle', $data['type'])->firstOrFail();
            $category = $hasCategory
                ? \App\Models\DocumentCategory::firstOrCreate(['name' => trim($data['category'])])->name
                : $this->categoryFor($data['type']);
            abort_unless($category, 422, 'Aucune catégorie existante n’est associée à ce type. Le document reste en attente.');
            if ($hasCategory && \Illuminate\Support\Facades\Schema::hasTable('type_document_categories')) {
                DB::table('type_document_categories')->updateOrInsert(
                    ['type_document_id' => $type->id],
                    ['document_category_id' => \App\Models\DocumentCategory::where('name', $category)->value('id'), 'created_at' => now(), 'updated_at' => now()]
                );
            }
            $typeName = $type->libelle;
        } else {
            $category = \App\Models\DocumentCategory::firstOrCreate(['name' => trim($data['category'])])->name;
            $type = $document->typeDocument ?: TypeDocument::whereRaw('LOWER(libelle) = ?', ['autre'])->first();
            abort_unless($type, 422, 'Le type de référence « Autre » est indisponible.');
            $typeName = $document->type_libelle ?: $type->libelle;
        }
        $datePart = $document->date_precision === 'year' ? $document->date_depot?->format('Y') : $document->date_depot?->format('Y-m-d');
        $company = config('app.company_name','IT-CORP');
        $tierName = $document->tiers?->raison_sociale ?: $document->tiers?->nom ?: 'Sans tiers';
        $parts = explode(' - ', $document->nom);
        $reference = count($parts) >= 5 ? implode(' - ', array_slice($parts, 2, -2)) : 'Document identifié';
        $name = $this->uniqueDocumentName(implode(' - ', [$company,$typeName,$reference,$tierName,(string)$datePart]), $document->id);
        $document->update(['type_document_id'=>$type->id,'type_libelle'=>$typeName,'categorie'=>$category,'statut'=>'archive','date_archivage'=>now(),'nom'=>$name]);
        $document->refresh();
        foreach(User::where('role','assistante')->get() as $assistant) Notification::create(['utilisateur_id'=>$assistant->id,'type'=>'Document traité','message'=>'[document:'.$document->id.'] Le document « '.$document->nom.' » a été identifié et archivé.']);
        if($request->user()->role!=='admin')foreach(User::where('role','admin')->get() as $admin)Notification::create(['utilisateur_id'=>$admin->id,'type'=>'Document identifié','message'=>'[document:'.$document->id.'] '.$request->user()->prenom.' '.$request->user()->nom.' a identifié et archivé « '.$document->nom.' ».']);
        return response()->json(['document'=>$this->payload($document->load(['tiers','deposant','destinataire'])),'typeCategoryMapping'=>$hasType ? [$type->libelle=>$category] : [],'message'=>'Document identifié et archivé.']);
    }

    public function exportMonth(Request $request)
    {
        abort_unless(in_array($request->user()->role, ['admin','assistante'], true), 403);
        $data = $request->validate(['month'=>['required','date_format:Y-m']]);
        abort_unless(class_exists(\ZipArchive::class), 503, 'La création d’archives ZIP n’est pas disponible sur ce serveur.');
        $documents = Document::whereNotNull('date_archivage')->whereRaw("DATE_FORMAT(date_archivage, '%Y-%m') = ?", [$data['month']])->orderBy('date_archivage')->get();
        $temp = storage_path('app/private/exports/'.uniqid('lot-', true).'.zip');
        File::ensureDirectoryExists(dirname($temp));
        $zip = new \ZipArchive();
        abort_unless($zip->open($temp, \ZipArchive::CREATE) === true, 500, 'Impossible de créer l’archive mensuelle.');
        $csv = "Nom;Type;Catégorie;Tiers;Date du document;Date d’archivage\\r\\n";
        foreach ($documents as $doc) {
            if (Storage::disk('local')->exists($doc->fichier)) $zip->addFile(Storage::disk('local')->path($doc->fichier), 'documents/'.basename($doc->nom).'.'.pathinfo($doc->fichier, PATHINFO_EXTENSION));
            $csv .= implode(';', array_map(fn($v)=>'"'.str_replace('"','""',(string)$v).'"', [$doc->nom,$doc->type_libelle,$doc->categorie,$doc->tiers?->raison_sociale ?: $doc->tiers?->nom,$doc->date_precision==='year'?$doc->date_depot?->format('Y'):$doc->date_depot?->format('d/m/Y'),$doc->date_archivage?->format('d/m/Y H:i')]))."\\r\\n";
        }
        $zip->addFromString('index_documents.csv', "\\xEF\\xBB\\xBF".$csv);
        $zip->close();
        return response()->download($temp, 'documents-'.$data['month'].'.zip')->deleteFileAfterSend(true);
    }

    public function destroy(Request $request, Document $document)
    {
        abort_unless($request->user()->role !== 'ceo', 403);
        $document->delete(); // suppression logique : la ligne et le fichier restent récupérables.
        if($request->user()->role!=='admin') foreach(User::where('role','admin')->get() as $admin) Notification::create(['utilisateur_id'=>$admin->id,'type'=>'Document supprimé','message'=>$request->user()->prenom.' '.$request->user()->nom.' a supprimé « '.$document->nom.' ».']);
        return response()->json(['message'=>'Document supprimé.']);
    }

    public function consult(Request $request, Document $document)
    {
        abort_if($request->user()->role === 'ceo' && $document->destinataire_id !== $request->user()->id, 403);
        $document->increment('consultations_count'); $document->update(['derniere_consultation'=>now()]);
        if($request->user()->role==='ceo' && $document->utilisateur_id!==$request->user()->id) Notification::create(['utilisateur_id'=>$document->utilisateur_id,'type'=>'Document consulté','message'=>'Le CEO a consulté « '.$document->nom.' ».']);
        if($request->user()->role!=='admin')foreach(User::where('role','admin')->get() as $admin)Notification::create(['utilisateur_id'=>$admin->id,'type'=>'Document consulté','message'=>'[document:'.$document->id.'] '.$request->user()->prenom.' '.$request->user()->nom.' a consulté « '.$document->nom.' ».']);
        $query = $this->isOfficeDocument($document) ? ['preview'=>1] : [];
        return response()->json(['url'=>route('documents.file', array_merge(['document'=>$document->id], $query))]);
    }

    public function file(Request $request, Document $document)
    {
        abort_if($request->user()->role === 'ceo' && $document->destinataire_id !== $request->user()->id, 403);
        abort_unless(Storage::disk('local')->exists($document->fichier), 404);
        if($request->boolean('download')) return Storage::disk('local')->download($document->fichier, $document->nom.'.'.pathinfo($document->fichier, PATHINFO_EXTENSION), ['Content-Type'=>$document->mime_type ?: 'application/octet-stream']);
        if ($request->boolean('preview') && $this->isOfficeDocument($document)) {
            $preview = $this->officePreview($document);
            return response()->file($preview, ['Content-Type'=>'application/pdf','Content-Disposition'=>'inline; filename="'.str_replace(['"',"\r","\n"],'',basename($document->nom)).'.pdf"']);
        }
        return response()->file(Storage::disk('local')->path($document->fichier), ['Content-Type'=>$document->mime_type ?: 'application/octet-stream','Content-Disposition'=>'inline; filename="'.str_replace('"','',basename($document->nom)).'.'.pathinfo($document->fichier, PATHINFO_EXTENSION).'"']);
    }

    private function isOfficeDocument(Document $document): bool
    {
        return in_array(strtolower(pathinfo($document->fichier, PATHINFO_EXTENSION)), ['doc','docx','xls','xlsx'], true);
    }

    private function officePreview(Document $document): string
    {
        $disk = Storage::disk('local');
        $cacheRelative = 'document-previews/'.$document->id.'.pdf';
        $cachePath = $disk->path($cacheRelative);
        if (is_file($cachePath) && filesize($cachePath) > 0) return $cachePath;

        $binary = config('services.libreoffice.binary');
        abort_unless($binary && is_file($binary), 503, 'La prévisualisation de ce fichier bureautique est indisponible sur ce serveur.');
        $source = $disk->path($document->fichier);
        set_time_limit(180);
        // LibreOffice on Windows can crash when its user profile is created
        // beneath a long project path. Use the OS temporary directory for both
        // its profile and output, then move the generated PDF into private cache.
        $workPath = $disk->path('document-previews/.tmp-'.bin2hex(random_bytes(8)));
        File::ensureDirectoryExists($workPath);
        $temporarySource = $workPath.DIRECTORY_SEPARATOR.'source.'.strtolower(pathinfo($source, PATHINFO_EXTENSION));
        File::copy($source, $temporarySource);
        $profilePath = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.'itdocs-lo-profile-'.bin2hex(random_bytes(8));
        File::ensureDirectoryExists($profilePath);
        $profileUrl = 'file:///'.str_replace(['\\', ' '], ['/', '%20'], $profilePath);
        try {
            $conversion = Process::timeout(120)->run([$binary, '-env:UserInstallation='.$profileUrl, '--headless', '--convert-to', 'pdf', '--outdir', $workPath, $temporarySource]);
            $generated = $workPath.DIRECTORY_SEPARATOR.'source.pdf';
            abort_unless(is_file($generated) && filesize($generated) > 0, 503, 'Impossible de créer l’aperçu de ce document. Réessayez ou téléchargez le fichier original.');
            File::ensureDirectoryExists(dirname($cachePath));
            File::move($generated, $cachePath);
        } finally {
            File::deleteDirectory($workPath);
            File::deleteDirectory($profilePath);
        }
        return $cachePath;
    }

    private function payload(Document $d): array
    {
        return ['id'=>$d->id,'name'=>$d->nom,'type'=>$d->statut === 'en_attente' ? 'À identifier' : $d->type_libelle,'category'=>$d->categorie ?: '—','tier'=>$d->tiers?->raison_sociale ?: $d->tiers?->nom ?: '—','date'=>$d->date_archivage?->format('d/m/Y') ?: '—','documentDate'=>$d->date_precision==='year'?$d->date_depot?->format('Y'):$d->date_depot?->format('d/m/Y'),'archivedAt'=>$d->date_archivage?->format('d/m/Y H:i'),'datePrecision'=>$d->date_precision ?: 'day','status'=>$d->statut === 'archive' ? 'Archivé' : 'En attente d’identification','size'=>$d->taille >= 1048576 ? round($d->taille/1048576,1).' Mo' : max(1,round($d->taille/1024)).' Ko','ext'=>strtoupper(pathinfo($d->fichier, PATHINFO_EXTENSION)),'mime'=>$d->mime_type,'url'=>route('documents.file',$d),'description'=>$d->description,'recipientId'=>$d->destinataire_id,'consultations'=>$d->consultations_count,'lastConsulted'=>$d->derniere_consultation?->toIso8601String()];
    }
}
