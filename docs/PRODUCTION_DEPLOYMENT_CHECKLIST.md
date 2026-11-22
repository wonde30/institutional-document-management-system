# WU-DMS Production Deployment Checklist

**Version:** 1.0  
**Date Prepared:** November 20, 2026  
**Application:** Wollo University Document Management System  
**Status:** Ready for Deployment (with conditions)

---

## Pre-Deployment Verification

### Application Readiness

- [x] All backend tests passing (484/484)
- [x] All frontend tests passing (180/180)
- [x] TypeScript compilation successful (0 errors)
- [x] Production build successful
- [x] No critical or high-severity security issues
- [x] Database schema stable and migrated
- [x] Git working tree clean (all fixes committed)

---

## Phase 1: Infrastructure Preparation

### 1.1 Server Environment Setup

**Web Server (Nginx/Apache):**
- [ ] Install Nginx or Apache
- [ ] Configure SSL/TLS certificates
- [ ] Set up reverse proxy to Laravel backend
- [ ] Configure SPA routing fallback (try_files)
- [ ] Enable gzip compression
- [ ] Set security headers (CSP, X-Frame-Options, HSTS)
- [ ] Configure rate limiting
- [ ] Disable directory listing

**Example Nginx Configuration:**
```nginx
server {
    listen 443 ssl http2;
    server_name dms.wollo.edu.et;

    ssl_certificate /etc/ssl/certs/wollo-dms.crt;
    ssl_certificate_key /etc/ssl/private/wollo-dms.key;

    # Frontend SPA
    root /var/www/wu-dms/client/dist;
    index index.html;

    # Security Headers
    add_header X-Frame-Options "SAMEORIGIN";
    add_header X-Content-Type-Options "nosniff";
    add_header X-XSS-Protection "1; mode=block";
    add_header Strict-Transport-Security "max-age=31536000; includeSubDomains";

    # SPA routing fallback
    location / {
        try_files $uri $uri/ /index.html;
    }

    # API proxy
    location /api {
        proxy_pass http://127.0.0.1:8000;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
        proxy_set_header X-XSRF-TOKEN $http_x_xsrf_token;
        
        # CORS and auth support
        proxy_cookie_path / "/; Secure; HttpOnly; SameSite=Lax";
    }

    # Sanctum CSRF endpoint
    location /sanctum/csrf-cookie {
        proxy_pass http://127.0.0.1:8000;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
    }

    # Static assets
    location ~* \.(js|css|png|jpg|jpeg|gif|ico|svg|woff|woff2|ttf|eot)$ {
        expires 1y;
        add_header Cache-Control "public, immutable";
    }
}

# HTTP to HTTPS redirect
server {
    listen 80;
    server_name dms.wollo.edu.et;
    return 301 https://$server_name$request_uri;
}
```

### 1.2 PHP Runtime Configuration

**Required PHP Version:** >= 8.4

**Required Extensions:**
- [ ] `php-openssl` (for JWT and digital signatures)
- [ ] `php-bcmath` (for financial calculations)
- [ ] `php-mbstring` (for UTF-8 string handling)
- [ ] `php-pdo_mysql` (for database connectivity)
- [ ] `php-xml` (for XML processing)
- [ ] `php-curl` (for HTTP client)
- [ ] `php-zip` (for archive operations)
- [ ] `php-gd` (optional: for image processing)

**PHP Configuration (`php.ini`):**
```ini
upload_max_filesize = 52M
post_max_size = 52M
max_execution_time = 300
memory_limit = 256M
opcache.enable = 1
opcache.memory_consumption = 128
opcache.max_accelerated_files = 10000
opcache.validate_timestamps = 0
```

**Verify PHP Extensions:**
```bash
php -m | grep -E 'openssl|bcmath|mbstring|pdo_mysql|xml|curl|zip'
```

### 1.3 Database Setup

**MySQL Version:** >= 8.0

**Database Creation:**
```sql
CREATE DATABASE wollo_dms_production
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

CREATE USER 'wu_dms_user'@'localhost' 
  IDENTIFIED BY '<strong_password>';

GRANT SELECT, INSERT, UPDATE, DELETE, CREATE, INDEX, ALTER
  ON wollo_dms_production.* 
  TO 'wu_dms_user'@'localhost';

FLUSH PRIVILEGES;
```

**Backup Configuration:**
- [ ] Configure automated daily backups
- [ ] Test backup restoration process
- [ ] Configure off-site backup storage
- [ ] Document backup retention policy (recommend 30 days minimum)

