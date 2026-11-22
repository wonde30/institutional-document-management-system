# WU-DMS Troubleshooting Guide

**System:** Wollo University Document Management System  
**Version:** 1.0  
**Last Updated:** 2024-11-22

---

## Quick Diagnostic Commands

```bash
# System Health Check
php artisan about
php artisan optimize:clear
php artisan config:cache
php artisan route:cache

# Check Services
sudo systemctl status nginx
sudo systemctl status php8.4-fpm
sudo systemctl status mysql
sudo supervisorctl status

# View Logs
tail -f storage/logs/laravel.log
tail -f /var/log/nginx/error.log
tail -f /var/log/mysql/error.log
```

---

## Authentication & Authorization Issues

### Problem: User Cannot Login

**Symptoms:**
- "Invalid credentials" error
- 401 Unauthorized
- Immediate logout after login

**Diagnostics:**
```bash
# Check user exists and is active
php artisan tinker
>>> \App\Models\User::where('email', 'user@wu.edu.et')->first();
>>> // Check: active=1, email_verified_at not null

# Test password hash
>>> Hash::check('password', $user->password);

# Check sessions table
>>> DB::table('sessions')->where('user_id', $user->id)->count();
```

**Solutions:**

1. **Account Inactive:**
```php
DB::table('users')->where('email', 'user@wu.edu.et')->update(['active' => 1]);
```

2. **Email Not Verified:**
```php
DB::table('users')->where('email', 'user@wu.edu.et')->update([
    'email_verified_at' => now()
]);
```

3. **Session Configuration:**
Check `.env`:
```ini
SESSION_DRIVER=database
SESSION_DOMAIN=.wu.edu.et
SESSION_SECURE_COOKIE=true  # Only if HTTPS
```

4. **Clear Sessions:**
```bash
php artisan session:table
php artisan migrate
DB::table('sessions')->truncate();
```

### Problem: 403 Forbidden on Authorized Actions

**Symptoms:**
- User has correct role but gets 403
- Permission check fails unexpectedly

**Diagnostics:**
```bash
php artisan tinker
>>> $user = \App\Models\User::find(1);
>>> $user->hasPermission('documents.create');
>>> $user->getCapabilitySets();  # Check active unit context
>>> $user->getPermittedUnitIds('documents.create');
```

**Solutions:**

1. **Check Active Organizational Unit:**
```php
// User must select an active unit
>>> $user->activeUnit;
// If null, permission check uses global scope only
```

2. **Verify Permission Grant:**
```sql
SELECT ur.user_id, r.code as role, rp.permission_id, p.code as permission, 
       as.kind as scope, as.unit_id
FROM user_roles ur
JOIN roles r ON ur.role_id = r.id
JOIN role_permissions rp ON r.id = rp.role_id
JOIN permissions p ON rp.permission_id = p.id
JOIN authorization_scopes as ON ur.scope_id = as.id
WHERE ur.user_id = 1;
```

3. **Force Permission Refresh:**
```bash
# Increment authz_version to invalidate cached permissions
DB::table('users')->where('id', $userId)->increment('authz_version');
```

### Problem: CORS Errors in Browser Console

**Symptoms:**
- "Access-Control-Allow-Origin" header missing
- Preflight OPTIONS request fails

**Solution:**

Check `config/cors.php`:
```php
'paths' => ['api/*', 'sanctum/csrf-cookie'],
'allowed_origins' => [env('FRONTEND_URL', 'https://dms.wu.edu.et')],
'allowed_methods' => ['*'],
'allowed_headers' => ['*'],
'exposed_headers' => [],
'max_age' => 0,
'supports_credentials' => true,
```

---

## Database Issues

### Problem: Connection Refused

**Diagnostics:**
```bash
# Test MySQL connectivity
mysql -u wudms_app -p -h 127.0.0.1 wollo_dms

# Check MySQL running
sudo systemctl status mysql

# Check port
sudo netstat -tlnp | grep 3306
```

**Solutions:**

1. **MySQL Not Running:**
```bash
sudo systemctl start mysql
sudo systemctl enable mysql
```

2. **Wrong Credentials:**
```bash
# Reset password
sudo mysql -u root -p
ALTER USER 'wudms_app'@'localhost' IDENTIFIED BY 'new_password';
FLUSH PRIVILEGES;

# Update .env
DB_PASSWORD=new_password
```

3. **Connection Limit Reached:**
```sql
SHOW VARIABLES LIKE 'max_connections';
SHOW STATUS LIKE 'Threads_connected';

-- Increase limit
SET GLOBAL max_connections = 200;
```

### Problem: Slow Queries

