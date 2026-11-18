<?php
// Role du fichier: Modele des comptes utilisateurs et de leurs relations Laravel.
namespace App\Models;

use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use Notifiable, SoftDeletes;

    protected $fillable = [
        'nom', 'prenom', 'email', 'telephone', 'fonction', 'adresse', 'nationalite',
        'role', 'statut', 'en_ligne', 'derniere_activite', 'password', 'photo',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return ['email_verified_at' => 'datetime', 'password' => 'hashed', 'en_ligne' => 'boolean', 'derniere_activite' => 'datetime'];
    }

    public function documentsDeposes() { return $this->hasMany(Document::class, 'utilisateur_id'); }
    public function documentsRecus() { return $this->hasMany(Document::class, 'destinataire_id'); }
}