**Backup Script Example:**
```bash
#!/bin/bash
TIMESTAMP=$(date +%Y%m%d_%H%M%S)
BACKUP_DIR="/var/backups/wu-dms"
DB_NAME="wollo_dms_production"

mkdir -p $BACKUP_DIR

mysqldump --single-transaction --routines --triggers \
  $DB_NAME | gzip > $BACKUP_DIR/wu_dms_$TIMESTAMP.sql.gz

# Keep last 30 days
find $BACKUP_DIR -name "wu_dms_*.sql.gz" -mtime +30 -delete
```

### 1.4 File Storage Setup

**Storage Directories:**
```bash
mkdir -p /var/www/wu-dms/server/storage/app/documents
mkdir -p /var/www/wu-dms/server/storage/app/private
mkdir -p /var/www/wu-dms/server/storage/logs
mkdir -p /var/www/wu-dms/server/storage/framework/cache
mkdir -p /var/www/wu-dms/server/storage/framework/sessions
mkdir -p /var/www/wu-dms/server/storage/framework/views

chown -R www-data:www-data /var/www/wu-dms/server/storage
chmod -R 775 /var/www/wu-dms/server/storage
```

**Institutional Assets:**
- [ ] Copy institutional emblem to `server/storage/assets/emblem.png`
- [ ] Copy Nyala font to `server/storage/fonts/nyala.ttf`
- [ ] Copy signature stamps to `server/storage/stamps/` (if applicable)
- [ ] Verify file permissions (readable by web server)

---

## Phase 2: Application Configuration

### 2.1 Environment Configuration

**Create Production `.env` File:**

```bash
cd /var/www/wu-dms/server
cp .env.example .env
nano .env
```

**Production Environment Variables:**

```ini
# Application
APP_NAME="Wollo University DMS"
APP_ENV=production
APP_KEY=                        # ⚠️ Generate with: php artisan key:generate
APP_DEBUG=false                 # ⚠️ MUST be false in production
APP_URL=https://dms.wollo.edu.et

# Localization
APP_LOCALE=en
APP_FALLBACK_LOCALE=en
APP_TIMEZONE=Africa/Addis_Ababa

# Database
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=wollo_dms_production
DB_USERNAME=wu_dms_user
DB_PASSWORD=                    # ⚠️ Use strong password

# Session
SESSION_DRIVER=database
SESSION_LIFETIME=120
SESSION_ENCRYPT=false
SESSION_PATH=/
SESSION_DOMAIN=.wollo.edu.et    # ⚠️ Set to your domain
SESSION_SECURE_COOKIE=true      # ⚠️ MUST be true for HTTPS
SESSION_HTTP_ONLY=true
SESSION_SAME_SITE=lax

# Cache & Queue
CACHE_STORE=database
QUEUE_CONNECTION=database

# Frontend
FRONTEND_URL=https://dms.wollo.edu.et
SANCTUM_STATEFUL_DOMAINS=dms.wollo.edu.et,www.wollo.edu.et

# CORS
CORS_ALLOWED_ORIGINS=https://dms.wollo.edu.et,https://www.wollo.edu.et

# Mail (Configure SMTP settings)
MAIL_MAILER=smtp
MAIL_HOST=smtp.wollo.edu.et
MAIL_PORT=587
MAIL_USERNAME=dms@wollo.edu.et
MAIL_PASSWORD=
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=dms@wollo.edu.et
MAIL_FROM_NAME="${APP_NAME}"

# Logging
LOG_CHANNEL=stack
LOG_LEVEL=warning               # Use 'warning' or 'error' in production
LOG_DEPRECATIONS_CHANNEL=null

# File Storage (use 'local' for local disk)
FILESYSTEM_DISK=local

# Optional: AWS S3 (if using cloud storage)
# FILESYSTEM_DISK=s3
# AWS_ACCESS_KEY_ID=
# AWS_SECRET_ACCESS_KEY=
# AWS_DEFAULT_REGION=
# AWS_BUCKET=
```

**Security Checklist:**
- [ ] `APP_DEBUG=false` (⚠️ CRITICAL)
- [ ] `APP_KEY` generated (never share)
- [ ] `SESSION_SECURE_COOKIE=true`
- [ ] Strong database password set
- [ ] Production domain configured
- [ ] CORS origins restricted to production domains only
- [ ] No localhost domains in `SANCTUM_STATEFUL_DOMAINS`

### 2.2 Install Dependencies

```bash
cd /var/www/wu-dms/server

# Install PHP dependencies (production only, optimized)
composer install --no-dev --optimize-autoloader --no-interaction

cd /var/www/wu-dms/client

# Install Node dependencies
npm ci --production

# Build optimized frontend
npm run build
```

### 2.3 Database Migration

