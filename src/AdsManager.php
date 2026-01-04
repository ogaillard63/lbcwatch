<?php
namespace App;

use PDO;

class AdsManager {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    public function getLatestAds($limit = 100, $searchId = null) {
        $sql = "
            SELECT a.*, s.name as search_name 
            FROM ads a
            JOIN searches s ON a.search_id = s.id
            WHERE a.is_seen = 0
        ";
        
        if ($searchId) {
            $sql .= " AND a.search_id = :search_id";
        }
        
        $sql .= " ORDER BY a.scraped_at DESC LIMIT :limit";
        
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':limit', (int)$limit, PDO::PARAM_INT);
        if ($searchId) {
            $stmt->bindValue(':search_id', (int)$searchId, PDO::PARAM_INT);
        }
        
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function getArchivedAds($limit = 100, $searchId = null) {
        $sql = "
            SELECT a.*, s.name as search_name 
            FROM ads a
            JOIN searches s ON a.search_id = s.id
            WHERE a.is_seen = 1
        ";
        
        if ($searchId) {
            $sql .= " AND a.search_id = :search_id";
        }
        
        $sql .= " ORDER BY a.scraped_at DESC LIMIT :limit";
        
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':limit', (int)$limit, PDO::PARAM_INT);
        if ($searchId) {
            $stmt->bindValue(':search_id', (int)$searchId, PDO::PARAM_INT);
        }
        
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function getNewAdsCount($searchId = null, $lastCheck = null) {
        $sql = "
            SELECT COUNT(*) as count
            FROM ads a
            WHERE a.is_seen = 0
        ";
        
        if ($lastCheck) {
            $sql .= " AND a.scraped_at > :last_check";
        }
        
        if ($searchId) {
            $sql .= " AND a.search_id = :search_id";
        }
        
        $stmt = $this->db->prepare($sql);
        
        if ($lastCheck) {
            $stmt->bindValue(':last_check', $lastCheck);
        }
        if ($searchId) {
            $stmt->bindValue(':search_id', (int)$searchId, PDO::PARAM_INT);
        }
        
        $stmt->execute();
        $result = $stmt->fetch();
        return (int)$result['count'];
    }

    public function getSearches() {
        $stmt = $this->db->query("SELECT * FROM searches ORDER BY created_at DESC");
        return $stmt->fetchAll();
    }

    public function getSearch($id) {
        $stmt = $this->db->prepare("SELECT * FROM searches WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch();
    }

    public function addSearch($name, $zipcodes, $price_min, $price_max, $keywords, $category = '9', $is_donation = 0, $excluded_categories = null) {
        $stmt = $this->db->prepare("
            INSERT INTO searches (name, zipcodes, price_min, price_max, keywords, category, is_donation, excluded_categories) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        return $stmt->execute([$name, $zipcodes, $price_min, $price_max, $keywords, $category, $is_donation, $excluded_categories]);
    }

    public function editSearch($id, $name, $zipcodes, $price_min, $price_max, $keywords, $category = '9', $is_donation = 0, $excluded_categories = null) {
        $stmt = $this->db->prepare("
            UPDATE searches 
            SET name = ?, zipcodes = ?, price_min = ?, price_max = ?, keywords = ?, category = ?, is_donation = ?, excluded_categories = ? 
            WHERE id = ?
        ");
        return $stmt->execute([$name, $zipcodes, $price_min, $price_max, $keywords, $category, $is_donation, $excluded_categories, $id]);
    }

    public function deleteSearch($id) {
        $stmt = $this->db->prepare("DELETE FROM searches WHERE id = ?");
        return $stmt->execute([$id]);
    }

    public function getScannerStatus() {
        $stmt = $this->db->query("SELECT MAX(last_checked) as last_activity FROM searches");
        $res = $stmt->fetch();
        
        $stmtLaunch = $this->db->query("SELECT value FROM system_stats WHERE name = 'last_launch'");
        $launchRes = $stmtLaunch->fetch();
        $lastLaunch = $launchRes['value'] ?? null;
        
        if (!$res['last_activity']) return [
            'status' => 'Inactif', 
            'color' => 'red', 
            'last' => null, 
            'last_launch' => $lastLaunch
        ];
        
        $lastChecked = strtotime($res['last_activity']);
        // Compte comme actif si dernière activité il y a moins de 10 minutes
        if ((time() - $lastChecked) < 600) {
            return [
                'status' => 'Actif', 
                'color' => 'green', 
                'last' => $res['last_activity'], 
                'last_launch' => $lastLaunch
            ];
        }
        
        return [
            'status' => 'En veille', 
            'color' => 'yellow', 
            'last' => $res['last_activity'], 
            'last_launch' => $lastLaunch
        ];
    }

    public function markAsSeen($id) {
        $stmt = $this->db->prepare("UPDATE ads SET is_seen = 1 WHERE id = ?");
        return $stmt->execute([$id]);
    }

    public function toggleFavorite($id) {
        $stmt = $this->db->prepare("UPDATE ads SET is_favorite = NOT is_favorite WHERE id = ?");
        return $stmt->execute([$id]);
    }

    public function getLatestLogs($limit = 10) {
        $stmt = $this->db->prepare("SELECT * FROM logs ORDER BY created_at DESC LIMIT ?");
        $stmt->bindValue(1, (int)$limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }
}
