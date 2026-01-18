# LBC Watch

Système de remontée d'annonces immobilières Leboncoin, inspiré de `lbc-finder`.

## Architecture
- **PHP / MySQL / Twig**: Dashboard de consultation et gestion des recherches.
- **Python**: Script de polling (basé sur l'approche de `lbc-finder`) pour récupérer les annonces en temps réel.

## Fonctionnalités
- Enregistrement de plusieurs flux de recherche.
- Check à intervalles réguliers.
- Déduplication des annonces.
- Interface moderne et réactive.

## Commandes Serveur (VPS)

**Connexion SSH :**
```bash
ssh lbcno6602@217.182.253.210
```

**Se placer dans le bon dossier :**
```bash
cd public_html
```

**Redémarrer le scanner :**

1. Trouver l'ID du processus (PID) :
```bash
ps aux | grep scanner.py
```

2. Arrêter le processus (remplacer `<PID>` par le numéro) :
```bash
pkill -f scanner.py
```

3. Relancer en arrière-plan :
```bash
nohup python3 scanner.py > scanner.log 2>&1 &
```

---
Lien dépôt : https://github.com/etienne-hd/lbc