**⚠️ CRITICAL: Create Backup First**

```bash
# Create pre-migration backup
mysqldump --single-transaction wollo_dms_production > backup_pre_migration.sql

# Run migrations
cd /var/www/wu-dms/server
php artisan migrate --force

# Verify migration status
php artisan migrate:status
```

**Optional: Apply FULLTEXT Index (if document volume > 20,000):**
```bash
php artisan migrate --path=database/migrations/2026_11_20_000001_wu_add_documents_fulltext_index.php --force
```

### 2.4 Cache Optimization

```bash
cd /var/www/wu-dms/server

# Clear all caches
php artisan config:clear
php artisan route:clear
php artisan view:clear
php artisan cache:clear

# Generate optimized caches
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Optimize autoloader
composer dump-autoload --optimize
```

### 2.5 File Permissions

```bash
cd /var/www/wu-dms/server

# Set ownership
chown -R www-data:www-data storage bootstrap/cache

# Set permissions
chmod -R 775 storage bootstrap/cache
```

---

## Phase 3: Institutional Configuration

### 3.1 Seed Initial Data

**⚠️ Only if database is empty. Skip if migrating from staging.**

```bash
php artisan db:seed --class=InstitutionalBaselineSeeder
```

### 3.2 Configure Organizational Structure

**Access Admin Panel:**
1. Log in as super administrator
2. Navigate to **Administration → Organizational Units**
3. Create institutional hierarchy:
   - Root: Wollo University
   - Colleges (e.g., College of Technology)
   - Departments (e.g., Software Engineering)
   - Offices (e.g., Quality Assurance Directorate)

### 3.3 Configure Roles and Permissions

**Review Default Roles:**
- Super Administrator
- System Administrator
- College Dean
- Department Head
- QA Director
- Registrar
- Archivist
- Academic Staff

**Create Custom Roles (if needed):**
- Navigate to **Access Control → Role Management**
- Define institutional-specific roles
- Assign appropriate permissions

### 3.4 Create User Accounts

**Create institutional user accounts:**
1. Navigate to **User Management → Create User**
2. For each staff member:
   - Enter email (institutional email)
   - Set temporary password (require change on first login)
   - Assign primary organizational unit
   - Assign roles
   - Set account status to active

**⚠️ Security Best Practice:**
- Use institutional email addresses only
- Enforce strong password policy
- Enable email verification (if configured)
- Document user creation process

### 3.5 Configure Workflow Definitions

**Create Institutional Workflows:**

1. Navigate to **Workflows → Workflow Definitions**
2. Create workflow for "Standard Document Approval":
   - **States:**
     - Draft
     - Department Review
     - College Review
     - Approved
     - Rejected
   - **Transitions:**
     - Draft → Department Review (submit)
     - Department Review → College Review (forward)
     - Department Review → Draft (return for revision)
     - College Review → Approved (approve)
     - College Review → Rejected (reject)
   - **Assignments:**
     - Department Review → Department Head role
     - College Review → College Dean role
   - **Consensus Policies:**
     - Configure as needed (unanimous, majority, etc.)

3. **Publish Workflow Version**
4. **Test with Sample Document**

### 3.6 Configure Correspondence Routing Policies

**Create Routing Rules:**

1. Navigate to **Configuration → Routing Policies**
2. Define rules based on:
   - Correspondence type (incoming, outgoing, internal)
   - Document category
   - Priority level
   - Origin unit
3. Set default receiving unit
4. Set SLA hours
5. Activate policy

---

## Phase 4: Production Testing

### 4.1 Smoke Tests

**Perform the following tests in production:**

- [ ] **Authentication:**
  - [ ] Super admin can log in
  - [ ] Staff user can log in
  - [ ] Invalid credentials rejected
  - [ ] Logout works correctly

- [ ] **Authorization:**
  - [ ] Users see only permitted navigation items
  - [ ] Cross-unit document access blocked
  - [ ] Admin-only features protected

- [ ] **Document Management:**
  - [ ] Create new document
  - [ ] Upload file attachment
  - [ ] View document detail
  - [ ] Download attached file
  - [ ] Create new version

- [ ] **Correspondence:**
  - [ ] Register incoming correspondence
  - [ ] Routing policy applied correctly
  - [ ] Movement recorded automatically
  - [ ] Acknowledgment works

- [ ] **Official Letters:**
  - [ ] Generate bilingual letterhead
  - [ ] Ethiopian date displays correctly (ዓ.ም not mojibake)
  - [ ] Preview PDF renders Amharic text
  - [ ] Download generated PDF

