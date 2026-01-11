# Cric Scorer (PHP + SQLite) — VPS-ready

A lightweight cricket scoring web app you can host on any VPS with PHP.
It supports:
- Tournaments
- Teams (unlimited per tournament)
- Matches
- Live scoring (ball-by-ball)
- Overs/balls (legal vs illegal)
- Run rate
- Undo last ball

> MVP note: This starter focuses on **innings 1** scoring (a clean base). You can extend to 2-innings + target easily.

---

## 1) Requirements (VPS)
- PHP 8.1+ recommended (works on PHP 7.4+ too)
- Extensions:
  - `pdo_sqlite`
  - `sqlite3`
- Web server: Nginx or Apache

---

## 2) Install / Deploy
### Option A — Nginx + PHP-FPM (Ubuntu/Debian)
1. Install:
   - `sudo apt update`
   - `sudo apt install nginx php-fpm php-sqlite3 -y`

2. Upload this folder to: `/var/www/cric-scorer`

3. Nginx site example:
```
server {
  listen 80;
  server_name YOUR_DOMAIN_OR_IP;

  root /var/www/cric-scorer;
  index index.php;

  location / {
    try_files $uri $uri/ /index.php?$query_string;
  }

  location ~ \.php$ {
    include snippets/fastcgi-php.conf;
    fastcgi_pass unix:/run/php/php8.2-fpm.sock; # change version if needed
  }
}
```

4. Reload:
- `sudo nginx -t && sudo systemctl reload nginx`

5. Permissions (SQLite file will be created in the project folder):
- `sudo chown -R www-data:www-data /var/www/cric-scorer`

6. Initialize DB (ONE TIME):
- Open: `http://YOUR_DOMAIN_OR_IP/init_db.php`
- Then delete `init_db.php` for safety.

---

### Option B — Apache (Ubuntu/Debian)
1. Install:
- `sudo apt update`
- `sudo apt install apache2 php libapache2-mod-php php-sqlite3 -y`

2. Copy to:
- `/var/www/html/cric-scorer`

3. Initialize DB:
- `http://YOUR_DOMAIN_OR_IP/cric-scorer/init_db.php`
- Then delete `init_db.php`.

---

## 3) Usage
1. Open `index.php`
2. Create tournament
3. Add teams (unlimited — supports bulk add)
4. Create match
5. Start live scoring

---

## 4) Data / DB
- SQLite database file is stored at: `cric.db` in the project root.

---

## 5) Security Notes
- Simple login is included.
  - Default credentials: **admin / admin123**
  - Change by setting environment variables before initializing DB:
    - `ADMIN_USER`
    - `ADMIN_PASS`
- Delete `init_db.php` after first run.

---

## 6) Next upgrades (ask and I'll implement)
- 2 innings + target + required RR
- Batsman/Bowler tracking + full scorecard
- Points table + NRR UI
- Export match as PDF/JSON


## Tournament formats
- Round robin: generates all pairings.
- Knockout: generates first-round bracket (simple seeding).

## Points system
Configurable per tournament at creation: Win/Tie/No Result/Loss.


## Auto innings end + Super Over
- Innings auto-ends when: **10 wickets**, **overs limit reached**, or (in chase) **target reached**.
- If match is tied after Innings 2, app will ask to start a **Super Over**.
- Super Over uses **1 over per side** (innings 3 & 4). If super over also ties, match stays Tie.

### DB change
Delete `cric.db` and re-run `init_db.php`.

### Ball counting note
If an innings ends exactly at the overs limit (e.g., 20.0), the UI may switch immediately to the next innings. This can look like it ended “one ball early”. A popup now confirms the innings ended AFTER the last ball was counted.

## Update db
- docker cp cric-scorer:/var/www/html/cric.db ./data/cric_backup.db
- docker cp ./data/cric_backup.db cric-scorer:/var/www/html/cric.db
- docker exec cric-scorer chown www-data:www-data /var/www/html/cric.db
- docker exec cric-scorer chown -R www-data:www-data /var/www/html/

## Update to Git
- git add .
- git commit -m "New update"
- git push
