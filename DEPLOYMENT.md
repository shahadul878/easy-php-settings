# Deployment Guide

This guide explains how to deploy the Easy PHP Settings plugin to WordPress.org using GitHub Actions.

## Prerequisites

1. **WordPress.org Account**: You need a WordPress.org account with commit access to the plugin repository
2. **Application Password**: Create an application password with SVN access:
   - Go to: https://wordpress.org/profile/applications
   - Create a new application password
   - Make sure it has SVN access enabled

## Initial Setup

### 1. Configure GitHub Secrets

1. Go to your GitHub repository
2. Navigate to **Settings** → **Secrets and variables** → **Actions**
3. Click **New repository secret**
4. Add the following secrets:

   - **Name**: `WPORG_USERNAME`
     - **Value**: Your WordPress.org username

   - **Name**: `WPORG_PASSWORD`
     - **Value**: Your WordPress.org application password (NOT your account password)

### 2. Verify SVN Repository Structure

The WordPress.org SVN repository should have this structure:
```
easy-php-settings/
├── trunk/
├── tags/
└── assets/
```

If this is your first deployment, you may need to initialize it manually:
```bash
svn checkout https://plugins.svn.wordpress.org/easy-php-settings/ /tmp/svn
cd /tmp/svn
mkdir -p trunk tags assets
svn add trunk tags assets
svn commit -m "Initial structure"
```

## Release Process

### Option 1: Using the Helper Script (Recommended)

1. **Prepare the release:**
   ```bash
   ./scripts/prepare-release.sh 1.0.5
   ```
   
   This will:
   - Update version in `class-easy-php-settings.php`
   - Update stable tag in `readme.txt`
   - Generate changelog from git commits

2. **Review the changes:**
   ```bash
   git diff
   ```

3. **Commit and tag:**
   ```bash
   git add class-easy-php-settings.php readme.txt
   git commit -m "Release version 1.0.5"
   git tag v1.0.5
   git push origin main
   git push origin v1.0.5
   ```

4. **Monitor the deployment:**
   - Go to the **Actions** tab in GitHub
   - Watch the "Deploy to WordPress.org" workflow
   - Check for any errors

### Option 2: Manual Process

1. **Update version in plugin file:**
   ```bash
   # Edit class-easy-php-settings.php
   # Change: Version:     1.0.4
   # To:     Version:     1.0.5
   ```

2. **Update stable tag in readme.txt:**
   ```bash
   # Edit readme.txt
   # Change: Stable tag: 1.0.4
   # To:     Stable tag: 1.0.5
   ```

3. **Generate changelog:**
   ```bash
   ./scripts/generate-changelog.sh 1.0.5
   ```

4. **Commit and tag:**
   ```bash
   git add .
   git commit -m "Release version 1.0.5"
   git tag v1.0.5
   git push origin main
   git push origin v1.0.5
   ```

## What Happens During Deployment

When you push a tag (e.g., `v1.0.5`), the GitHub Action will:

1. ✅ **Extract version** from the tag name
2. ✅ **Generate changelog** from commits since the previous tag
3. ✅ **Update readme.txt** with new version and changelog
4. ✅ **Update plugin version** in main plugin file
5. ✅ **Create deployment package** (excluding files in `.distignore`)
6. ✅ **Deploy to WordPress.org SVN trunk**
7. ✅ **Create SVN tag** for the version
8. ✅ **Create GitHub release** with zip file

## File Exclusions

Files excluded from deployment (defined in `.distignore`):
- Development files (`.git`, `.github`, `composer.json`, etc.)
- Test files (`tests/`, `phpunit.xml`, etc.)
- Build tools (`node_modules/`, `vendor/`, etc.)
- Documentation (`README.md`, `CHANGELOG.md`, etc.)
- Logs and temporary files

## Assets

Plugin assets (banners, icons, screenshots) should be placed in the `assets/` directory:
- `banner-772x250.png` - Plugin banner (772x250px)
- `banner-1544x500.png` - Plugin banner (1544x500px)
- `icon-128x128.png` - Plugin icon (128x128px)
- `icon-256x256.png` - Plugin icon (256x256px)
- `screenshot-1.png` through `screenshot-5.png` - Plugin screenshots

These will be automatically deployed to the WordPress.org SVN `assets/` directory.

## Troubleshooting

### Authentication Errors

**Error**: `svn: E170001: Authentication failed`

**Solution**:
- Verify `WPORG_USERNAME` and `WPORG_PASSWORD` secrets are set correctly
- Ensure the application password has SVN access enabled
- Check that your WordPress.org account has commit access

### Missing Files in Deployment

**Issue**: Some files are missing in the WordPress.org version

**Solution**:
- Check `.distignore` - listed files/folders are excluded
- Verify files exist in the repository before tagging
- Check the workflow logs for exclusion messages

### Version Mismatch

**Issue**: Version in plugin file doesn't match readme.txt

**Solution**:
- Use the helper script: `./scripts/prepare-release.sh <version>`
- Or manually update both files before creating a tag

### Changelog is Empty

**Issue**: Changelog section is empty or missing

**Solution**:
- Ensure you have previous tags in the repository
- Check git commit history: `git log --oneline`
- Manually add changelog entry if needed

### SVN Tag Already Exists

**Issue**: Workflow fails because tag already exists

**Solution**:
- The workflow automatically removes and recreates existing tags
- If it still fails, manually delete the tag in SVN:
  ```bash
  svn rm https://plugins.svn.wordpress.org/easy-php-settings/tags/1.0.5 -m "Remove existing tag"
  ```

## Best Practices

1. **Version Numbering**: Follow semantic versioning (MAJOR.MINOR.PATCH)
2. **Changelog**: Write clear, descriptive commit messages (they become changelog entries)
3. **Testing**: Test the plugin locally before releasing
4. **Review**: Always review the generated changelog before pushing the tag
5. **Staging**: Consider testing the deployment process with a pre-release version first

## Workflow Files

- `.github/workflows/deploy-to-wordpress-org.yml` - Main deployment workflow
- `.github/workflows/update-changelog.yml` - Automatic changelog updates
- `.github/workflows/validate.yml` - Plugin validation on push/PR

## Support

If you encounter issues:
1. Check the GitHub Actions logs for detailed error messages
2. Verify all secrets are configured correctly
3. Ensure your WordPress.org account has proper permissions
4. Review the `.distignore` file to ensure important files aren't excluded
