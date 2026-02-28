#!/bin/bash
# Prepare plugin for release
# Usage: ./scripts/prepare-release.sh [version]

set -e

VERSION=${1:-""}

if [ -z "$VERSION" ]; then
    echo "Usage: $0 <version>"
    echo "Example: $0 1.0.5"
    exit 1
fi

echo "Preparing release version $VERSION"
echo ""

# Update version in main plugin file
echo "📝 Updating version in class-easy-php-settings.php..."
sed -i.tmp "s/Version:     [0-9.]*/Version:     ${VERSION}/" class-easy-php-settings.php
rm -f class-easy-php-settings.php.tmp

# Update stable tag in readme.txt
echo "📝 Updating stable tag in readme.txt..."
sed -i.tmp "s/Stable tag: .*/Stable tag: ${VERSION}/" readme.txt
rm -f readme.txt.tmp

# Generate changelog
echo "📝 Generating changelog..."
./scripts/generate-changelog.sh "$VERSION"

echo ""
echo "✅ Release preparation complete!"
echo ""
echo "Next steps:"
echo "1. Review the changes:"
echo "   git diff"
echo ""
echo "2. Commit the changes:"
echo "   git add class-easy-php-settings.php readme.txt"
echo "   git commit -m \"Release version ${VERSION}\""
echo ""
echo "3. Create and push tag:"
echo "   git tag v${VERSION}"
echo "   git push origin main"
echo "   git push origin v${VERSION}"
echo ""
echo "The GitHub Action will automatically deploy to WordPress.org"
