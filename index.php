<?php
session_start();
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

date_default_timezone_set('Europe/Paris');

require_once __DIR__ . '/vendor/autoload.php';

use App\AdsManager;
use App\Database;
use App\LeboncoinUrlBuilder;
use Dotenv\Dotenv;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;

// Load environment variables
if (file_exists(__DIR__ . '/.env')) {
    $dotenv = Dotenv::createImmutable(__DIR__);
    $dotenv->load();
}

// Setup Twig
$loader = new FilesystemLoader(__DIR__ . '/templates');
$twig = new Environment($loader, [
    'cache' => false, // Set to __DIR__ . '/cache' in production
    'debug' => true,
]);

$adsManager = new AdsManager();

// Simple routing
$action = $_GET['action'] ?? 'dashboard';

// Authentication Check
$appPassword = $_ENV['APP_PASSWORD'] ?? $_SERVER['APP_PASSWORD'] ?? '1234';
$isAuthenticated = isset($_SESSION['authenticated']) && $_SESSION['authenticated'] === true;

if ($action === 'login') {
    // Check if blocked
    if (isset($_SESSION['blocked_until']) && $_SESSION['blocked_until'] > time()) {
        $remaining = $_SESSION['blocked_until'] - time();
        $error = "Trop de tentatives. Réessayez dans " . ceil($remaining / 60) . " minute(s).";
    } else {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (($_POST['password'] ?? '') === $appPassword) {
                $_SESSION['authenticated'] = true;
                unset($_SESSION['login_attempts']);
                unset($_SESSION['blocked_until']);
                header('Location: index.php');
                exit;
            } else {
                $_SESSION['login_attempts'] = ($_SESSION['login_attempts'] ?? 0) + 1;
                if ($_SESSION['login_attempts'] >= 5) {
                    $_SESSION['blocked_until'] = time() + 300; // Block for 5 minutes
                    $error = "Trop de tentatives. Compte bloqué pour 5 minutes.";
                } else {
                    $error = "Code incorrect. Tentative " . $_SESSION['login_attempts'] . "/5";
                }
            }
        }
    }
    
    echo $twig->render('login.html.twig', [
        'error' => $error ?? null,
        'blocked' => isset($_SESSION['blocked_until']) && $_SESSION['blocked_until'] > time()
    ]);
    exit;
}

if ($action === 'logout') {
    session_destroy();
    header('Location: index.php?action=login');
    exit;
}

// Redirect to login if not authenticated
if (!$isAuthenticated) {
    header('Location: index.php?action=login');
    exit;
}

if ($action === 'add_search' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $adsManager->addSearch(
        $_POST['name'],
        $_POST['zipcodes'],
        $_POST['price_min'] ?: null,
        $_POST['price_max'] ?: null,
        $_POST['keywords'],
        $_POST['category'] ?? '9',
        isset($_POST['is_donation']) ? 1 : 0,
        $_POST['excluded_categories'] ?: null
    );
    header('Location: index.php?action=searches');
    exit;
}

if ($action === 'edit_search' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $adsManager->editSearch(
        $_POST['id'],
        $_POST['name'],
        $_POST['zipcodes'],
        $_POST['price_min'] ?: null,
        $_POST['price_max'] ?: null,
        $_POST['keywords'],
        $_POST['category'] ?? '9',
        isset($_POST['is_donation']) ? 1 : 0,
        $_POST['excluded_categories'] ?: null
    );
    header('Location: index.php?action=searches');
    exit;
}

if ($action === 'delete_search' && isset($_GET['id'])) {
    $adsManager->deleteSearch($_GET['id']);
    header('Location: index.php?action=searches');
    exit;
}

if ($action === 'toggle_search' && isset($_GET['id'])) {
    $adsManager->toggleSearchStatus($_GET['id']);
    header('Location: index.php?action=searches');
    exit;
}

if ($action === 'toggle_favorite' && isset($_GET['id'])) {
    $adsManager->toggleFavorite($_GET['id']);
    echo json_encode(['success' => true]);
    exit;
}

if ($action === 'mark_seen' && isset($_GET['id'])) {
    $adsManager->markAsSeen($_GET['id']);
    echo json_encode(['success' => true]);
    exit;
}

if ($action === 'check_new_ads') {
    $currentSearchId = $_GET['search_id'] ?? null;
    $lastCheck = $_GET['last_check'] ?? null;
    
    $newAdsCount = $adsManager->getNewAdsCount($currentSearchId, $lastCheck);
    $scannerStatus = $adsManager->getScannerStatus();
    
    echo json_encode([
        'new_ads_count' => $newAdsCount,
        'scanner_status' => $scannerStatus,
        'current_time' => date('Y-m-d H:i:s')
    ]);
    exit;
}

if ($action === 'run_scan') {
    // Activate token in DB
    $db = Database::getInstance();
    $stmt = $db->prepare("INSERT INTO system_stats (name, value) VALUES ('scan_request', 'pending') ON DUPLICATE KEY UPDATE value = 'pending'");
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Erreur DB']);
    }
    exit;
}

// Render page
$categories = \App\Categories::getAll();
$flatCategories = [];
foreach ($categories as $group => $cats) {
    foreach ($cats as $id => $name) {
        $flatCategories[$id] = $name;
    }
}
$scannerStatus = $adsManager->getScannerStatus();

if ($action === 'searches') {
    $searches = $adsManager->getSearches();
    
    // Générer les URLs Leboncoin pour chaque recherche
    foreach ($searches as &$search) {
        $search['leboncoin_url'] = LeboncoinUrlBuilder::buildUrl($search);
    }
    
    echo $twig->render('searches.html.twig', [
        'searches' => $searches,
        'categories' => $categories,
        'flatCategories' => $flatCategories,
        'scannerStatus' => $scannerStatus,
        'page' => 'searches'
    ]);
} elseif ($action === 'archives') {
    $currentSearchId = $_GET['search_id'] ?? null;
    echo $twig->render('archives.html.twig', [
        'ads' => $adsManager->getArchivedAds(100, $currentSearchId),
        'searches' => $adsManager->getSearches(),
        'categories' => $categories,
        'flatCategories' => $flatCategories,
        'scannerStatus' => $scannerStatus,
        'logs' => $adsManager->getLatestLogs(20),
        'current_search_id' => $currentSearchId,
        'page' => 'archives'
    ]);
} else {
    $currentSearchId = $_GET['search_id'] ?? null;
    echo $twig->render('dashboard.html.twig', [
        'ads' => $adsManager->getLatestAds(100, $currentSearchId),
        'searches' => $adsManager->getSearches(),
        'categories' => $categories,
        'flatCategories' => $flatCategories,
        'scannerStatus' => $scannerStatus,
        'logs' => $adsManager->getLatestLogs(20),
        'current_search_id' => $currentSearchId,
        'page' => 'dashboard'
    ]);
}
