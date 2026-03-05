# GitHub Actions Workflows

This directory contains GitHub Actions workflows for the Easy PHP Settings plugin.

## Workflows

### 1. `deploy-to-wordpress-org.yml`
**Triggers:** When a tag starting with `v` is pushed (e.g., `v1.0.5`)

**What it does:**
- Extracts version number from the tag
- Generates changelog from git commits since the previous tag
- Updates `readme.txt` with the new version and changelog
- Updates version in the main plugin file
- Creates a deployment package (excluding files in `.distignore`)
- Deploys plugin code to WordPress.org SVN (trunk and tag)
- Deploys assets from `.wordpress-org/` to SVN `assets/`
- Creates a GitHub release with the zip file

**Required Secrets:**
- `WPORG_USERNAME`: Your WordPress.org username
- `WPORG_PASSWORD`: Your WordPress.org application password (not your account password)

**How to use:**
1. Update version in `class-easy-php-settings.php` and `readme.txt`
2. Commit your changes
3. Create and push a tag: `git tag v1.0.5 && git push origin v1.0.5`
4. The workflow will automatically deploy to WordPress.org

### 2. `update-changelog.yml`
**Triggers:** When `readme.txt` or `class-easy-php-settings.php` is updated on main/master branch

**What it does:**
- Automatically updates the changelog in `readme.txt` if a new version is detected
- Commits the changelog update back to the repository

**Required Secrets:**
- `GH_TOKEN`: A GitHub personal access token with `contents: write` permission

**Note:** This workflow will skip if the commit message contains `[skip ci]`

### 3. `validate.yml`
**Triggers:** On push to main/master/develop branches and on pull requests

**What it does:**
- Validates `readme.txt` format and required headers
- Validates plugin header in main plugin file
- Checks for PHP syntax errors across all `.php` files
- Validates version consistency between plugin file and readme.txt
- Validates required plugin files and module structure exist

## Plugin Structure

```
easy-php-settings/
├── .distignore                 # Files excluded from WP.org distribution
├── .github/workflows/          # CI/CD workflows
├── .gitignore
├── .wordpress-org/             # WP.org plugin page assets (banners, icons, screenshots)
├── class-easy-php-settings.php # Main plugin entry point
├── css/admin-styles.css        # Admin stylesheet
├── includes/                   # Helper classes (12 files)
├── js/admin.js                 # Admin JavaScript
├── languages/                  # Translation files
├── modules/                    # Feature modules (6 files)
├── readme.txt                  # WordPress.org readme
└── view/phpinfo-table.phtml    # PHP info display template
```

## Setup Instructions

### 1. Configure WordPress.org Secrets

1. Go to your GitHub repository
2. Navigate to **Settings** → **Secrets and variables** → **Actions**
3. Add the following secrets:
   - `WPORG_USERNAME`: Your WordPress.org username
   - `WPORG_PASSWORD`: Your WordPress.org application password
     - Get this from: https://wordpress.org/profile/applications
     - Create a new application password with SVN access

### 2. Configure GitHub Token

For the changelog workflow:
1. Create a personal access token at https://github.com/settings/tokens
2. Grant `contents: write` permission
3. Add it as a secret named `GH_TOKEN` in your repository settings

### 3. Tagging and Releasing

To create a new release:

```bash
# 1. Update version in class-easy-php-settings.php
# 2. Update stable tag in readme.txt
# 3. Commit changes
git add .
git commit -m "Release version 1.0.5"

# 4. Create and push tag
git tag v1.0.5
git push origin master
git push origin v1.0.5
```

The workflow will automatically:
- Extract version from tag
- Generate changelog
- Deploy to WordPress.org (trunk, tag, and assets)
- Create GitHub release

## Troubleshooting

### Deployment fails with authentication error
- Verify `WPORG_USERNAME` and `WPORG_PASSWORD` secrets are set correctly
- Ensure the application password has SVN access enabled
- Check that your WordPress.org account has commit access to the plugin

### Changelog is empty
- Ensure you have previous tags in the repository
- Check that commits follow a descriptive format (the workflow uses commit messages)

### Files are missing in deployment
- Check `.distignore` file — listed files/folders are excluded from the ZIP
- Verify files exist in the repository before tagging

### Assets not showing on WordPress.org
- Ensure images are in `.wordpress-org/` directory
- File names must be exact: `banner-772x250.png`, `icon-128x128.png`, etc.
- PNG and JPEG formats only

### Version mismatch warnings
- Ensure version in `class-easy-php-settings.php` matches `Stable tag` in `readme.txt`
- Update both before creating a release tag
