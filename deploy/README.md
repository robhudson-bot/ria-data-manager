# GitHub Webhook Auto-Deployment System

Automated deployment system for RIA Data Manager plugin using GitHub webhooks.

## 📋 Quick Overview

**What it does:** Automatically updates your WordPress plugin on the-ria.ca when you push to GitHub.

**How it works:**
1. You push changes to GitHub
2. GitHub sends webhook notification
3. Server downloads latest code
4. Creates backup automatically
5. Updates plugin files (preserves config.php)
6. Sends email notification

---

## 🚀 Installation Guide

### Step 1: Generate Webhook Secret

1. Go to https://www.random.org/strings/?num=1&len=64&upperalpha=on&loweralpha=on&digits=on
2. Copy the generated random string
3. Save it somewhere safe (you'll need it twice)

### Step 2: Prepare Files on Your Computer

1. **Create config.php:**
   - In `C:\Users\RHudson\Desktop\GitHub\ria-data-manager\.deploy\`
   - Copy `config.sample.php` → `config.php`
   - Edit `config.php`:
     ```php
     define('DEPLOY_WEBHOOK_SECRET', 'paste_your_random_string_here');
     ```
   - Leave other settings as-is (they'll work with default paths)

2. **Add .deploy folder to .gitignore:**
   - Open `C:\Users\RHudson\Desktop\GitHub\ria-data-manager\.gitignore`
   - Add this line at the bottom:
     ```
     .deploy/config.php
     ```
   - This prevents your secret key from being uploaded to GitHub

3. **Commit and push to GitHub:**
   - Use GitHub Desktop to commit:
     - Files changed: `.deploy/` folder, `.gitignore`
     - Commit message: "Add auto-deployment webhook system"
   - Push to GitHub

### Step 3: Upload to SiteGround via FTP

1. **Connect to FTP:**
   - Host: the-ria.ca
   - Port: 21
   - Use your SiteGround credentials

2. **Create .deploy folder on server:**
   - Navigate to `/public_html/`
   - Create new folder: `.deploy`

3. **Upload files to `/public_html/.deploy/`:**
   - `webhook.php`
   - `deployer.php`
   - `config.php` (the one you edited, NOT config.sample.php)

4. **Set permissions:**
   - Set folder `.deploy` to 755
   - Set all PHP files to 644

### Step 4: Configure GitHub Webhook

1. Go to: https://github.com/robhudson-bot/ria-data-manager/settings/hooks

2. Click "Add webhook"

3. Fill in:
   - **Payload URL:** `https://the-ria.ca/.deploy/webhook.php`
   - **Content type:** `application/json`
   - **Secret:** Paste your random string from Step 1
   - **Which events:** Select "Just the push event"
   - **Active:** ✅ Checked

4. Click "Add webhook"

5. GitHub will test the webhook immediately. Check:
   - Green checkmark = success ✅
   - Red X = problem (see troubleshooting below)

---

## ✅ Testing Your Deployment

### Test 1: Manual Webhook Test

1. In GitHub webhook settings, scroll down
2. Find your webhook
3. Click "Recent Deliveries" tab
4. Click "Redeliver" on the test payload
5. Check response (should show success)

### Test 2: Real Code Push

1. Make a small change locally (e.g., edit `test.txt`)
2. Commit with message: "Test auto-deployment"
3. Push to GitHub
4. Wait ~30 seconds
5. Check your email for deployment notification
6. Verify change on live site

---

## 📧 Email Notifications

You'll receive emails for:
- ✅ **Successful deployments** (green)
- ❌ **Failed deployments** (red)

Each email includes:
- Commit details (who, what, when)
- Deployment status
- Error messages (if failed)

**No email received?**
- Check spam folder
- Verify `rob.hudson@the-ria.ca` in config.php
- Check log file: `/public_html/wp-content/uploads/.deploy-log.txt`

---

## 🔍 Monitoring & Logs

### Log File Location
`/public_html/wp-content/uploads/.deploy-log.txt`

View via FTP or cPanel File Manager to see:
- Webhook requests received
- Deployment steps
- Errors and warnings
- Timestamp for each action

### Backup Files
Automatic backups stored in:
`/public_html/wp-content/uploads/.deploy-backups/`

- Keeps last 5 backups automatically
- Named: `ria-data-manager_backup_YYYY-MM-DD_HH-MM-SS.zip`

---

## 🛡️ Security Features

✅ **Signature Verification:** Only valid GitHub requests are processed  
✅ **Secret Key:** 64-character random string required  
✅ **Config Protection:** `config.php` never committed to Git  
✅ **Automatic Backups:** Previous version saved before each update  
✅ **Rollback on Failure:** Automatically restores backup if deployment fails  
✅ **Hidden Directory:** `.deploy` folder hidden from web browsing  

---

## 🚫 Skipping Deployment

Add `[skip deploy]` or `[no deploy]` to your commit message to prevent automatic deployment:

```
git commit -m "Update docs only [skip deploy]"
```

Useful for:
- Documentation changes
- README updates
- Non-code commits

---

## 🔧 Troubleshooting

### Webhook shows red X in GitHub

**Check 1:** Verify URL
- Should be: `https://the-ria.ca/.deploy/webhook.php`
- No typos, correct domain

**Check 2:** File uploaded correctly
- Log into FTP
- Verify `webhook.php` exists at `/public_html/.deploy/webhook.php`

**Check 3:** Permissions
- Folder `.deploy`: 755
- Files: 644

**Check 4:** Secret key matches
- GitHub webhook secret = `config.php` secret
- No extra spaces or characters

### Deployment fails

**Check log file:**
```
/public_html/wp-content/uploads/.deploy-log.txt
```

**Common issues:**

1. **"Plugin directory not found"**
   - Verify path in `config.php`
   - Check: `/public_html/wp-content/plugins/ria-data-manager/`

2. **"Not writable"**
   - Fix folder permissions:
   - Plugin folder: 755
   - Files: 644

3. **"ZipArchive not available"**
   - Contact SiteGround support
   - Ask to enable PHP zip extension

4. **"Download failed"**
   - Repository might be private
   - Check repository exists at GitHub
   - Verify branch name is 'main'

### Config.php not preserved

- Should work automatically
- Verify `config.php` exists in `/public_html/wp-content/plugins/ria-data-manager/config/`
- Check it's not in `.gitignore` (only `.deploy/config.php` should be)

### No email notifications

1. Verify email in `config.php`
2. Check spam folder
3. SiteGround might block emails - contact support
4. Disable temporarily: Set `DEPLOY_ENABLE_NOTIFICATIONS` to `false`

---

## 🔄 Manual Rollback

If you need to manually restore a previous version:

1. **Via FTP:**
   - Navigate to `/public_html/wp-content/uploads/.deploy-backups/`
   - Download latest backup ZIP
   - Extract locally
   - Upload to `/public_html/wp-content/plugins/ria-data-manager/`
   - Overwrite all files

2. **Via cPanel:**
   - File Manager → `wp-content/uploads/.deploy-backups/`
   - Right-click backup → Extract
   - Copy files to plugin folder

---

## 📁 File Structure

```
/public_html/
├── .deploy/                          [Hidden deployment system]
│   ├── webhook.php                  (Receives GitHub webhooks)
│   ├── deployer.php                 (Handles deployment logic)
│   └── config.php                   (Your settings - NEVER commit!)
│
├── wp-content/
│   ├── plugins/
│   │   └── ria-data-manager/        [Your plugin - gets updated]
│   │       ├── includes/
│   │       ├── assets/
│   │       ├── config/
│   │       │   └── config.php       (Preserved during updates)
│   │       └── ...
│   │
│   └── uploads/
│       ├── .deploy-temp/            (Temporary download/extract)
│       ├── .deploy-backups/         (Automatic backups - last 5 kept)
│       └── .deploy-log.txt          (Deployment log)
```

---

## 🎯 What Gets Updated

### ✅ Updated Automatically:
- All PHP files
- JavaScript & CSS
- Templates
- Documentation
- Dependencies

### ❌ NOT Updated (Preserved):
- `config/config.php` (your database credentials)
- WordPress uploads folder
- User data

---

## ⚙️ Advanced Configuration

### Change Branch

Edit `config.php`:
```php
define('DEPLOY_BRANCH', 'development');  // Deploy from different branch
```

Update GitHub webhook to trigger on that branch.

### Disable Email Notifications

Edit `config.php`:
```php
define('DEPLOY_ENABLE_NOTIFICATIONS', false);
```

### Keep More Backups

Edit `deployer.php`, line ~255:
```php
private function cleanup_old_backups($keep = 10) {  // Change from 5 to 10
```

---

## 📞 Support

**Deployment Issues:**
- Check log file first: `/wp-content/uploads/.deploy-log.txt`
- Review this README
- Check GitHub webhook "Recent Deliveries" tab

**Server Issues:**
- Contact SiteGround support
- Mention: PHP permissions, zip extension, or file access issues

---

## 🎉 Success Checklist

- [ ] Random secret generated
- [ ] `config.php` created and configured
- [ ] `.deploy/config.php` added to `.gitignore`
- [ ] Files pushed to GitHub
- [ ] `.deploy/` folder created on server via FTP
- [ ] All 3 PHP files uploaded to server
- [ ] Permissions set correctly (755/644)
- [ ] GitHub webhook created
- [ ] Webhook shows green checkmark
- [ ] Test deployment successful
- [ ] Email notification received

**Once all checked, you're done! 🚀**

Push to GitHub → Plugin updates automatically!
