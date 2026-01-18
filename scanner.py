"""
Scanner Leboncoin - Version Robuste

Description :
Ce script surveille les nouvelles annonces selon les configurations en base de données (table `searches`).
Il utilise une approche furtive (curl_cffi) pour contourner les protections anti-bot.

Cycle d'Exécution :
1. Mode Automatique :
   - Fréquence : Aléatoire entre 45 et 75 minutes pour imiter un comportement humain.
   - Pause Nocturne : Le scanner se met en pause de 00h00 à 07h00 (aucun scan auto).
   
2. Mode Manuel :
   - Prioritaire : Déclenchement immédiat si demandé via l'interface web.
   - Force le scan même pendant la pause nocturne.

Gestion des Erreurs :
- Rotation automatique des sessions et User-Agents en cas de blocage (403).
"""

import time
import mysql.connector
from datetime import datetime
import json
import os
from dotenv import load_dotenv
from curl_cffi import requests
import random

load_dotenv()

# Configuration DB
db_config = {
    'host': os.getenv('DB_HOST', 'localhost'),
    'user': os.getenv('DB_USER', 'root'),
    'password': os.getenv('DB_PASS', 'root'),
    'database': os.getenv('DB_NAME', 'lbcwatch')
}

# --- NOUVELLE LOGIQUE SIMPLIFIÉE BASÉE SUR LA LIBRAIRIE LBC ---

class LbcClient:
    def __init__(self, max_retries=5):
        self.max_retries = max_retries
        self.session = None
        self._init_session()

    def _init_session(self):
        """Crée une nouvelle session propre avec un navigateur aléatoire"""
        # On laisse curl_cffi choisir les détails (pas de User-Agent forcé manuellement)
        browsers = ["chrome", "edge", "safari", "firefox"]
        browser_choice = random.choice(browsers)
        
        self.session = requests.Session(impersonate=browser_choice)
        
        # Headers minimaux requis, le reste est géré par l'impersonate
        self.session.headers.update({
            'Sec-Fetch-Dest': 'empty',
            'Sec-Fetch-Mode': 'cors',
            'Sec-Fetch-Site': 'same-site',
        })
        
        # Initialisation des cookies (important !)
        try:
            self.session.get("https://www.leboncoin.fr/")
            log_to_db(f"Session initialisée ({browser_choice})", "DEBUG")
        except Exception as e:
            log_to_db(f"Erreur init session: {e}", "ERROR")

    def post(self, url, payload, retries=None):
        """Envoie une requête POST avec gestion des retries automatique sur 403"""
        if retries is None:
            retries = self.max_retries

        try:
            response = self.session.post(url, json=payload, timeout=30)
            
            if response.status_code == 200:
                return response
            
            elif response.status_code == 403:
                if retries > 0:
                    log_to_db(f"Accès refusé (403), nouvelle tentative... ({retries} restants)", "WARNING")
                    # On détruit et recrée la session
                    self._init_session()
                    time.sleep(random.uniform(2, 5))
                    return self.post(url, payload, retries - 1)
                else:
                    log_to_db("Bloqué par Datadome (403) après plusieurs essais.", "ERROR")
                    return None
            else:
                log_to_db(f"Erreur HTTP {response.status_code}", "ERROR")
                return response
                
        except Exception as e:
            if retries > 0:
                log_to_db(f"Exception réseau: {e}, retry...", "WARNING")
                time.sleep(2)
                self._init_session()
                return self.post(url, payload, retries - 1)
            return None

# --- FONCTIONS UTILITAIRES ---

def get_db_connection():
    conn = mysql.connector.connect(**db_config)
    cursor = conn.cursor()
    cursor.execute("SET time_zone = '+01:00'")
    cursor.close()
    return conn

def log_to_db(message, level="INFO"):
    try:
        conn = get_db_connection()
        cursor = conn.cursor()
        sql = "INSERT INTO logs (message, level) VALUES (%s, %s)"
        cursor.execute(sql, (message, level))
        conn.commit()
        cursor.close()
        conn.close()
        print(f"[{level}] {message}")
    except Exception as e:
        print(f"[!] Erreur SQL logging: {e}")