- [ ] **Workflows:**
  - [ ] Initiate workflow on document
  - [ ] Task appears in reviewer inbox
  - [ ] Approve/reject decision works
  - [ ] Document status updated

- [ ] **Search:**
  - [ ] Global search returns results
  - [ ] Amharic search works
  - [ ] Authorization respected
  - [ ] Pagination works

- [ ] **Accreditation:**
  - [ ] View frameworks and standards
  - [ ] Create accreditation cycle
  - [ ] Upload evidence
  - [ ] Submit for review

### 4.2 Performance Testing

**Measure Key Operations:**
- [ ] Dashboard load time < 2 seconds
- [ ] Document list load time < 1 second
- [ ] Search response time < 500ms
- [ ] PDF generation time < 5 seconds
- [ ] File download initiation < 1 second

**Monitor Resource Usage:**
- [ ] PHP memory usage under 150MB per request
- [ ] Database query time < 100ms average
- [ ] CPU usage < 70% under normal load

### 4.3 User Acceptance Testing

**Conduct UAT with Stakeholders:**
- [ ] College Deans test workflow approval
- [ ] Registrars test correspondence routing
- [ ] QA Director tests accreditation module
- [ ] Archivists test archive operations
- [ ] Academic staff test document creation

**Document Feedback:**
- [ ] Record all user feedback
- [ ] Prioritize critical issues
- [ ] Fix blockers before go-live
- [ ] Schedule enhancements for future releases

---

## Phase 5: Production Launch

### 5.1 Pre-Launch Checklist

- [ ] All smoke tests passed
- [ ] Performance acceptable
- [ ] UAT sign-off received
- [ ] Database backup verified
- [ ] SSL certificates valid
- [ ] DNS configured
- [ ] Monitoring set up
- [ ] Support process established
- [ ] User training completed
- [ ] Documentation published

### 5.2 Monitoring & Logging Setup

**Application Monitoring:**
- [ ] Set up error tracking (Sentry, Bugsnag, or Rollbar)
- [ ] Configure uptime monitoring (Pingdom, UptimeRobot)
- [ ] Set up performance monitoring (New Relic, DataDog)
- [ ] Configure log aggregation (ELK stack, CloudWatch)

**Error Tracking Configuration (`config/logging.php`):**
```php
'channels' => [
    'production' => [
        'driver' => 'stack',
        'channels' => ['daily', 'sentry'],
        'ignore_exceptions' => false,
    ],
    'sentry' => [
        'driver' => 'sentry',
    ],
],
```

**Alerts to Configure:**
- [ ] Application errors (critical and above)
- [ ] Failed authentication attempts (> 10 per minute)
- [ ] Database connection failures
- [ ] Disk space < 10% free
- [ ] Memory usage > 90%
- [ ] CPU usage > 90% for 5 minutes
- [ ] Response time > 5 seconds

### 5.3 Launch Execution

**Launch Steps:**

1. **Final Backup:**
   ```bash
   mysqldump --single-transaction wollo_dms_production > backup_pre_launch.sql
   ```

2. **Put Application in Maintenance Mode:**
   ```bash
   php artisan down --message="System launching. Back shortly." --retry=60
   ```

3. **Pull Latest Code:**
   ```bash
   git pull origin main
   ```

4. **Run Final Migrations (if any):**
   ```bash
   php artisan migrate --force
   ```

5. **Clear and Rebuild Caches:**
   ```bash
   php artisan config:cache
   php artisan route:cache
   php artisan view:cache
   ```

6. **Bring Application Online:**
   ```bash
   php artisan up
   ```

7. **Verify Production Access:**
   - Access `https://dms.wollo.edu.et`
   - Verify login works
   - Check dashboard loads
   - Verify API responses

### 5.4 Post-Launch Monitoring

**First 24 Hours:**
- [ ] Monitor error logs continuously
- [ ] Track authentication success rate
- [ ] Monitor API response times
- [ ] Check database performance
- [ ] Verify file uploads/downloads
- [ ] Monitor server resources

**First Week:**
- [ ] Daily review of error logs
- [ ] User feedback collection
- [ ] Performance metrics review
- [ ] Security log analysis
- [ ] Backup verification

---

## Phase 6: Post-Deployment

### 6.1 Documentation

**Publish User Guides:**
- [ ] System administrator manual
- [ ] End-user guide
- [ ] Workflow guide
- [ ] Correspondence routing guide
- [ ] FAQ document

**Technical Documentation:**
- [ ] API documentation
- [ ] Database schema documentation
- [ ] Deployment runbook
- [ ] Incident response procedures
- [ ] Backup/restore procedures

### 6.2 Training