**Diagnostics:**
```bash
# Enable slow query log
sudo nano /etc/mysql/mysql.conf.d/mysqld.cnf
# Add:
# slow_query_log = 1
# long_query_time = 2

sudo systemctl restart mysql

# Analyze slow queries
sudo mysqldumpslow -s t -t 10 /var/log/mysql/slow-query.log
```

**Solutions:**

1. **Missing Indexes:**
```bash
php artisan dms:profile-queries
# Review EXPLAIN output for full table scans
```

2. **Add Indexes:**
```php
// Migration example
Schema::table('documents', function (Blueprint $table) {
    $table->index(['owner_unit_id', 'lifecycle', 'created_at']);
});
```

3. **Query Optimization:**
```php
// Use eager loading
Document::with(['ownerUnit', 'category', 'currentVersion'])->get();

// Instead of N+1 queries
Document::all()->each(fn($doc) => $doc->ownerUnit);
```

### Problem: Deadlocks

**Symptoms:**
- `SQLSTATE[40001]: Serialization failure: 1213 Deadlock found`

**Diagnostics:**
```sql
SHOW ENGINE INNODB STATUS\G
# Look for LATEST DETECTED DEADLOCK section
```

**Solutions:**

1. **Consistent Lock Order:**
```php
// Always lock in same order
DB::transaction(function () {
    Document::lockForUpdate()->find($id);
    DocumentVersion::lockForUpdate()->where('document_id', $id)->first();
});
```

2. **Reduce Transaction Scope:**
```php
// Keep transactions short
DB::transaction(function () {
    // Only critical operations here
});
```

---

## File Upload Issues

### Problem: Upload Fails with 413 Payload Too Large

**Solutions:**

1. **Nginx Configuration:**
```nginx
# /etc/nginx/sites-available/wudms
client_max_body_size 100M;
```

2. **PHP Configuration:**
```ini
# /etc/php/8.4/fpm/php.ini
upload_max_filesize = 100M
post_max_size = 100M
```

3. **Laravel Configuration:**
```php
// config/settings.php
'documents.max_upload_size_mb' => 100,
```

Restart services:
```bash
sudo systemctl reload nginx
sudo systemctl restart php8.4-fpm
```

### Problem: File Not Found After Upload

**Diagnostics:**
```bash
# Check storage link exists
ls -la public/storage  # Should point to ../storage/app/public

# Check file permissions
ls -la storage/app/private/documents/

# Check disk configuration
php artisan storage:link
```

**Solutions:**

1. **Recreate Storage Link:**
```bash
rm public/storage
php artisan storage:link
```

2. **Fix Permissions:**
```bash
sudo chown -R wudms:www-data storage/
sudo chmod -R 775 storage/
```

3. **Check Disk Configuration:**
```php
// config/filesystems.php
'disks' => [
    'private' => [
        'driver' => 'local',
        'root' => storage_path('app/private'),
        'visibility' => 'private',
    ],
],
```

---

## Queue & Scheduler Issues

### Problem: Jobs Not Processing

**Diagnostics:**
```bash
# Check queue workers
sudo supervisorctl status wudms-worker:*

# Check jobs table
php artisan queue:monitor

# View failed jobs
php artisan queue:failed
```

**Solutions:**

1. **Start Workers:**
```bash
sudo supervisorctl start wudms-worker:*
```

2. **Retry Failed Jobs:**
```bash
php artisan queue:retry all
```

3. **Clear Stuck Jobs:**
```bash
# Truncate jobs table (⚠️ loses pending jobs)
DB::table('jobs')->truncate();
DB::table('failed_jobs')->truncate();
```

4. **Check Worker Logs:**
```bash
tail -f /var/www/wudms/shared/logs/worker.log
```

### Problem: Scheduler Not Running

**Diagnostics:**
```bash
# Check cron entry
sudo crontab -l -u wudms

# Test scheduler manually
php artisan schedule:run -vvv

# List scheduled tasks
php artisan schedule:list
```

**Solutions:**

1. **Add Cron Entry:**
```bash
sudo crontab -e -u wudms
# Add:
* * * * * cd /var/www/wudms/current/server && php artisan schedule:run >> /dev/null 2>&1
```

2. **Check Cron Logs:**
```bash
grep CRON /var/log/syslog | tail -20
```

---

## Performance Issues

### Problem: Slow Page Load

**Diagnostics:**
```bash
# Check Opcache status
php -r "print_r(opcache_get_status());"

# Profile API requests
php artisan route:list --json | jq '.[] | select(.uri | contains("api"))'

# Database query profiling
DB::enableQueryLog();
// ... perform operation
dd(DB::getQueryLog());
```

**Solutions:**

1. **Enable Opcache:**
```ini
# /etc/php/8.4/fpm/php.ini
opcache.enable=1
opcache.memory_consumption=256
opcache.max_accelerated_files=20000
opcache.validate_timestamps=0  # Production only
```

