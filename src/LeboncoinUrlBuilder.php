<?php
namespace App;

class LeboncoinUrlBuilder {
    
    /**
     * Génère une URL Leboncoin à partir des paramètres de recherche
     */
    public static function buildUrl($search) {
        $baseUrl = "https://www.leboncoin.fr/recherche";
        $params = [];
        
        // Catégorie (utiliser le paramètre category au lieu du slug)
        if (!empty($search['category']) && $search['category'] != '0') {
            $params['category'] = $search['category'];
        }
        
        // Mots-clés
        if (!empty($search['keywords'])) {
            $params['text'] = $search['keywords'];
        }
        
        // Localisation (codes postaux séparés par des virgules)
        if (!empty($search['zipcodes'])) {
            $zipcodes = array_map('trim', explode(',', $search['zipcodes']));
            $params['locations'] = implode(',', $zipcodes);
        }
        
        // Prix
        if (!empty($search['price_min']) && !empty($search['price_max'])) {
            $params['price'] = $search['price_min'] . '-' . $search['price_max'];
        } elseif (!empty($search['price_min'])) {
            $params['price'] = $search['price_min'] . '-999999999';
        } elseif (!empty($search['price_max'])) {
            $params['price'] = '0-' . $search['price_max'];
        }
        
        // Dons
        if (!empty($search['is_donation']) && $search['is_donation'] == 1) {
            $params['donation'] = '1';
        }
        
        // Construction de l'URL finale
        if (!empty($params)) {
            $baseUrl .= '?' . http_build_query($params);
        }
        
        return $baseUrl;
    }
    
    /**
     * Retourne le slug de catégorie pour l'URL
     */
    private static function getCategorySlug($categoryId) {
        $slugs = [
            '9' => 'ventes_immobilieres',
            '10' => 'locations',
            '11' => 'colocations',
            '12' => 'bureaux_commerces',
            '1' => 'vehicules',
            '2' => 'voitures',
            '3' => 'motos',
            '4' => 'caravaning',
            '5' => 'utilitaires',
            '6' => 'equipement_auto',
            '7' => 'equipement_moto',
            '8' => 'equipement_caravaning',
            '13' => 'emploi',
            '14' => 'offres_emploi',
            '15' => 'mode',
            '16' => 'vetements',
            '17' => 'chaussures',
            '18' => 'accessoires_bagagerie',
            '19' => 'montres_bijoux',
            '20' => 'equipement_bebe',
            '21' => 'maison',
            '22' => 'ameublement',
            '23' => 'electromenager',
            '24' => 'arts_de_la_table',
            '25' => 'decoration',
            '26' => 'linge_de_maison',
            '27' => 'bricolage',
            '28' => 'jardinage',
            '29' => 'multimedia',
            '30' => 'informatique',
            '31' => 'consoles_jeux',
            '32' => 'image_son',
            '33' => 'telephonie',
            '34' => 'loisirs',
            '35' => 'dvd_films',
            '36' => 'animaux',
            '37' => 'velos',
            '38' => 'sports_loisirs',
            '39' => 'instruments_musique',
            '40' => 'collection',
            '41' => 'jeux_jouets',
            '42' => 'vins_gastronomie',
            '43' => 'livres',
            '44' => 'vacances',
            '45' => 'locations_gites',
            '46' => 'chambres_hotes',
            '47' => 'campings',
            '48' => 'hotels',
            '49' => 'hebergements_insolites',
            '50' => 'services',
            '51' => 'prestations_de_services',
            '52' => 'billetterie',
            '53' => 'evenements',
            '54' => 'cours_particuliers',
            '55' => 'covoiturage'
        ];
        
        return $slugs[$categoryId] ?? null;
    }
}