**Conduct Training Sessions:**
- [ ] Administrator training (2 days)
- [ ] Power user training (1 day)
- [ ] End-user training (half day)
- [ ] Help desk training

**Training Materials:**
- [ ] Video tutorials
- [ ] Quick reference guides
- [ ] Troubleshooting guides

### 6.3 Support Structure

**Establish Support Channels:**
- [ ] Help desk email: dms-support@wollo.edu.et
- [ ] Support ticket system
- [ ] In-person support hours
- [ ] Escalation procedures

**Support Team:**
- [ ] Designate Level 1 support (help desk)
- [ ] Designate Level 2 support (power users)
- [ ] Designate Level 3 support (administrators/developers)
- [ ] Define escalation paths

### 6.4 Maintenance Schedule

**Regular Maintenance:**
- [ ] **Daily:** Monitor logs and performance
- [ ] **Weekly:** Review security logs, check disk space
- [ ] **Monthly:** Apply security patches, review user accounts
- [ ] **Quarterly:** Database optimization, security audit
- [ ] **Annually:** Disaster recovery drill, penetration test

**Backup Schedule:**
- [ ] **Daily:** Full database backup (retained 30 days)
- [ ] **Weekly:** Full system backup (retained 90 days)
- [ ] **Monthly:** Off-site backup verification

---

## Phase 7: Continuous Improvement

### 7.1 Performance Optimization (Post-Launch)

**Monitor and Optimize:**
- [ ] Analyze slow queries (> 100ms)
- [ ] Review N+1 query patterns
- [ ] Evaluate need for FULLTEXT index (if document volume > 20K)
- [ ] Consider Redis for session/cache
- [ ] Evaluate CDN for static assets

### 7.2 Security Hardening

**Ongoing Security:**
- [ ] Monthly security patch review
- [ ] Quarterly penetration testing
- [ ] Annual security audit
- [ ] Regular access review (disable inactive accounts)
- [ ] Password rotation for service accounts

### 7.3 Feature Enhancements

**Phase 2 Features (Post-Launch):**
- [ ] Accreditation checklist builders
- [ ] Advanced reporting module
- [ ] Mobile application
- [ ] Bulk document import
- [ ] Advanced search with filters
- [ ] Document templates library
- [ ] Integration with institutional systems

---

## Emergency Contacts

**Application Support:**
- System Administrator: [Name] - [Phone] - [Email]
- Database Administrator: [Name] - [Phone] - [Email]
- Infrastructure Team: [Name] - [Phone] - [Email]

**Escalation Path:**
1. Help Desk (Level 1)
2. System Administrator (Level 2)
3. Technical Lead (Level 3)
4. IT Director (Critical Issues)

**Vendor Support:**
- Hosting Provider: [Contact Info]
- SSL Certificate Authority: [Contact Info]
- Email Provider: [Contact Info]

---

## Rollback Procedure

**If Critical Issues Arise:**

1. **Immediate Actions:**
   ```bash
   # Put site in maintenance mode
   php artisan down
   
   # Assess severity
   # - Can issue be fixed quickly? (< 30 minutes) → Fix and proceed
   # - Critical functionality broken? → Rollback
   ```

2. **Rollback Steps:**
   ```bash
   # Restore database from pre-launch backup
   mysql wollo_dms_production < backup_pre_launch.sql
   
   # Revert code to previous stable version
   git checkout <previous-stable-tag>
   
   # Clear caches
   php artisan config:clear
   php artisan route:clear
   php artisan view:clear
   
   # Rebuild caches
   php artisan config:cache
   php artisan route:cache
   
   # Bring site back online
   php artisan up
   ```

3. **Post-Rollback:**
   - [ ] Verify application functionality
   - [ ] Notify stakeholders
   - [ ] Investigate root cause
   - [ ] Document incident
   - [ ] Plan remediation
   - [ ] Schedule re-launch

---

## Sign-Off

**Deployment Authorization:**

| Role | Name | Signature | Date |
|---|---|---|---|
| IT Director | | | |
| System Administrator | | | |
| QA Manager | | | |
| Project Sponsor | | | |

**Deployment Execution:**

| Task | Responsible | Completed | Date | Notes |
|---|---|---|---|---|
| Infrastructure Setup | | [ ] | | |
| Application Configuration | | [ ] | | |
| Database Migration | | [ ] | | |
| Institutional Configuration | | [ ] | | |
| Smoke Testing | | [ ] | | |
| UAT Sign-off | | [ ] | | |
| Production Launch | | [ ] | | |

---

**Document Version:** 1.0  
**Last Updated:** November 20, 2026  
**Next Review:** After successful deployment

---

**END OF DEPLOYMENT CHECKLIST**