2. **Cache Configuration:**
```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

3. **Database Query Optimization:**
```php
// Use select() to limit columns
Document::select('id', 'title', 'reference_number')->get();

// Use chunk() for large datasets
Document::chunk(100, function ($documents) {
    // Process batch
});
```

4. **Enable Redis Cache:**
```ini
# .env
CACHE_DRIVER=redis
SESSION_DRIVER=redis
QUEUE_CONNECTION=redis
```

### Problem: High Memory Usage

**Diagnostics:**
```bash
# Check PHP memory limit
php -i | grep memory_limit

# Monitor process memory
ps aux --sort=-%mem | head -10

# Check Laravel memory usage
php artisan about
```

**Solutions:**

1. **Increase PHP Memory:**
```ini
# /etc/php/8.4/fpm/php.ini
memory_limit = 256M
```

2. **Optimize Eloquent Queries:**
```php
// Use cursor() instead of get() for large datasets
Document::cursor()->each(function ($doc) {
    // Process one at a time
});

// Unset variables
unset($largeArray);
gc_collect_cycles();  // Force garbage collection
```

---

## Frontend Issues

### Problem: Blank Page / White Screen

**Diagnostics:**
```bash
# Check browser console for errors
# Check Vite build errors
npm run build

# Check nginx configuration
sudo nginx -t

# Check static files served correctly
curl -I https://dms.wu.edu.et/assets/index.js
```

**Solutions:**

1. **Rebuild Frontend:**
```bash
cd client
rm -rf dist node_modules
npm install
npm run build
```

2. **Check Base URL:**
```javascript
// client/.env.production
VITE_API_BASE_URL=https://dms.wu.edu.et/api/v1
```

3. **Clear Browser Cache:**
```bash
# Force hard reload in browser: Ctrl+Shift+R
```

### Problem: API Calls Fail from Frontend

**Diagnostics:**
```bash
# Check browser Network tab
# Look for CORS errors, 401/403 responses

# Test API directly
curl -X POST https://dms.wu.edu.et/api/v1/auth/login \
  -H "Content-Type: application/json" \
  -d '{"email":"test@wu.edu.et","password":"password"}'
```

**Solutions:**

1. **Check Sanctum Configuration:**
```php
// config/sanctum.php
'stateful' => explode(',', env('SANCTUM_STATEFUL_DOMAINS', 'dms.wu.edu.et')),
```

2. **Get CSRF Token:**
```javascript
// Frontend must call this first
await axios.get('/sanctum/csrf-cookie');
```

3. **Check Credentials Flag:**
```javascript
// axios configuration
axios.defaults.withCredentials = true;
```

---

## Security Alerts

### Problem: Brute Force Attack Detected

**Immediate Actions:**
```bash
# Check fail2ban bans
sudo fail2ban-client status wudms-auth

# Ban specific IP manually
sudo fail2ban-client set wudms-auth banip <IP_ADDRESS>

# Review login attempts
grep "POST /api/v1/auth/login" /var/log/nginx/wudms_access.log | \
  awk '{print $1}' | sort | uniq -c | sort -rn | head -20
```

### Problem: Suspicious Audit Trail Entries

**Investigation:**
```php
php artisan tinker
>>> AuditEvent::where('action', 'like', '%unauthorized%')
    ->with('actor')
    ->latest()
    ->take(50)
    ->get();
```

**Response:**
```bash
# Lock compromised account
DB::table('users')->where('id', $userId)->update(['active' => 0]);

# Invalidate sessions
DB::table('sessions')->where('user_id', $userId)->delete();

# Force password reset
DB::table('users')->where('id', $userId)->update([
    'password_reset_required' => 1
]);
```

---

## Emergency Procedures

### Complete System Restore

```bash
# 1. Stop all services
sudo systemctl stop nginx
sudo supervisorctl stop all

# 2. Restore database
gunzip < /var/backups/wudms/database/wudms_LATEST.sql.gz | \
  mysql -u wudms_app -p wollo_dms

# 3. Restore storage
rsync -avz backup-server:/backups/wudms/storage/ \
  /var/www/wudms/shared/storage/

# 4. Clear caches
php artisan cache:clear
php artisan config:clear

# 5. Restart services
sudo supervisorctl start all
sudo systemctl start nginx

# 6. Verify
curl -I https://dms.wu.edu.et
```

### Maintenance Mode

```bash
# Enable maintenance mode
php artisan down --message="Scheduled maintenance in progress"

# Perform maintenance
# ...

# Disable maintenance mode
php artisan up
```

---

## Contact & Escalation

**Level 1 Support:** IT Helpdesk  
**Level 2 Support:** System Administrators  
**Level 3 Support:** Development Team  

**Emergency Contact:** [On-Call Engineer Phone]

---

**Document Version:** 1.0  
**Next Review:** 2025-02-22
