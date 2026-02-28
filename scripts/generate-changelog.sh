#!/bin/bash
# Generate changelog from git commits
# Usage: ./scripts/generate-changelog.sh [version] [previous-tag]

VERSION=${1:-$(grep "Stable tag:" readme.txt | sed 's/Stable tag: //' | tr -d ' ')}
PREVIOUS_TAG=${2:-$(git describe --tags --abbrev=0 HEAD^ 2>/dev/null || echo "")}

echo "Generating changelog for version: $VERSION"
echo "Previous tag: ${PREVIOUS_TAG:-'none (using all commits)'}"
echo ""

if [ -z "$PREVIOUS_TAG" ]; then
    # No previous tag, get last 50 commits
    CHANGELOG=$(git log --pretty=format:"* %s" -50 --reverse)
else
    # Get commits since previous tag
    CHANGELOG=$(git log ${PREVIOUS_TAG}..HEAD --pretty=format:"* %s" --reverse)
fi

if [ -z "$CHANGELOG" ]; then
    echo "No commits found. Exiting."
    exit 1
fi

DATE=$(date +"%B %d, %Y")

# Check if changelog section exists
if grep -q "== Changelog ==" readme.txt; then
    # Insert new version changelog after "== Changelog =="
    sed -i.tmp "/== Changelog ==/a\\
\\
= ${VERSION} =\\
Released: ${DATE}\\
\\
${CHANGELOG}\\
" readme.txt
    rm -f readme.txt.tmp
else
    # Add changelog section at the end
    echo "" >> readme.txt
    echo "== Changelog ==" >> readme.txt
    echo "" >> readme.txt
    echo "= ${VERSION} =" >> readme.txt
    echo "Released: ${DATE}" >> readme.txt
    echo "" >> readme.txt
    echo "$CHANGELOG" >> readme.txt
fi

echo "✅ Changelog updated in readme.txt"
echo ""
echo "Preview:"
echo "---"
grep -A 20 "= ${VERSION} =" readme.txt | head -25
