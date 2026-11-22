# Wollo University Administrative & Document Management System (WU-DMS)
## Institutional Production Deployment & Operations Guide

- **Target Institution**: Wollo University (Dessie & Kombolcha Campuses)
- **Standard Baseline**: Ubuntu 24.04 LTS (Noble Numbat) / RHEL 9 Enterprise Linux
- **Stack**: Nginx 1.26+, PHP 8.4+ FPM, MySQL 8.4 LTS, Node.js 22 LTS, Vite / Vue 3 SPA
- **Status**: Production Deployment Architecture Specification

---

## 1. Operating System & Server Prerequisites

### 1.1 Compute & Storage Recommendations
- **Application & API Server (CoI Pilot / Production)**:
  - CPU: Minimum 4 vCPUs (Intel Xeon / AMD EPYC)
  - RAM: Minimum 8 GB (16 GB recommended for OCR / full text indexing)
  - Disk: 100 GB NVMe for OS & Application; Dedicated 500 GB+ Volume mounted at `/var/wu-dms/storage` for document binary objects and backups.
- **Database Server (MySQL 8.4 LTS)**:
  - CPU: 4 vCPUs
  - RAM: 16 GB (allocate 10 GB to `innodb_buffer_pool_size`)
  - Disk: 200 GB+ Enterprise SSD / NVMe in RAID 10.

### 1.2 Base Package Installation (Ubuntu 24.04 LTS)
```bash
# Update repository index
sudo apt update && sudo apt upgrade -y

# Add Ondřej Surý PHP PPA for PHP 8.4
sudo apt install -y software-properties-common curl git unzip ufw fail2ban
sudo add-apt-repository -y ppa:ondrej/php
sudo apt update

# Install PHP 8.4 runtime and enterprise extensions
sudo apt install -y php8.4-fpm php8.4-cli php8.4-common php8.4-mysql php8.4-zip \
    php8.4-gd php8.4-mbstring php8.4-curl php8.4-xml php8.4-bcmath php8.4-intl \
    php8.4-readline php8.4-opcache php8.4-redis

# Install Nginx and MySQL 8.4 Client
sudo apt install -y nginx mysql-client

# Install Composer 2.7+
curl -sS https://getcomposer.org/installer | sudo php -- --install-dir=/usr/local/bin --filename=composer

# Install Node.js 22.x LTS and npm
curl -fsSL https://deb.nodesource.com/setup_22.x | sudo -E bash -
sudo apt install -y nodejs
```

---

## 2. Directory Structure & Permissions (Zero-Downtime Releases)

Deploy the application following a standard atomic release layout:

```
/var/www/wu-dms/
├── releases/
│   ├── 20261115000000/
│   └── 20261115120000/
├── shared/
│   ├── .env
│   ├── storage/
│   │   ├── app/
│   │   │   ├── private/
│   │   │   ├── documents/
│   │   │   └── backups/
│   │   ├── framework/
│   │   │   ├── cache/
│   │   │   ├── sessions/
│   │   │   └── views/
│   │   └── logs/
└── current -> /var/www/wu-dms/releases/20261115120000
```

### 2.1 Permission Initialization
```bash
# Create shared directory hierarchy
sudo mkdir -p /var/www/wu-dms/shared/storage/app/documents
sudo mkdir -p /var/www/wu-dms/shared/storage/app/backups
sudo mkdir -p /var/www/wu-dms/shared/storage/framework/{cache,sessions,views}
sudo mkdir -p /var/www/wu-dms/shared/storage/logs

# Set ownership to web user
sudo chown -R www-data:www-data /var/www/wu-dms
sudo chmod -R 775 /var/www/wu-dms/shared/storage
sudo chmod 700 /var/www/wu-dms/shared/storage/app/backups
```

---

## 3. Nginx Web Server Configuration

Create `/etc/nginx/sites-available/wu-dms.conf`:

```nginx
# Rate Limiting Zones for Authentication Protection
limit_req_zone $binary_remote_addr zone=wu_dms_auth:10m rate=5r/m;
limit_req_zone $binary_remote_addr zone=wu_dms_api:10m rate=60r/m;

# HTTP -> HTTPS Redirect
server {
    listen 80;
    listen [::]:80;
    server_name dms.wu.edu.et;
    return 301 https://$host$request_uri;
}

# HTTPS Primary Institutional Virtual Host
server {
    listen 443 ssl http2;
    listen [::]:443 ssl http2;
    server_name dms.wu.edu.et;

    root /var/www/wu-dms/current/server/public;
    index index.php index.html;

    # SSL / TLS 1.3 Hardened Institutional Profile
    ssl_certificate /etc/ssl/certs/wu_dms_chain.crt;
    ssl_certificate_key /etc/ssl/private/wu_dms.key;
    ssl_protocols TLSv1.2 TLSv1.3;
    ssl_ciphers ECDHE-ECDSA-AES128-GCM-SHA256:ECDHE-RSA-AES128-GCM-SHA256:ECDHE-ECDSA-AES256-GCM-SHA384:ECDHE-RSA-AES256-GCM-SHA384;
    ssl_prefer_server_ciphers on;
    ssl_session_cache shared:SSL:20m;
    ssl_session_timeout 1d;
    ssl_session_tickets off;

    # Institutional Security Headers
    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header X-XSS-Protection "1; mode=block" always;
    add_header Referrer-Policy "strict-origin-when-cross-origin" always;
    add_header Strict-Transport-Security "max-age=31536000; includeSubDomains; preload" always;
    add_header Content-Security-Policy "default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline'; img-src 'self' data: blob:; font-src 'self' data:; connect-src 'self'; frame-ancestors 'self';" always;

    # Upload Limits (50 MB Max Document Upload)
    client_max_body_size 50M;
    client_body_timeout 120s;
    client_header_timeout 30s;

    # Gzip Compression
    gzip on;
    gzip_vary on;
    gzip_proxied any;
    gzip_comp_level 6;
    gzip_types text/plain text/css text/xml application/json application/javascript application/xml+rss application/atom+xml image/svg+xml;

    # Health Check Endpoint (Unthrottled for L4/L7 Load Balancers)
    location = /api/v1/health {
        try_files $uri $uri/ /index.php?$query_string;
        access_log off;
    }

    # Strict Rate-Limiting for Sensitive Authentication Handlers
    location ~ ^/api/v1/auth/(login|forgot-password|reset-password) {
        limit_req zone=wu_dms_auth burst=5 nodelay;
        try_files $uri $uri/ /index.php?$query_string;
    }

    # General API Routes
    location ^~ /api/ {
        limit_req zone=wu_dms_api burst=20 nodelay;
        try_files $uri $uri/ /index.php?$query_string;
    }

    # Static Assets (Vite Dist Bundles)
    location ~* \.(js|css|png|jpg|jpeg|gif|ico|svg|woff|woff2|ttf|eot)$ {
        expires 1y;
        add_header Cache-Control "public, immutable";
        access_log off;
    }

    # Primary Routing
    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    # PHP-FPM FastCGI Handler
    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/run/php/php8.4-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
        fastcgi_read_timeout 180;
        fastcgi_buffer_size 128k;
        fastcgi_buffers 4 256k;
        fastcgi_busy_buffers_size 256k;
    }

    # Deny Hidden Files (.env, .git)
    location ~ /\.(?!well-known).* {
        deny all;
    }
}
```

Enable virtual host:
```bash
sudo ln -s /etc/nginx/sites-available/wu-dms.conf /etc/nginx/sites-enabled/
sudo nginx -t && sudo systemctl reload nginx
```

---

## 4. PHP 8.4 FPM Optimization

Edit `/etc/php/8.4/fpm/pool.d/www.conf`:

```ini
[www]
user = www-data
group = www-data
listen = /run/php/php8.4-fpm.sock
listen.owner = www-data
listen.group = www-data
listen.mode = 0660

pm = dynamic
pm.max_children = 50
pm.start_servers = 10
pm.min_spare_servers = 5
pm.max_spare_servers = 15
pm.max_requests = 1000

request_terminate_timeout = 180s
rlimit_files = 65535
```

Edit `/etc/php/8.4/fpm/php.ini`:

```ini
upload_max_filesize = 50M
post_max_size = 55M
memory_limit = 512M
max_execution_time = 180
date.timezone = Africa/Addis_Ababa

; OPcache High-Performance Tuning
opcache.enable = 1
opcache.memory_consumption = 256
opcache.interned_strings_buffer = 16
opcache.max_accelerated_files = 20000
opcache.validate_timestamps = 0
opcache.save_comments = 1
opcache.fast_shutdown = 1

; JIT Compiler
opcache.jit = 1255
opcache.jit_buffer_size = 64M
```