def record_start_time():
    log_to_db("Démarrage du scanner (Mode Robuste)", "SYSTEM")
    try:
        conn = get_db_connection()
        cursor = conn.cursor()
        sql = "INSERT INTO system_stats (name, value) VALUES ('last_launch', NOW()) ON DUPLICATE KEY UPDATE value = NOW()"
        cursor.execute(sql)
        conn.commit()
        cursor.close()
        conn.close()
    except Exception as e:
        print(f"[!] Impossible d'enregistrer l'heure de lancement: {e}")

def fetch_lbc_ads(client, search):
    """Récupère les annonces en utilisant notre client robuste"""
    url = "https://api.leboncoin.fr/finder/search"
    
    # Payload identique à avant, il était correct
    payload = {
        "filters": {
            "enums": {"ad_type": ["offer"]},
            "ranges": {}
        },
        "limit": 15,
        "sort_by": "time",
        "sort_order": "desc",
        "listing_source": "direct-search"
    }

    if search['zipcodes']:
        payload["filters"]["location"] = {
            "city_zipcodes": [{"zipcode": z.strip()} for z in search['zipcodes'].split(',')]
        }

    cat_id = str(search['category']) if search['category'] else "9"
    if cat_id != "0":
        payload["filters"]["category"] = {"id": cat_id}
    
    is_donation = search.get('is_donation') and str(search['is_donation']) in ['1', 'True', 'true']
    if is_donation:
        payload["filters"]["enums"]["donation"] = ["1"]
    else:
        if search['price_min']:
            payload["filters"]["ranges"]["price"] = payload["filters"]["ranges"].get("price", {})
            payload["filters"]["ranges"]["price"]["min"] = int(search['price_min'])
        if search['price_max']:
            payload["filters"]["ranges"]["price"] = payload["filters"]["ranges"].get("price", {})
            payload["filters"]["ranges"]["price"]["max"] = int(search['price_max'])
    
    if search['keywords']:
        payload["filters"]["keywords"] = {"text": search['keywords']}

    # Envoi via notre client intelligent
    response = client.post(url, payload)
    
    if response and response.status_code == 200:
        return response.json().get('ads', [])
    return []

def save_ad(cursor, search_id, ad):
    try:
        lbc_id = str(ad.get('list_id'))
        title = ad.get('subject')
        
        if not lbc_id or lbc_id == 'None' or not title:
            return

        price = ad.get('price', [0])[0] if ad.get('price') else 0
        url = ad.get('url')
        location = ad.get('location', {}).get('city', 'Inconnue')
        category_id = ad.get('category_id')
        
        surface = 0
        for attr in ad.get('attributes', []):
            if attr.get('key') == 'square':
                surface = attr.get('value')
                break
        
        image_url = ad.get('images', {}).get('thumb_url')
        if image_url and 'rule=' in image_url:
            image_url = image_url.split('?')[0] + '?rule=ad-small'
        elif image_url:
            image_url += '?rule=ad-small'

        sql = """INSERT INTO ads (search_id, lbc_id, title, price, surface, location, image_url, url, category_id) 
                 VALUES (%s, %s, %s, %s, %s, %s, %s, %s, %s)
                 ON DUPLICATE KEY UPDATE 
                 is_seen = IF(price > VALUES(price), 0, is_seen),
                 title = VALUES(title),
                 price = VALUES(price),
                 category_id = VALUES(category_id)"""
        cursor.execute(sql, (search_id, lbc_id, title, price, surface, location, image_url, url, category_id))
    except Exception as e:
        print(f"[!] Erreur save_ad: {e}")

