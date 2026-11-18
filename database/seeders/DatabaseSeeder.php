<?php
// Role du fichier: Donnees initiales de la base.
namespace Database\Seeders;

use App\Models\TypeDocument;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Les comptes de rôles existent dès l'installation; les documents et tiers restent vides.
        $accounts = [
            ['nom'=>'Mba','prenom'=>'Pauline','email'=>'admin@it-corp.co','fonction'=>'Administratrice','role'=>'admin'],
            ['nom'=>'MBINA MOUSSAVOU','prenom'=>'Jeannie Loïca','email'=>'assistante@it-corp.co','fonction'=>'Assistante de direction','role'=>'assistante'],
            ['nom'=>'Pierre','prenom'=>'Jean','email'=>'ceo@it-corp.co','fonction'=>'Directeur général','role'=>'ceo'],
        ];
        foreach ($accounts as $account) {
            User::firstOrCreate(['email'=>$account['email']], $account + ['password'=>'itcorp123','statut'=>'inactif','en_ligne'=>false,'adresse'=>'Libreville, Gabon','nationalite'=>'Gabonaise']);
        }

        foreach (['Bordereau','Fiche d’intervention','Note de service','Note interne','Courrier','Contrat','Facture','Rapport','Autre'] as $type) {
            TypeDocument::firstOrCreate(['libelle'=>$type]);
        }
    }
}
