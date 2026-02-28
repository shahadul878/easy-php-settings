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
- Deploys to WordPress.org SVN (trunk and tag)
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

**Note:** This workflow will skip if the commit message contains `[skip ci]`

### 3. `validate.yml`
**Triggers:** On push to main/master/develop branches and on pull requests

**What it does:**
- Validates `readme.txt` format and required headers
- Validates plugin header in main plugin file
- Runs PHPCS (if available)
- Checks for PHP syntax errors
- Validates version consistency between plugin file and readme.txt

## Setup Instructions

### 1. Configure WordPress.org Secrets

1. Go to your GitHub repository
2. Navigate to **Settings** → **Secrets and variables** → **Actions**
3. Add the following secrets:
   - `WPORG_USERNAME`: Your WordPress.org username
   - `WPORG_PASSWORD`: Your WordPress.org application password
     - Get this from: https://wordpress.org/profile/applications
     - Create a new application password with SVN access

### 2. First-time SVN Setup

If this is your first deployment, you may need to initialize the SVN repository structure:

```bash
svn checkout https://plugins.svn.wordpress.org/easy-php-settings/ /tmp/svn
cd /tmp/svn
mkdir -p trunk tags assets
svn add trunk tags assets
svn commit -m "Initial structure"
```

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
git push origin main
git push origin v1.0.5
```

The workflow will automatically:
- Extract version from tag
- Generate changelog
- Deploy to WordPress.org
- Create GitHub release

## Troubleshooting

### Deployment fails with authentication error
- Verify `WPORG_USERNAME` and `WPORG_PASSWORD` secrets are set correctly
- Ensure the application password has SVN access enabled
- Check that your WordPress.org account has commit access to the plugin

### Changelog is empty
- Ensure you have previous tags in the repository
- Check that commits follow conventional format (the workflow uses commit messages)

### Files are missing in deployment
- Check `.distignore` file - listed files/folders are excluded
- Verify files exist in the repository before tagging

### Version mismatch warnings
- Ensure version in `class-easy-php-settings.php` matches `Stable tag` in `readme.txt`
- Update both before creating a release tag

## Notes

- The workflow uses `.distignore` to exclude files from deployment
- Assets (banners, icons, screenshots) should be in the `assets/` directory
- The changelog is generated from git commit messages since the last tag
- SVN tags are created automatically from the trunk version
