<?php
// Role du fichier: Correspondances par defaut entre types et categories.
return [
    // Une association par type, résolue uniquement si la catégorie existe déjà en base.
    'types' => [
        'Bordereau' => 'Administration',
        'Fiche d’intervention' => 'Interventions',
        'Note de service' => 'Administration',
        'Note interne' => 'Administration',
        'Courrier' => 'Courriers',
        'Courrier entrant' => 'Courriers',
        'Courrier sortant' => 'Courriers',
        'Contrat' => 'Contrats',
        'Facture' => 'Factures',
        'Rapport' => 'Autre',
    ],
];
