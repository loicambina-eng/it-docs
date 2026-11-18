<?php
// Role du fichier: Modele des documents deposes, en attente ou archives.
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Document extends Model
{
    use SoftDeletes;

    protected $fillable = ['nom', 'nom_original', 'fichier', 'type_libelle', 'date_depot', 'date_precision', 'date_archivage', 'statut', 'categorie', 'mois', 'description', 'mime_type', 'taille', 'consultations_count', 'derniere_consultation', 'utilisateur_id', 'type_document_id', 'tiers_id', 'destinataire_id'];

    protected function casts(): array { return ['date_depot' => 'datetime', 'date_archivage' => 'datetime', 'derniere_consultation' => 'datetime']; }
    public function deposant() { return $this->belongsTo(User::class, 'utilisateur_id'); }
    public function destinataire() { return $this->belongsTo(User::class, 'destinataire_id'); }
    public function typeDocument() { return $this->belongsTo(TypeDocument::class); }
    public function tiers() { return $this->belongsTo(Tiers::class); }
}
