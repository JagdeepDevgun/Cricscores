# CricScore - Cricket Tournament & Live Scoring App

**CricScore** is a self-hosted, web-based application designed to manage local cricket tournaments. It handles everything from team management and fixture generation to ball-by-ball live scoring and automatic statistics generation.

Built with **PHP** and **SQLite**, it runs easily in a Docker container and functions as a Progressive Web App (PWA) on mobile devices.

## 🌟 Features

* **Tournament Management:** Organize multiple tournaments, manage points tables, and generate fixtures automatically.
* **Live Scoring:** Mobile-friendly interface for ball-by-ball scoring (runs, wickets, extras, undo support).
* **Match Logic:** Handles complex scenarios like Super Overs, DLS (Duckworth-Lewis-Stern) adjustments, and "Man of the Match" selection.
* **Statistics:** Automatic calculation of player stats (Runs, Strike Rate, Wickets, Economy) and team standings.
* **Team & Player Management:** detailed profiles, regular player tracking, and team editing.
* **Offline Capable:** Installs as a PWA for faster access and "app-like" feel on iOS/Android.
* **Database Tools:** Built-in features for database backup, optimization, and migration.

---

## 📋 Prerequisites

* **Docker** and **Docker Compose** installed on your machine.
* (Optional) A reverse proxy (like Nginx) for SSL if deploying to the web.

---

## 🚀 Quick Start (Docker)

### 1. Project Setup

Ensure your project folder structure looks like this:

```text
/cricscore-app
  ├── api/
  ├── assets/
  ├── data/          <-- Database lives here
  ├── pages/
  ├── docker-compose.yml
  ├── Dockerfile
  ├── index.php
  ├── init_db.php
  └── ... (other files)

```

### 2. Build and Run

Open your terminal in the project root and run:

```bash
docker compose up -d --build

```

### 3. Permissions (Important)

SQLite requires write permissions for the `data` folder so the web server can save scores. Run these commands:

```bash
# Replace 'cricscore_container_name' with the actual container name from docker-compose
# (You can check it via 'docker ps')

docker cp ./data/cric_backup_2026-01-08_04-52.db cric-scorer:/var/www/html/cric.db
docker exec -it cricscore_web chown -R www-data:www-data /var/www/html/data
docker exec -it cricscore_web chmod -R 775 /var/www/html/data

```

### 4. Database Initialization

1. Open your browser and navigate to: `http://localhost:8080/init_db.php` (Check your port in `docker-compose.yml`).
2. This will create the necessary tables (`tournaments`, `matches`, `balls`, `players`, etc.).
3. **Security Note:** Once initialized, you should delete or rename `init_db.php` to prevent accidental resets.

---

## ⚙️ Configuration

The application settings are primarily handled via environment variables in `docker-compose.yml`.

| Variable | Description |
| --- | --- |
| `PHP_TZ` | Timezone for match timestamps (e.g., `Asia/Kolkata`). |
| `SQLITE_PATH` | Path to the DB file (Default: `/var/www/html/data/cricket.db`). |

To change the timezone, edit `docker-compose.yml`:

```yaml
environment:
  - PHP_TZ=America/New_York

```

---

## 📖 Usage Guide

### 1. Starting a Tournament

* Navigate to the **Tournaments** tab.
* Create a new tournament (e.g., "Winter Cup 2026").
* Add **Teams** to the tournament.
* Use the **Fixtures** generator to automatically schedule matches between teams.

### 2. Scoring a Match

* Go to the **Matches** tab and click on a scheduled match.
* **Toss:** Select who won the toss and their decision.
* **Playing XI:** Select players for both teams.
* **Scoring Interface:**
* Tap runs (0, 1, 2, 3, 4, 6).
* Mark extras (Wide, No Ball).
* Record wickets (Bowled, Catch, Run-out).
* Use the **Undo** button if you make a mistake.



### 3. Reviewing Stats

* **Points Table:** Automatically updates after every match result.
* **Player Stats:** Click on a player's name to see their career runs, high scores, and bowling figures.
* **Match Summary:** View the full scorecard and ball-by-ball commentary for completed matches.

---

## 🛠 Troubleshooting

**"Unable to open database file"**

* This is almost always a permission issue. Ensure you ran the `chown` and `chmod` commands listed in step 3 of the Quick Start.

**Scores not saving**

* Check if your disk is full or if the `data/` directory is read-only.

**PWA not installing**

* Ensure you are serving the app over **HTTPS** (or `localhost`). Service Workers (required for PWA) do not work over insecure HTTP connections on remote networks.

---

## 📂 Backup & Maintenance

* **Backup:** The app has a built-in backup API (`api/backup.php`), or you can simply copy the `.db` file from the `data/` folder.
* **Optimize:** Occasional running of `api/optimize_db.php` can help keep the SQLite database fast by running a `VACUUM` command.
