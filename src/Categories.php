<?php
namespace App;

class Categories {
    public static function getAll() {
        return [
            'Général' => [
                '0' => 'Toutes catégories'
            ],
            'Immobilier' => [
                '9' => 'Ventes immobilières',
                '10' => 'Locations',
                '11' => 'Colocations',
                '13' => 'Locations saisonnières',
                '12' => 'Bureaux & Commerces'
            ],
            'Véhicules' => [
                '2' => 'Voitures',
                '3' => 'Motos',
                '4' => 'Caravaning',
                '5' => 'Utilitaires',
                '6' => 'Équipement Auto',
                '44' => 'Équipement Moto',
                '7' => 'Nautisme'
            ],
            'Multimédia' => [
                '15' => 'Informatique',
                '16' => 'Consoles & Jeux vidéo',
                '17' => 'Image & Son',
                '18' => 'Téléphonie'
            ],
            'Maison' => [
                '19' => 'Ameublement',
                '20' => 'Électroménager',
                '21' => 'Arts de la table',
                '22' => 'Décoration',
                '23' => 'Linge de maison',
                '24' => 'Bricolage',
                '25' => 'Jardinage',
                '26' => 'Vêtements',
                '27' => 'Chaussures',
                '28' => 'Accessoires & Bagagerie',
                '29' => 'Montres & Bijoux',
                '30' => 'Équipement bébé',
                '31' => 'Vêtements bébé'
            ],
            'Loisirs' => [
                '33' => 'DVD / Films',
                '34' => 'CD / Musique',
                '35' => 'Livres',
                '36' => 'Animaux',
                '37' => 'Vélos',
                '38' => 'Sports & Hobbies',
                '39' => 'Instruments de musique',
                '40' => 'Collection',
                '41' => 'Jeux & Jouets'
            ],
            'Matériel Pro' => [
                '50' => 'Matériel Agricole',
                '51' => 'Transport - Manutention',
                '52' => 'BTP - Chantier',
                '54' => 'Équipements Industriels'
            ]
        ];
    }

    public static function getName($id) {
        foreach (self::getAll() as $group => $cats) {
            if (isset($cats[$id])) return $cats[$id];
        }
        return "Autre ($id)";
    }
}
