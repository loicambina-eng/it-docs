<?php
// Role du fichier: Modele des types de documents.
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
class TypeDocument extends Model { use SoftDeletes; protected $table = 'types_documents'; protected $fillable = ['libelle', 'description']; }
