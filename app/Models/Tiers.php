<?php
// Role du fichier: Modele des tiers associes aux documents.
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
class Tiers extends Model { use SoftDeletes; protected $fillable = ['type_tiers','nom','prenom','raison_sociale','fonction','adresse','telephone','email','statut']; }
