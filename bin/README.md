# Backup Manager CLI Scripts

Quick and easy command-line tools for managing backup captions.

## Location
`extension/git_manager/bin/php/`

## Scripts

### 📦 backup-create.php
Create new backup captions.

**Usage:**
```bash
php backup-create.php [type] [options]
```

**Types:**
- `full` - Create full backup (DB + var)
- `db` - Create database backup only
- `var` - Create var directory backup only

**Options:**
- `-d "description"` - Add description
- `-e` - Encrypt backup
- `-p "passphrase"` - Encryption passphrase (required with -e)

**Examples:**
```bash
# Simple full backup
php backup-create.php full

# Database with description
php backup-create.php db -d "Before update"

# Encrypted full backup
php backup-create.php full -e -p "mySecretPass123" -d "Encrypted backup"

# Files only with description
php backup-create.php var -d "Files only"
```

---

### 📋 backup-list.php
List all backup captions.

**Usage:**
```bash
php backup-list.php [options]
```

**Options:**
- `-v` - Verbose (show file details)
- `-s` - Show sizes in human readable format

**Examples:**
```bash
# Simple list
php backup-list.php

# Detailed list with sizes
php backup-list.php -v -s
```

---

### ℹ️ backup-info.php
Show detailed information about a specific backup.

**Usage:**
```bash
php backup-info.php <timestamp>
```

**Examples:**
```bash
php backup-info.php 2026-06-21_20-20-49
```

---

### 🗑️ backup-delete.php
Delete backup captions.

**Usage:**
```bash
php backup-delete.php <timestamp> [options]
```

**Options:**
- `-f` - Force deletion without confirmation
- `-a` - Delete all backups (use with caution!)

**Examples:**
```bash
# Delete specific backup (with confirmation)
php backup-delete.php 2026-06-21_20-20-49

# Force delete without confirmation
php backup-delete.php 2026-06-21_20-20-49 -f

# Delete all backups (requires typing 'DELETE ALL')
php backup-delete.php -a
```

---

## Quick Reference

```bash
# Create full backup with encryption
php backup-create.php full -e -p "pass123" -d "Production backup"

# List all backups
php backup-list.php -v -s

# Get details about specific backup
php backup-info.php 2026-06-21_20-20-49

# Delete old backup
php backup-delete.php 2026-06-21_20-20-49 -f
```

## Notes

- All scripts require eZ Publish environment
- Run from the eZ Publish root directory
- Encrypted backups require passphrase to decrypt
- Timestamps use format: YYYY-MM-DD_HH-MM-SS
- Scripts provide clear error messages and validation
