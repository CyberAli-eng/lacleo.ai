#!/bin/bash

# Configuration
# Adjust these URLs and branches if needed
REPO_APP_URL="https://github.com/LaCleoAI/app.git"
REPO_APP_BASE_BRANCH="stage"

REPO_API_URL="https://github.com/LaCleoAI/api.git"
REPO_API_BASE_BRANCH="main"

REPO_ACCOUNTS_URL="https://github.com/LaCleoAI/accounts.git"
REPO_ACCOUNTS_BASE_BRANCH="main"

# Base Directory (The mono-repo root)
SOURCE_DIR="$(pwd)"
STAGING_DIR="${SOURCE_DIR}/../lacleo_legacy_sync_staging"

# Text Colors
GREEN='\033[0;32m'
BLUE='\033[0;34m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
NC='\033[0m' # No Color

# Generate a unique branch name
TIMESTAMP=$(date +%Y%m%d-%H%M)
SYNC_BRANCH="sync/update-${TIMESTAMP}"

echo -e "${BLUE}=== Lacleo Legacy Sync Tool (PR Mode) ===${NC}"
echo "This script will sync your code to a NEW BRANCH so your CTO can review it via Pull Request."
echo "Target Branch Name: ${SYNC_BRANCH}"
echo "Staging Directory: ${STAGING_DIR}"
echo ""

mkdir -p "$STAGING_DIR"

# Function to sync a component
sync_repo() {
    local NAME=$1
    local URL=$2
    local BASE_BRANCH=$3
    local SOURCE_SUBDIR=$4

    echo -e "${YELLOW}>>> Processing [${NAME}]...${NC}"
    
    TARGET_PATH="${STAGING_DIR}/${NAME}"

    # 1. Clone (or update if exists)
    if [ -d "$TARGET_PATH/.git" ]; then
        echo "Updating existing clone..."
        cd "$TARGET_PATH"
        git fetch origin
        git checkout "$BASE_BRANCH"
        git pull origin "$BASE_BRANCH"
    else
        echo "Cloning ${URL} (Base: ${BASE_BRANCH})..."
        git clone -b "$BASE_BRANCH" "$URL" "$TARGET_PATH" --depth 1
    fi
    
    cd "$TARGET_PATH"

    # 2. Create the new Feature Branch
    echo "Creating branch ${SYNC_BRANCH}..."
    git checkout -b "$SYNC_BRANCH"

    # 3. Sync Files
    cd "$SOURCE_DIR"
    echo "Syncing files..."
    rsync -av --delete \
        --exclude='.git' \
        --exclude='.github' \
        --exclude='node_modules' \
        --exclude='vendor' \
        --exclude='.env' \
        --exclude='.DS_Store' \
        --exclude='storage/*.key' \
        "${SOURCE_SUBDIR}/" "${TARGET_PATH}/"

    # 4. Commit and Push
    cd "$TARGET_PATH"
    
    if [[ -n $(git status -s) ]]; then
        echo -e "${GREEN}Changes detected in [${NAME}].${NC}"
        git add .
        git commit -m "Sync from Mono-repo: ${TIMESTAMP}"
        
        echo "Pushing branch to origin..."
        # We try to push. If it fails (auth), we warn the user.
        if git push origin "$SYNC_BRANCH"; then
            echo -e "${GREEN}Successfully pushed [${SYNC_BRANCH}] for [${NAME}]!${NC}"
            
            # Construct PR URL (Github specific)
            # URL format: https://github.com/USER/REPO/compare/BASE...HEAD?expand=1
            # We need to strip .git from URL for the web link
            WEB_URL=${URL%.git}
            PR_LINK="${WEB_URL}/compare/${BASE_BRANCH}...${SYNC_BRANCH}?expand=1"
            
            echo -e "${YELLOW}Create PR here: ${PR_LINK}${NC}"
        else
            echo -e "${RED}Failed to push. Check your git permissions.${NC}"
            echo "You can manually push from: ${TARGET_PATH}"
        fi
    else
        echo -e "${BLUE}No changes detected for [${NAME}]. It is up to date.${NC}"
    fi

    cd "$SOURCE_DIR"
    echo ""
}

# Execute Syncs
sync_repo "app" "$REPO_APP_URL" "$REPO_APP_BASE_BRANCH" "app"
sync_repo "api" "$REPO_API_URL" "$REPO_API_BASE_BRANCH" "api"
sync_repo "accounts" "$REPO_ACCOUNTS_URL" "$REPO_ACCOUNTS_BASE_BRANCH" "accounts"

echo -e "${BLUE}=== Sync Complete ===${NC}"
echo "Open the PR links above to let your CTO review and merge the changes."