Restart PHP-FPM:
```bash
sudo systemctl restart php8.4-fpm
```

---

## 5. MySQL 8.4 Production Tuning

Add to `/etc/mysql/mysql.conf.d/wu_dms.cnf`:

```ini
[mysqld]
default_storage_engine = InnoDB
character_set_server = utf8mb4
collation_server = utf8mb4_unicode_ci

# Memory Allocation (For 16GB Dedicated Database Host)
innodb_buffer_pool_size = 10G
innodb_buffer_pool_instances = 8
innodb_log_buffer_size = 64M
innodb_log_file_size = 1G
innodb_flush_log_at_trx_commit = 1
innodb_flush_method = O_DIRECT

# Connection Scaling
max_connections = 300
thread_cache_size = 50
table_open_cache = 4000

# Binary Logging & Point-In-Time Recovery
log_bin = /var/log/mysql/mysql-bin.log
binlog_format = ROW
binlog_expire_logs_seconds = 604800 # 7 Days Retention
server_id = 1
```

Restart MySQL:
```bash
sudo systemctl restart mysql
```

### 5.1 Database User & Grant Configuration

**Critical Security Requirement: Audit Trail Append-Only Enforcement**

The application database user must be restricted to prevent modification or deletion of audit records.

Create the application database user with limited grants:

```sql
-- Create dedicated application database user
CREATE USER 'wu_dms_app'@'%' IDENTIFIED BY 'STRONG_GENERATED_PASSWORD_HERE';

-- Grant standard privileges for all tables
GRANT SELECT, INSERT, UPDATE, DELETE, CREATE, DROP, INDEX, ALTER, CREATE TEMPORARY TABLES, LOCK TABLES 
ON wu_dms.* TO 'wu_dms_app'@'%';

-- CRITICAL: Revoke UPDATE and DELETE on audit_events table to enforce append-only integrity
REVOKE UPDATE, DELETE ON wu_dms.audit_events FROM 'wu_dms_app'@'%';

-- Grant only SELECT and INSERT on audit_events (append-only)
GRANT SELECT, INSERT ON wu_dms.audit_events TO 'wu_dms_app'@'%';

-- Apply privileges
FLUSH PRIVILEGES;
```

**Verification:**

After configuring grants, verify the application user has the correct restricted permissions:

```sql
-- Show grants for application user
SHOW GRANTS FOR 'wu_dms_app'@'%';

-- Expected output should include:
-- GRANT SELECT, INSERT ON `wu_dms`.`audit_events` TO `wu_dms_app`@`%`
-- And NOT include UPDATE or DELETE on audit_events
```

**Rationale:**

The `audit_events` table serves as the system's tamper-proof audit trail. Three layers of protection enforce immutability:

1. **Application Layer**: Laravel Eloquent model events prevent UPDATE/DELETE operations
2. **Database Layer**: MySQL triggers reject UPDATE/DELETE statements with integrity violation errors
3. **Privilege Layer**: Application database user lacks UPDATE/DELETE grants on audit_events

This defense-in-depth approach ensures audit integrity cannot be compromised through:
- Direct SQL injection bypassing ORM protections
- Compromised application code executing raw queries
- Administrative mistakes or unauthorized database access

Only the DBA user retains UPDATE/DELETE privileges for emergency recovery scenarios.

**Administrative Access:**

For legitimate audit record corrections (e.g., GDPR data deletion, court orders), use the DBA account:

```sql
-- Emergency audit correction (requires senior authorization)
-- Document the operation in institutional change management system
DELETE FROM audit_events WHERE id = <specific_record_id>;
```

---

## 6. Systemd Background Daemon Services

### 6.1 Multi-Process Asynchronous Queue Worker

Create `/etc/systemd/system/wu-dms-queue.service`:

```ini
[Unit]
Description=Wollo University DMS Queue Worker Cluster
After=network.target mysql.service

[Service]
Type=simple
User=www-data
Group=www-data
Restart=always
RestartSec=5
WorkingDirectory=/var/www/wu-dms/current/server
ExecStart=/usr/bin/php8.4 artisan queue:work database --sleep=3 --tries=3 --max-time=3600 --timeout=120
StandardOutput=append:/var/www/wu-dms/shared/storage/logs/queue-worker.log
StandardError=append:/var/www/wu-dms/shared/storage/logs/queue-worker.log

[Install]
WantedBy=multi-user.target
```