def process_searches():
    print("[DEBUG] Début process_searches")
    
    # Création d'une instance client unique pour ce cycle
    # Elle gérera ses propres cookies et retries
    client = LbcClient()
    
    conn = get_db_connection()
    cursor = conn.cursor(dictionary=True)
    
    cursor.execute("SELECT * FROM searches WHERE is_active = 1")
    searches = cursor.fetchall()
    
    for s in searches:
        log_to_db(f"Scan: {s['name']}")
        
        ads = fetch_lbc_ads(client, s)
        print(f"[DEBUG] {len(ads)} annonces récupérées pour {s['name']}")
        
        excluded_ids = []
        if s.get('excluded_categories') and s['excluded_categories']:
            excluded_ids = [cid.strip() for cid in s['excluded_categories'].split(',')]
        
        new_count = 0
        for ad in ads:
            cat_id = str(ad.get('category_id'))
            if cat_id in excluded_ids:
                continue

            save_ad(cursor, s['id'], ad)
            if cursor.rowcount > 0:
                new_count += 1
        
        if new_count > 0:
            log_to_db(f"{new_count} news: {s['name']}", "SUCCESS")
            
        cursor.execute("UPDATE searches SET last_checked = NOW() WHERE id = %s", (s['id'],))
        conn.commit()
        
        # Pause prudente entre chaque recherche
        time.sleep(random.uniform(3, 7))
        
    cursor.close()
    conn.close()

if __name__ == "__main__":
    record_start_time()
    time.sleep(2)
    
    print("[SYSTEM] Scanner en attente (Cycle 45-75min, Pause 00h-07h)...")
    
    # Premier intervalle court pour lancer le scan au démarrage (sauf si pause nuit)
    next_interval = 15
    last_auto_scan = time.time()

    while True:
        try:
            current_time = time.time()
            
            # --- 1. Vérification Manuelle (Token DB) ---
            conn = get_db_connection()
            cursor = conn.cursor()
            cursor.execute("SELECT value FROM system_stats WHERE name = 'scan_request'")
            row = cursor.fetchone()
            
            should_scan_manual = False
            if row and row[0] == 'pending':
                should_scan_manual = True
                cursor.execute("UPDATE system_stats SET value = 'processing' WHERE name = 'scan_request'")
                conn.commit()
            
            cursor.close()
            conn.close()

            # --- 2. Vérification Automatique (Cycle variable & Pause nuit) ---
            should_scan_auto = False
            
            # Gestion de la pause nocturne paramétrable
            pause_start = int(os.getenv('NIGHT_PAUSE_START', 0))
            pause_end = int(os.getenv('NIGHT_PAUSE_END', 7))
            
            now_dt = datetime.now()
            
            if pause_start < pause_end:
                is_night_pause = (pause_start <= now_dt.hour < pause_end)
            else: # Cas où la pause traverse minuit (ex: 22h à 06h)
                is_night_pause = (now_dt.hour >= pause_start or now_dt.hour < pause_end)

            if not is_night_pause and (current_time - last_auto_scan) > next_interval:
                should_scan_auto = True

            # --- 3. Exécution du Scan ---
            if should_scan_manual or should_scan_auto:
                trigger_type = "MANUEL" if should_scan_manual else f"AUTO ({int(next_interval/60)}min)"
                log_to_db(f"Lancement scan [{trigger_type}]", "SYSTEM")
                
                process_searches()
                
                log_to_db("Cycle terminé", "SYSTEM")
                
                # Reset du timer et calcul du prochain intervalle aléatoire (45 - 75 min)
                last_auto_scan = time.time()
                next_interval = random.randint(45 * 60, 75 * 60)
                
                log_to_db(f"Prochain scan auto dans {int(next_interval/60)} minutes", "SYSTEM")

                # Nettoyage du token si manuel
                if should_scan_manual:
                    conn = get_db_connection()
                    cursor = conn.cursor()
                    cursor.execute("DELETE FROM system_stats WHERE name = 'scan_request'")
                    conn.commit()
                    cursor.close()
                    conn.close()
            
            time.sleep(5)
            
        except Exception as e:
            log_to_db(f"Erreur boucle principale: {e}", "ERROR")
            time.sleep(15)
