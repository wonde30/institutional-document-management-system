# Production Deployment Runbook

**System:** Wollo University Administrative and Document Management System (WU-DMS)  
**Version:** 1.0  
**Last Updated:** 2024-11-22

This runbook provides step-by-step instructions for deploying WU-DMS to a production server. A system administrator should be able to install and configure the entire system by following this guide alone.

---

## Table of Contents

1. [Prerequisites](#1-prerequisites)
2. [System User Setup](#2-system-user-setup)
3. [Database Installation & Configuration](#3-database-installation--configuration)
4. [Application Installation](#4-application-installation)
5. [Web Server Configuration](#5-web-server-configuration)
6. [Process Management](#6-process-management)
7. [Scheduler Setup](#7-scheduler-setup)
8. [Frontend Build & Deployment](#8-frontend-build--deployment)
9. [Security Hardening](#9-security-hardening)
10. [Monitoring & Logging](#10-monitoring--logging)
11. [Backup Strategy](#11-backup-strategy)
12. [Post-Deployment Verification](#12-post-deployment-verification)
13. [Troubleshooting](#13-troubleshooting)
14. [Rollback Procedure](#14-rollback-procedure)

---

## 1. Prerequisites

### 1.1 Server Requirements

**Recommended:** Ubuntu 22.04 LTS or RHEL 9  
**Minimum Specifications:**
- CPU: 4 cores (8 cores recommended for production)
- RAM: 8 GB (16 GB recommended)
- Storage: 100 GB SSD (storage grows with document uploads)
- Network: Static IP address with domain name

### 1.2 Required Software

#### PHP 8.4+
```bash
# Ubuntu 22.04
sudo add-apt-repository ppa:ondrej/php
sudo apt update
sudo apt install -y php8.4-cli php8.4-fpm php8.4-mysql php8.4-mbstring \
    php8.4-xml php8.4-curl php8.4-zip php8.4-gd php8.4-intl \
    php8.4-bcmath php8.4-soap php8.4-opcache php8.4-redis
```

**Required PHP Extensions:**
- OpenSSL (for cryptographic operations and Sanctum)
- PDO + PDO_MySQL (database connectivity)
- Mbstring (UTF-8 string handling)
- XML (configuration parsing)
- Curl (HTTP client for external services)
- Zip (import/export operations)
- GD or Imagick (image processing)
- Intl (internationalization)
- BCMath (arbitrary precision mathematics)
- Opcache (performance optimization)

#### Composer 2.x
```bash
php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');"
php composer-setup.php --install-dir=/usr/local/bin --filename=composer
php -r "unlink('composer-setup.php');"
composer --version  # Should show 2.x
```

#### MySQL 8.4+
```bash
# Ubuntu 22.04
wget https://dev.mysql.com/get/mysql-apt-config_0.8.29-1_all.deb
sudo dpkg -i mysql-apt-config_0.8.29-1_all.deb
sudo apt update
sudo apt install -y mysql-server
mysql --version  # Should show 8.4.x
```

#### Node.js 20+ and npm
```bash
curl -fsSL https://deb.nodesource.com/setup_20.x | sudo -E bash -
sudo apt install -y nodejs
node --version  # Should show v20.x
npm --version   # Should show 10.x
```

#### Nginx (Recommended Web Server)
```bash
sudo apt install -y nginx
nginx -v  # Should show nginx/1.18 or higher
```

#### Supervisor (Process Manager)
```bash
sudo apt install -y supervisor
supervisorctl version
```

#### Git
```bash
sudo apt install -y git
```

### 1.3 SSL/TLS Certificate

**Production requires HTTPS.** Obtain a certificate from:
- Let's Encrypt (free, automated renewal)
- Commercial CA (Sectigo, DigiCert, etc.)
- Internal PKI (for institutional networks)

```bash
# Let's Encrypt with Certbot
sudo apt install -y certbot python3-certbot-nginx
sudo certbot certonly --nginx -d dms.wu.edu.et
```

### 1.4 Mail Server Configuration

Configure SMTP settings for:
- Account verification emails
- Password reset links
- Workflow notifications
- System alerts

**Recommended:** Use institutional mail server or service like Mailgun, SendGrid, or AWS SES.

---

## 2. System User Setup

### 2.1 Create Dedicated Application User

```bash
# Create user without shell access (security best practice)
sudo useradd -r -s /bin/false -d /var/www/wudms -m wudms

# Grant web server access to the application group
sudo usermod -a -G wudms www-data
```

### 2.2 Directory Structure

```bash
# Application root
sudo mkdir -p /var/www/wudms/{releases,shared,current}

# Shared directories (persist across deployments)
sudo mkdir -p /var/www/wudms/shared/{storage,logs,.env}

# Storage structure (Laravel convention)
sudo mkdir -p /var/www/wudms/shared/storage/{app,framework,logs}
sudo mkdir -p /var/www/wudms/shared/storage/app/{public,private,documents,imports,exports}
sudo mkdir -p /var/www/wudms/shared/storage/framework/{cache,sessions,views}

# Set ownership
sudo chown -R wudms:wudms /var/www/wudms
```

---

## 3. Database Installation & Configuration

### 3.1 MySQL Security Hardening

```bash
# Run security script
sudo mysql_secure_installation
# - Set root password
# - Remove anonymous users: YES
# - Disallow root login remotely: YES
# - Remove test database: YES
# - Reload privilege tables: YES
```

### 3.2 Create Database and User

```bash
sudo mysql -u root -p
```

```sql
-- Create database with correct character set and collation
CREATE DATABASE wollo_dms 
    CHARACTER SET utf8mb4 
    COLLATE utf8mb4_0900_as_cs;

-- Create application user with restricted privileges
CREATE USER 'wudms_app'@'localhost' IDENTIFIED BY '{{STRONG_PASSWORD}}';

-- Grant necessary privileges
GRANT SELECT, INSERT, UPDATE, DELETE, CREATE, DROP, INDEX, ALTER, 
      CREATE TEMPORARY TABLES, LOCK TABLES, EXECUTE, 
      CREATE VIEW, SHOW VIEW, CREATE ROUTINE, ALTER ROUTINE, TRIGGER
ON wollo_dms.* 
TO 'wudms_app'@'localhost';

-- **CRITICAL SECURITY:** Prevent audit trail tampering
-- The application account MUST NOT be able to UPDATE or DELETE audit_events
REVOKE UPDATE, DELETE ON wollo_dms.audit_events FROM 'wudms_app'@'localhost';

FLUSH PRIVILEGES;
EXIT;
```

**⚠️ WARNING:** The `REVOKE` statement above is critical for audit integrity. The audit trail is append-only by design. Verify this restriction after deployment:

```bash
# Test audit protection (should fail)
mysql -u wudms_app -p wollo_dms
```
```sql
UPDATE audit_events SET action = 'tampered' WHERE id = 1;
-- Expected: ERROR 1142 (42000): UPDATE command denied
```

### 3.3 Performance Tuning

Edit `/etc/mysql/mysql.conf.d/mysqld.cnf`:

```ini
[mysqld]
# InnoDB Configuration (WU-DMS uses InnoDB exclusively)
innodb_buffer_pool_size = 4G         # 50-70% of available RAM
innodb_log_file_size = 512M
innodb_flush_log_at_trx_commit = 2   # Balanced durability/performance
innodb_file_per_table = 1

# Query Cache (disabled in MySQL 8.0+, use application caching)
# Connections
max_connections = 200
max_allowed_packet = 64M            # For large document uploads

# Character Set
character-set-server = utf8mb4
collation-server = utf8mb4_0900_as_cs

# Logging
slow_query_log = 1
slow_query_log_file = /var/log/mysql/slow-query.log
long_query_time = 2                  # Log queries taking > 2 seconds
log_queries_not_using_indexes = 1
```

Restart MySQL:
```bash
sudo systemctl restart mysql
```

---

## 4. Application Installation

### 4.1 Clone Repository

```bash
# Switch to application user
sudo -u wudms -i

# Clone to versioned release directory
cd /var/www/wudms/releases
git clone https://github.com/WolloUniversity/DMS.git $(date +%Y%m%d%H%M%S)
cd $(ls -t | head -1)  # Enter latest release

# Checkout production tag/branch
git checkout v1.0.0  # Use actual version tag
```

### 4.2 Install PHP Dependencies

```bash
cd server
composer install --no-dev --optimize-autoloader --no-interaction

# Verify installation
composer diagnose
```

**Production Flags Explained:**
- `--no-dev`: Exclude development dependencies (PHPUnit, testing tools)
- `--optimize-autoloader`: Generate optimized class map for performance
- `--no-interaction`: Non-interactive mode for automation

### 4.3 Environment Configuration

```bash
# Copy environment template
cp .env.example /var/www/wudms/shared/.env
ln -sf /var/www/wudms/shared/.env .env

# Edit configuration
nano /var/www/wudms/shared/.env
```

**Production .env Template:**

```ini
# Application
APP_NAME="Wollo University DMS"
APP_ENV=production
APP_KEY=  # Generated in next step
APP_DEBUG=false  # NEVER true in production
APP_URL=https://dms.wu.edu.et
APP_TIMEZONE=Africa/Addis_Ababa
APP_LOCALE=en

# Database
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=wollo_dms
DB_USERNAME=wudms_app
DB_PASSWORD={{DB_PASSWORD}}

# Session & Cache
SESSION_DRIVER=database
SESSION_LIFETIME=120  # Minutes (configurable via system settings)
SESSION_DOMAIN=.wu.edu.et
SESSION_SECURE_COOKIE=true  # HTTPS only
CACHE_DRIVER=redis  # Or 'file' if Redis unavailable

# Redis (if used)
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379

# Mail
MAIL_MAILER=smtp
MAIL_HOST={{SMTP_HOST}}
MAIL_PORT=587
MAIL_USERNAME={{SMTP_USERNAME}}
MAIL_PASSWORD={{SMTP_PASSWORD}}
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=noreply@wu.edu.et
MAIL_FROM_NAME="${APP_NAME}"

# Filesystem
FILESYSTEM_DISK=local  # 'local' for server storage, 's3' for AWS
DOCUMENTS_DISK=private  # Document storage (NOT publicly accessible)

# AWS S3 (if used for scalable storage)
#AWS_ACCESS_KEY_ID=
#AWS_SECRET_ACCESS_KEY=
#AWS_DEFAULT_REGION=us-east-1
#AWS_BUCKET=wudms-documents
#AWS_USE_PATH_STYLE_ENDPOINT=false

# Logging
LOG_CHANNEL=daily  # Rotates logs daily
LOG_LEVEL=warning  # error, warning, info, debug
LOG_DEPRECATIONS_CHANNEL=null

# Queue (for background jobs)
QUEUE_CONNECTION=database  # Or 'redis' for better performance

# Sanctum (SPA Authentication)
SANCTUM_STATEFUL_DOMAINS=dms.wu.edu.et
SESSION_DOMAIN=.wu.edu.et

# Security
BCRYPT_ROUNDS=12  # Password hashing cost (higher = more secure, slower)

# Institutional Configuration
INSTITUTION_CODE=WU
INSTITUTION_NAME="Wollo University"
INSTITUTION_SHORT_NAME="WU"
```

### 4.4 Generate Application Key

```bash
php artisan key:generate --force
# Key written to .env file
```

**⚠️ CRITICAL:** Store this key securely. Changing it invalidates all encrypted data and user sessions.

### 4.5 Link Shared Storage

```bash
# Remove default storage directory
rm -rf storage

# Link to shared storage (persists across deployments)
ln -sf /var/www/wudms/shared/storage storage

# Create public storage symlink (for publicly accessible uploads)
php artisan storage:link
```

### 4.6 Run Database Migrations

```bash
# Review migrations first (optional but recommended)
php artisan migrate:status

# Run migrations (creates all tables)
php artisan migrate --force

# Verify tables created
php artisan db:show
```

### 4.7 Seed Initial Data

```bash
# Seed institutional baseline data
php artisan db:seed --class=InstitutionalCredentialSeeder --force

# Verify seeded data
php artisan tinker
>>> \App\Models\User::count();
>>> \App\Models\OrganizationUnit::count();
>>> \App\Models\Permission::count();
>>> exit
```

### 4.8 Optimize Laravel

```bash
# Cache configuration for performance
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Generate optimized event list
php artisan event:cache

# Clear any development artifacts
php artisan optimize:clear
```

---

## 5. Web Server Configuration

### 5.1 Nginx Configuration

Create `/etc/nginx/sites-available/wudms`:

```nginx
# Upstream PHP-FPM pool
upstream php-fpm {
    server unix:/run/php/php8.4-fpm.sock;
}

# Redirect HTTP to HTTPS
server {
    listen 80;
    listen [::]:80;
    server_name dms.wu.edu.et;

    return 301 https://$server_name$request_uri;
}

# HTTPS Server Block
server {
    listen 443 ssl http2;
    listen [::]:443 ssl http2;
    server_name dms.wu.edu.et;

    root /var/www/wudms/current/client/dist;  # Vue production build
    index index.html;

    # SSL Configuration
    ssl_certificate /etc/letsencrypt/live/dms.wu.edu.et/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/dms.wu.edu.et/privkey.pem;
    ssl_session_timeout 1d;
    ssl_session_cache shared:SSL:50m;
    ssl_session_tickets off;

    # Modern SSL configuration (Mozilla Intermediate)
    ssl_protocols TLSv1.2 TLSv1.3;
    ssl_ciphers 'ECDHE-RSA-AES128-GCM-SHA256:ECDHE-RSA-AES256-GCM-SHA384';
    ssl_prefer_server_ciphers on;

    # HSTS (enforce HTTPS for 1 year)
    add_header Strict-Transport-Security "max-age=31536000; includeSubDomains" always;

    # Security Headers
    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header X-XSS-Protection "1; mode=block" always;
    add_header Referrer-Policy "strict-origin-when-cross-origin" always;
    add_header Permissions-Policy "geolocation=(), microphone=(), camera=()" always;

    # Client upload size (documents can be up to 100MB by default)
    client_max_body_size 100M;
    client_body_buffer_size 128k;

    # Timeouts
    client_body_timeout 60s;
    client_header_timeout 60s;
    send_timeout 60s;

    # Logging
    access_log /var/log/nginx/wudms_access.log combined;
    error_log /var/log/nginx/wudms_error.log warn;

    # Gzip Compression
    gzip on;
    gzip_vary on;
    gzip_min_length 1024;
    gzip_types text/plain text/css text/xml text/javascript 
               application/json application/javascript application/xml+rss 
               application/rss+xml font/truetype font/opentype 
               application/vnd.ms-fontobject image/svg+xml;

    # Vue SPA (frontend)
    location / {
        try_files $uri $uri/ /index.html;
        
        # Cache static assets
        location ~* \.(js|css|png|jpg|jpeg|gif|ico|svg|woff|woff2|ttf|eot)$ {
            expires 1y;
            add_header Cache-Control "public, immutable";
            access_log off;
        }
    }

    # Laravel API Backend
    location /api {
        alias /var/www/wudms/current/server/public;
        try_files $uri $uri/ @api;

        location ~ \.php$ {
            fastcgi_pass php-fpm;
            fastcgi_index index.php;
            include fastcgi_params;
            fastcgi_param SCRIPT_FILENAME /var/www/wudms/current/server/public/index.php;
            fastcgi_param PATH_INFO $fastcgi_path_info;
            
            # Security: Prevent script execution in uploads
            fastcgi_param PHP_VALUE "upload_max_filesize=100M \n post_max_size=100M";
            
            # Timeouts for long-running operations
            fastcgi_read_timeout 300s;
            fastcgi_send_timeout 300s;
        }
    }

    location @api {
        rewrite /api/(.*)$ /api/index.php?/$1 last;
    }

    # Sanctum CSRF Cookie
    location /sanctum/csrf-cookie {
        alias /var/www/wudms/current/server/public;
        try_files $uri /index.php?$query_string;
        
        location ~ \.php$ {
            fastcgi_pass php-fpm;
            include fastcgi_params;
            fastcgi_param SCRIPT_FILENAME /var/www/wudms/current/server/public/index.php;
        }
    }

    # Deny access to sensitive files
    location ~ /\. {
        deny all;
        access_log off;
        log_not_found off;
    }

    location ~ /\.env {
        deny all;
    }

    location ~ /\.git {
        deny all;
    }

    # Deny execution of scripts in upload directories
    location ~* /storage/.*\.(php|phtml|php3|php4|php5|php7|phps|cgi|pl|exe)$ {
        deny all;
    }

    # Rate Limiting (prevents brute force)
    limit_req_zone $binary_remote_addr zone=api_limit:10m rate=30r/m;
    location /api/v1/auth/login {
        limit_req zone=api_limit burst=5 nodelay;
        try_files $uri @api;
    }
}
```

Enable the site:
```bash
sudo ln -s /etc/nginx/sites-available/wudms /etc/nginx/sites-enabled/
sudo nginx -t  # Test configuration
sudo systemctl reload nginx
```

### 5.2 PHP-FPM Configuration

Edit `/etc/php/8.4/fpm/pool.d/www.conf`:

```ini
[www]
user = wudms
group = wudms
listen = /run/php/php8.4-fpm.sock
listen.owner = www-data
listen.group = www-data
listen.mode = 0660

# Process Management
pm = dynamic
pm.max_children = 50       # Max concurrent requests
pm.start_servers = 10      # Initial workers
pm.min_spare_servers = 5
pm.max_spare_servers = 20
pm.max_requests = 500      # Restart after N requests (prevents memory leaks)

# Resource Limits
php_admin_value[memory_limit] = 256M
php_admin_value[upload_max_filesize] = 100M
php_admin_value[post_max_size] = 100M
php_admin_value[max_execution_time] = 300

# Error Logging
php_admin_value[error_log] = /var/log/php8.4-fpm-wudms.log
php_admin_flag[log_errors] = on

# Security
php_admin_value[open_basedir] = /var/www/wudms:/tmp
php_admin_value[disable_functions] = exec,passthru,shell_exec,system,proc_open,popen,curl_exec,curl_multi_exec,parse_ini_file,show_source
```

Restart PHP-FPM:
```bash
sudo systemctl restart php8.4-fpm
```

---

## 6. Process Management

### 6.1 Queue Worker Configuration

Create `/etc/supervisor/conf.d/wudms-worker.conf`:

```ini
[program:wudms-worker]
process_name=%(program_name)s_%(process_num)02d
command=/usr/bin/php /var/www/wudms/current/server/artisan queue:work database --sleep=3 --tries=3 --max-time=3600 --timeout=300
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=wudms
numprocs=4  # Number of parallel workers
redirect_stderr=true
stdout_logfile=/var/www/wudms/shared/logs/worker.log
stdout_logfile_maxbytes=10MB
stdout_logfile_backups=5
stopwaitsecs=3600
```

**Queue Worker Explanation:**
- `--sleep=3`: Wait 3 seconds between job polls
- `--tries=3`: Retry failed jobs 3 times
- `--max-time=3600`: Restart worker after 1 hour
- `--timeout=300`: Job execution timeout (5 minutes)

Load configuration:
```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start wudms-worker:*
sudo supervisorctl status  # Verify running
```

---

## 7. Scheduler Setup

Laravel's scheduler requires a single cron entry that runs every minute:

```bash
sudo crontab -e -u wudms
```

Add this line:
```cron
* * * * * cd /var/www/wudms/current/server && /usr/bin/php artisan schedule:run >> /dev/null 2>&1
```

**Scheduled Commands (defined in `routes/console.php`):**
- `dms:cleanup-import-uploads` - Daily at 02:30 (removes expired temporary import files)
- `dms:process-due-dates` - Every 15 minutes (checks for overdue workflow tasks)
- `dms:send-notification-digest` - Daily at 08:00 (batched notifications)
- `backup:run` - Daily at 03:00 (automated database backup)

Verify scheduler:
```bash
# List scheduled tasks
php artisan schedule:list

# Test scheduler execution
php artisan schedule:run
```

---

## 8. Frontend Build & Deployment

### 8.1 Install Dependencies

```bash
cd /var/www/wudms/releases/$(ls -t | head -1)/client
npm ci --production  # Clean install from package-lock.json
```

### 8.2 Environment Configuration

Create `client/.env.production`:

```ini
VITE_API_BASE_URL=https://dms.wu.edu.et/api/v1
VITE_APP_NAME="Wollo University DMS"
VITE_APP_DESCRIPTION="Document Management and Archive System"
```

### 8.3 Production Build

```bash
npm run build
# Output: client/dist/
```

**Build Verification:**
```bash
ls -lh dist/
# Should show optimized assets:
# - index.html
# - assets/*.js (minified)
# - assets/*.css (minified)
# - favicon, images
```

### 8.4 Deploy to Web Root

```bash
# Create symlink to current release
sudo ln -sfn /var/www/wudms/releases/$(ls -t | head -1) /var/www/wudms/current

# Verify symlink
ls -la /var/www/wudms/current  # Should point to latest release
```

---

## 9. Security Hardening

### 9.1 File Permissions

```bash
# Application files: read-only for web server
sudo chown -R wudms:wudms /var/www/wudms
sudo find /var/www/wudms/current -type f -exec chmod 644 {} \;
sudo find /var/www/wudms/current -type d -exec chmod 755 {} \;

# Storage: writable by web server
sudo chown -R wudms:www-data /var/www/wudms/shared/storage
sudo find /var/www/wudms/shared/storage -type f -exec chmod 664 {} \;
sudo find /var/www/wudms/shared/storage -type d -exec chmod 775 {} \;

# Logs: writable
sudo chown -R wudms:www-data /var/www/wudms/shared/logs
sudo chmod -R 775 /var/www/wudms/shared/logs

# .env: readable only by application user
sudo chmod 600 /var/www/wudms/shared/.env
sudo chown wudms:wudms /var/www/wudms/shared/.env

# Artisan executable
sudo chmod +x /var/www/wudms/current/server/artisan
```

### 9.2 Firewall Configuration

```bash
# UFW (Ubuntu)
sudo ufw allow 22/tcp    # SSH
sudo ufw allow 80/tcp    # HTTP (redirects to HTTPS)
sudo ufw allow 443/tcp   # HTTPS
sudo ufw enable

# Verify rules
sudo ufw status
```

### 9.3 Fail2Ban (Brute Force Protection)

```bash
sudo apt install -y fail2ban
```

Create `/etc/fail2ban/jail.d/wudms.conf`:

```ini
[wudms-auth]
enabled = true
filter = wudms-auth
logpath = /var/log/nginx/wudms_access.log
maxretry = 5
findtime = 600
bantime = 3600
port = http,https
```

Create `/etc/fail2ban/filter.d/wudms-auth.conf`:

```ini
[Definition]
failregex = ^<HOST>.*POST.*/api/v1/auth/login.*401
ignoreregex =
```

Restart Fail2Ban:
```bash
sudo systemctl restart fail2ban
sudo fail2ban-client status wudms-auth
```

### 9.4 Disable Unnecessary Services

```bash
# List running services
systemctl list-units --type=service --state=running

# Disable unused services (example)
sudo systemctl disable bluetooth
sudo systemctl stop bluetooth
```

---

## 10. Monitoring & Logging

### 10.1 Laravel Logging

Logs are written to `/var/www/wudms/shared/storage/logs/`.

Configure rotation in `/etc/logrotate.d/wudms`:

```
/var/www/wudms/shared/storage/logs/*.log {
    daily
    missingok
    rotate 14
    compress
    delaycompress
    notifempty
    create 0664 wudms www-data
    sharedscripts
    postrotate
        sudo supervisorctl restart wudms-worker:*
    endscript
}
```

### 10.2 MySQL Slow Query Monitoring

Enable slow query log (already configured in section 3.3):

```bash
# Analyze slow queries
sudo mysqldumpslow -s t -t 10 /var/log/mysql/slow-query.log
```

### 10.3 Health Check Endpoint

Test application health:

```bash
curl -I https://dms.wu.edu.et/api/v1/health
# Expected: 200 OK
```

If health endpoint doesn't exist, create `/server/routes/api.php`:

```php
Route::get('/health', function () {
    return response()->json([
        'status' => 'healthy',
        'timestamp' => now()->toIso8601String(),
        'database' => DB::connection()->getPdo() ? 'connected' : 'disconnected',
    ]);
});
```

### 10.4 Monitoring Tools (Optional)

**Application Performance:**
- Laravel Telescope (staging only, NOT production)
- Sentry (error tracking)
- New Relic (APM)

**Infrastructure:**
- Prometheus + Grafana
- Nagios
- Zabbix

---

## 11. Backup Strategy

### 11.1 Database Backup

Create `/usr/local/bin/wudms-backup.sh`:

```bash
#!/bin/bash
set -e

BACKUP_DIR="/var/backups/wudms/database"
DATE=$(date +%Y%m%d_%H%M%S)
RETENTION_DAYS=30

mkdir -p "$BACKUP_DIR"

# Dump database
mysqldump --single-transaction --routines --triggers \
    --user=wudms_app --password={{DB_PASSWORD}} \
    wollo_dms | gzip > "$BACKUP_DIR/wudms_${DATE}.sql.gz"

# Verify backup
if [ -f "$BACKUP_DIR/wudms_${DATE}.sql.gz" ]; then
    echo "Backup created: $BACKUP_DIR/wudms_${DATE}.sql.gz"
else
    echo "ERROR: Backup failed!"
    exit 1
fi

# Delete backups older than RETENTION_DAYS
find "$BACKUP_DIR" -name "wudms_*.sql.gz" -mtime +$RETENTION_DAYS -delete

echo "Old backups cleaned (retention: ${RETENTION_DAYS} days)"
```

Make executable:
```bash
sudo chmod +x /usr/local/bin/wudms-backup.sh
```

Schedule daily backup:
```bash
sudo crontab -e
```
Add:
```cron
0 3 * * * /usr/local/bin/wudms-backup.sh >> /var/log/wudms-backup.log 2>&1
```

### 11.2 Storage Backup

```bash
# Rsync to backup server
rsync -avz --delete /var/www/wudms/shared/storage/app/private/ \
    backup-server:/backups/wudms/storage/
```

### 11.3 Restoration Procedure

**Database Restore:**
```bash
# List available backups
ls -lh /var/backups/wudms/database/

# Restore specific backup
gunzip < /var/backups/wudms/database/wudms_20241122_030000.sql.gz | \
    mysql -u wudms_app -p wollo_dms

# Verify restoration
mysql -u wudms_app -p wollo_dms -e "SELECT COUNT(*) FROM users;"
```

**Storage Restore:**
```bash
rsync -avz backup-server:/backups/wudms/storage/ \
    /var/www/wudms/shared/storage/app/private/
```

---

## 12. Post-Deployment Verification

### 12.1 Health Check Checklist

```bash
# 1. Web server responding
curl -I https://dms.wu.edu.et
# Expected: HTTP/2 200

# 2. API accessible
curl https://dms.wu.edu.et/api/v1/settings/public
# Expected: JSON response

# 3. Database connectivity
php artisan tinker
>>> \App\Models\User::count();
>>> exit

# 4. Queue workers running
sudo supervisorctl status
# Expected: wudms-worker:* RUNNING

# 5. Scheduler configured
sudo crontab -l -u wudms
# Expected: schedule:run entry

# 6. Logs writable
sudo -u wudms touch /var/www/wudms/shared/storage/logs/test.log
ls -l /var/www/wudms/shared/storage/logs/test.log

# 7. Cache working
php artisan cache:clear
php artisan config:cache

# 8. Storage accessible
php artisan storage:link
ls -la /var/www/wudms/current/server/public/storage
```

### 12.2 Functional Testing

**Test User Login:**
1. Navigate to `https://dms.wu.edu.et`
2. Login with seeded credentials: `admin@wu.edu.et` / check seeder
3. Verify dashboard loads

**Test Document Upload:**
1. Navigate to Documents → Create Document
2. Upload a test PDF file
3. Verify file appears in storage: `/var/www/wudms/shared/storage/app/private/`

**Test Correspondence Workflow:**
1. Navigate to Correspondence → Incoming Letters
2. Register a new incoming correspondence
3. Assign to a user
4. Verify notification sent

**Test Audit Trail:**
1. Navigate to Administration → Audit Log
2. Verify login event recorded
3. Verify document upload recorded

### 12.3 Performance Baseline

```bash
# API response time
time curl -s https://dms.wu.edu.et/api/v1/auth/me > /dev/null
# Target: < 200ms

# Database query performance
php artisan dms:profile-queries
# Review execution plans

# Page load time (use browser DevTools)
# Target: First Contentful Paint < 1.5s
```

---

## 13. Troubleshooting

### 13.1 Common Issues

#### Issue: 502 Bad Gateway

**Cause:** PHP-FPM not running or socket permission issue

**Solution:**
```bash
# Check PHP-FPM status
sudo systemctl status php8.4-fpm

# Check socket permissions
ls -l /run/php/php8.4-fpm.sock

# Restart PHP-FPM
sudo systemctl restart php8.4-fpm
```

#### Issue: 500 Internal Server Error

**Cause:** Laravel error, check logs

**Solution:**
```bash
# Check Laravel logs
tail -f /var/www/wudms/shared/storage/logs/laravel.log

# Check Nginx error log
sudo tail -f /var/log/nginx/wudms_error.log

# Check PHP-FPM error log
sudo tail -f /var/log/php8.4-fpm-wudms.log
```

#### Issue: Database Connection Failed

**Cause:** Incorrect credentials or MySQL not running

**Solution:**
```bash
# Verify MySQL running
sudo systemctl status mysql

# Test connection
mysql -u wudms_app -p wollo_dms -e "SELECT 1;"

# Check .env configuration
cat /var/www/wudms/shared/.env | grep DB_
```

#### Issue: Permission Denied (Storage)

**Cause:** Incorrect file ownership

**Solution:**
```bash
# Fix storage permissions
sudo chown -R wudms:www-data /var/www/wudms/shared/storage
sudo chmod -R 775 /var/www/wudms/shared/storage

# Clear cached permissions
php artisan cache:clear
```

#### Issue: Queue Jobs Not Processing

**Cause:** Supervisor worker not running

**Solution:**
```bash
# Check supervisor status
sudo supervisorctl status wudms-worker:*

# Restart workers
sudo supervisorctl restart wudms-worker:*

# Check worker logs
tail -f /var/www/wudms/shared/logs/worker.log
```

#### Issue: Scheduler Not Running

**Cause:** Cron not configured

**Solution:**
```bash
# Verify cron entry
sudo crontab -l -u wudms

# Test scheduler manually
php artisan schedule:run -vvv

# Check cron logs
grep CRON /var/log/syslog
```

### 13.2 Debug Mode (⚠️ NEVER IN PRODUCTION)

If you absolutely must enable debug mode temporarily:

```bash
# Edit .env
nano /var/www/wudms/shared/.env
# Set: APP_DEBUG=true

# Clear cache
php artisan config:clear

# **IMMEDIATELY** disable after debugging:
# Set: APP_DEBUG=false
php artisan config:cache
```

### 13.3 Log Locations

- **Laravel Application:** `/var/www/wudms/shared/storage/logs/laravel.log`
- **Queue Workers:** `/var/www/wudms/shared/logs/worker.log`
- **Nginx Access:** `/var/log/nginx/wudms_access.log`
- **Nginx Error:** `/var/log/nginx/wudms_error.log`
- **PHP-FPM:** `/var/log/php8.4-fpm-wudms.log`
- **MySQL:** `/var/log/mysql/error.log`, `/var/log/mysql/slow-query.log`
- **System:** `/var/log/syslog`

---

## 14. Rollback Procedure

### 14.1 Quick Rollback (Symlink Switch)

If the new release has critical issues:

```bash
# List releases
ls -lt /var/www/wudms/releases/

# Identify previous working release
PREVIOUS_RELEASE=20241120153000  # Example timestamp

# Switch symlink to previous release
sudo ln -sfn /var/www/wudms/releases/$PREVIOUS_RELEASE /var/www/wudms/current

# Clear caches
php artisan config:clear
php artisan route:clear
php artisan view:clear

# Restart services
sudo systemctl reload nginx
sudo supervisorctl restart wudms-worker:*

# Verify rollback
curl -I https://dms.wu.edu.et
```

### 14.2 Database Rollback

**⚠️ WARNING:** Database rollback is destructive. Only proceed if you have a verified backup.

```bash
# Stop application traffic (maintenance mode)
php artisan down --message="System maintenance in progress"

# Stop queue workers
sudo supervisorctl stop wudms-worker:*

# Restore database
gunzip < /var/backups/wudms/database/wudms_TIMESTAMP.sql.gz | \
    mysql -u wudms_app -p wollo_dms

# Verify restoration
mysql -u wudms_app -p wollo_dms -e "SELECT MAX(id) FROM audit_events;"

# Restart services
sudo supervisorctl start wudms-worker:*

# Exit maintenance mode
php artisan up
```

### 14.3 Zero-Downtime Deployment Strategy

For future deployments:

1. **Blue-Green Deployment:** Run two identical production environments
2. **Load Balancer:** Switch traffic between environments
3. **Database Migrations:** Use non-breaking migrations only
4. **Feature Flags:** Enable/disable features without code deployment

---

## 15. Maintenance Windows

### 15.1 Planned Maintenance

**Monthly Security Patching:**
- First Sunday of each month, 02:00-04:00 AM
- Update OS packages, PHP, MySQL
- Test on staging first

```bash
# System updates
sudo apt update && sudo apt upgrade -y

# Restart services
sudo systemctl restart mysql
sudo systemctl restart php8.4-fpm
sudo systemctl reload nginx
```

### 15.2 Database Optimization

**Quarterly:**
```bash
# Analyze tables
mysqlcheck -u wudms_app -p --analyze wollo_dms

# Optimize tables
mysqlcheck -u wudms_app -p --optimize wollo_dms

# Check for fragmentation
mysql -u wudms_app -p wollo_dms -e "SHOW TABLE STATUS;"
```

---

## 16. Contact & Support

**System Administrators:**
- Primary: [IT Department Email]
- Secondary: [Backup Contact]

**Escalation:**
- Critical (P1): Page on-call engineer immediately
- High (P2): Email within 2 hours
- Medium (P3): Email within 24 hours
- Low (P4): Weekly maintenance window

**Vendor Support:**
- Wollo University KIoT Development Team
- GitHub Issues: [Repository URL]

---

## Appendix A: Production Environment Variables Reference

Complete `.env` template with all configuration options:

```ini
# See section 4.3 for full configuration details
```

## Appendix B: Firewall Rules Reference

```bash
# See section 9.2 for complete firewall configuration
```

## Appendix C: SSL Certificate Renewal

```bash
# Let's Encrypt auto-renewal
sudo certbot renew --dry-run

# Manual renewal
sudo certbot renew

# Nginx reload after renewal
sudo systemctl reload nginx
```

---

**Document Version:** 1.0  
**Last Reviewed:** 2024-11-22  
**Next Review:** 2025-02-22
