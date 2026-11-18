<?php
// Role du fichier: Controleur des operations sur les tiers.
namespace App\Http\Controllers;

use App\Models\Notification;
use App\Models\Tiers;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class TierController extends Controller
{
    private function report(Request $request, string $action, string $tier): void
    {
        if ($request->user()->role === 'admin') return;
        foreach (User::where('role', 'admin')->get() as $admin) {
            Notification::create(['utilisateur_id'=>$admin->id,'type'=>'Action sur un tiers','message'=>$request->user()->prenom.' '.$request->user()->nom.' '.$action.' le tiers « '.$tier.' ».']);
        }
    }

    public function index(Request $request)
    {
        abort_unless($request->user()->role !== 'ceo', 403);
        return response()->json(['tiers'=>Tiers::orderBy('raison_sociale')->get()]);
    }

    public function store(Request $request)
    {
        abort_unless($request->user()->role !== 'ceo', 403);
        $data=$request->validate(['type_tiers'=>['required','string','max:50'],'nom'=>['nullable','string','max:100'],'prenom'=>['nullable','string','max:100'],'raison_sociale'=>['nullable','string','max:150'],'fonction'=>['nullable','string','max:100'],'adresse'=>['nullable','string','max:500'],'telephone'=>['nullable','string','max:30'],'email'=>['nullable','email','max:150']]);
        $attributes=[];
        foreach(['type_tiers','nom','prenom','raison_sociale','fonction','adresse','telephone','email'] as $field) $attributes[$field]=isset($data[$field])?trim((string)$data[$field]):null;
        $match=Tiers::withTrashed()->get()->first(function($tier) use ($attributes) {
            foreach($attributes as $field=>$value) if((string)($tier->{$field}??'') !== (string)($value??'')) return false;
            return true;
        });
        if($match){
            if($match->trashed()) $match->restore();
            $tier=$match;
            $created=false;
        }else{
            $tier=Tiers::create($data+['statut'=>'actif']);
            $created=true;
        }
        $this->report($request, 'a ajouté', $tier->raison_sociale ?: $tier->nom ?: 'un tiers');
        return response()->json(['tier'=>$tier,'message'=>$created?'Tiers ajouté.':'Tiers déjà enregistré.'], $created?201:200);
    }

    public function update(Request $request, Tiers $tier)
    {
        abort_unless($request->user()->role !== 'ceo', 403);
        $data=$request->validate(['type_tiers'=>['required','string','max:50'],'nom'=>['nullable','string','max:100'],'prenom'=>['nullable','string','max:100'],'raison_sociale'=>['nullable','string','max:150'],'fonction'=>['nullable','string','max:100'],'adresse'=>['nullable','string','max:500'],'telephone'=>['nullable','string','max:30'],'email'=>['nullable','email','max:150'],'statut'=>['sometimes','required',Rule::in(['actif','inactif'])]]);
        $oldStatus=$tier->statut;
        $tier->update($data);
        $action=array_key_exists('statut',$data)&&$oldStatus!==$tier->statut?'a modifié le statut de':'a modifié';
        $this->report($request, $action, $tier->raison_sociale ?: $tier->nom ?: 'un tiers');
        return response()->json(['tier'=>$tier]);
    }

}