Enable and start queue:
```bash
sudo systemctl daemon-reload
sudo systemctl enable --now wu-dms-queue
```

### 6.2 Institutional Cron Scheduler

Configure the Laravel scheduler in root or `www-data` crontab:

```bash
# Edit crontab
sudo crontab -u www-data -e

# Add single cron entry running every minute
* * * * * cd /var/www/wu-dms/current/server && /usr/bin/php8.4 artisan schedule:run >> /dev/null 2>&1
```

The scheduler automatically fires:
- `dms:check-due-dates`: Every minute (without overlapping)
- `dms:cleanup-import-uploads`: Daily at 02:30 EAT
- `dms:backup`: Daily at 02:00 EAT (SHA-256 verified)
- `dms:backup-cleanup`: Daily at 03:00 EAT (30-day retention lock)
- Scheduler Heartbeat: Every minute into cache store

---

## 7. Deployment & Release Pipeline Script

Create `/var/www/wu-dms/deploy.sh`:

```bash
#!/usr/bin/env bash
set -e

RELEASE_ID=$(date +%Y%m%d%H%M%S)
BASE_DIR="/var/www/wu-dms"
RELEASE_DIR="${BASE_DIR}/releases/${RELEASE_ID}"
SHARED_DIR="${BASE_DIR}/shared"

echo "==> Deploying WU-DMS Release: ${RELEASE_ID}"

# 1. Clone / Checkout to Release Directory
mkdir -p "${RELEASE_DIR}"
git clone --depth 1 git@github.com:wollo-university/wu-dms.git "${RELEASE_DIR}"

# 2. Link Shared Storage & Configuration
rm -rf "${RELEASE_DIR}/server/storage"
ln -sfn "${SHARED_DIR}/storage" "${RELEASE_DIR}/server/storage"
ln -sfn "${SHARED_DIR}/.env" "${RELEASE_DIR}/server/.env"

# 3. Build Backend Dependencies & Optimize
cd "${RELEASE_DIR}/server"
composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction
php8.4 artisan config:cache
php8.4 artisan route:cache
php8.4 artisan view:cache
php8.4 artisan migrate --force

# 4. Build Frontend Single Page Application
cd "${RELEASE_DIR}/client"
npm ci
npm run build
cp -r dist/* "${RELEASE_DIR}/server/public/"

# 5. Atomic Symlink Swap
ln -sfn "${RELEASE_DIR}" "${BASE_DIR}/current"

# 6. Restart Queue Workers & Reload PHP-FPM
sudo systemctl restart wu-dms-queue
sudo systemctl reload php8.4-fpm

# 7. Purge Old Releases (Keep Last 5)
cd "${BASE_DIR}/releases"
ls -dt */ | tail -n +6 | xargs -I {} rm -rf {}

echo "==> WU-DMS Release ${RELEASE_ID} Deployed Successfully."
```

---

## 8. Rollback Strategy

In case an unexpected critical bug or regression occurs during deployment:

```bash
#!/usr/bin/env bash
# Quick Rollback to Previous Release
BASE_DIR="/var/www/wu-dms"
PREVIOUS_RELEASE=$(ls -dt ${BASE_DIR}/releases/*/ | sed -n '2p')

if [ -n "$PREVIOUS_RELEASE" ]; then
    echo "==> Rolling back to: ${PREVIOUS_RELEASE}"
    ln -sfn "${PREVIOUS_RELEASE}" "${BASE_DIR}/current"
    sudo systemctl restart wu-dms-queue
    sudo systemctl reload php8.4-fpm
    echo "==> Rollback complete."
else
    echo "==> No previous release found to rollback to."
    exit 1
fi
```

---

## 9. Disaster Recovery Drill Schedule & Integrity Verification

The institution mandates a daily automated DR drill and weekly isolated verification:
- Automated daily backup occurs at 02:00 EAT.
- Weekly automated or on-demand recovery drill executes via `php artisan dms:dr-drill`.
- Restoration is executed against the isolated database `wu_dms_recovery_test` without touching the live database.
- Integrity compliance: Target RTO < 30 minutes, SHA-256 byte mutation: 0%.
