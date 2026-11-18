<?php
// Role du fichier: Modele des notifications destinees aux utilisateurs.
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Notification extends Model { protected $fillable=['utilisateur_id','type','message','lu']; protected function casts():array{return['lu'=>'boolean'];} }
