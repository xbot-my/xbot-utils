#!/bin/bash

# Laravel 项目清理脚本
# 清除所有运行时产生的文件和敏感数据

set -e  # 遇到错误时退出

# 颜色定义
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# 项目根目录
PROJECT_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

echo -e "${BLUE}================================${NC}"
echo -e "${BLUE}Laravel 项目清理脚本${NC}"
echo -e "${BLUE}================================${NC}"

# 确认执行
read -p "⚠️  此操作将删除项目中的所有敏感数据和运行时文件，确定继续吗？(y/N): " -n 1 -r
echo
if [[ ! $REPLY =~ ^[Yy]$ ]]; then
    echo -e "${YELLOW}操作已取消${NC}"
    exit 0
fi

echo -e "${GREEN}开始清理项目...${NC}"

# 1. 清除 storage 目录下的运行时文件
echo -e "${YELLOW}清理 storage 目录...${NC}"
if [ -d "storage" ]; then
    # 保留 storage 目录结构，但清除内容
    rm -rf storage/logs/*
    rm -rf storage/cache/*
    rm -rf storage/framework/cache/*
    rm -rf storage/framework/sessions/*
    rm -rf storage/framework/views/*
    rm -rf storage/framework/testing/*
    rm -rf storage/app/public/*
    rm -rf storage/debugbar/*
    echo -e "${GREEN}✓ storage 目录清理完成${NC}"
else
    echo -e "${YELLOW}⚠ storage 目录不存在${NC}"
fi

# 2. 清除 bootstrap/cache 目录
echo -e "${YELLOW}清理 bootstrap/cache 目录...${NC}"
if [ -d "bootstrap/cache" ]; then
    rm -rf bootstrap/cache/*
    echo -e "${GREEN}✓ bootstrap/cache 目录清理完成${NC}"
else
    echo -e "${YELLOW}⚠ bootstrap/cache 目录不存在${NC}"
fi

# 3. 清除敏感配置文件
echo -e "${YELLOW}清理敏感配置文件...${NC}"
if [ -f ".env" ]; then
    rm .env
    echo -e "${GREEN}✓ .env 文件已删除${NC}"
else
    echo -e "${YELLOW}⚠ .env 文件不存在${NC}"
fi

if [ -f ".env.local" ]; then
    rm .env.local
    echo -e "${GREEN}✓ .env.local 文件已删除${NC}"
fi

if [ -f ".env.production" ]; then
    rm .env.production
    echo -e "${GREEN}✓ .env.production 文件已删除${NC}"
fi

if [ -f ".env.staging" ]; then
    rm .env.staging
    echo -e "${GREEN}✓ .env.staging 文件已删除${NC}"
fi

if [ -f ".env.backup" ]; then
    rm .env.backup
    echo -e "${GREEN}✓ .env.backup 文件已删除${NC}"
fi

# 4. 清除数据库文件
echo -e "${YELLOW}清理数据库文件...${NC}"
if [ -f "database/database.sqlite" ]; then
    rm database/database.sqlite
    echo -e "${GREEN}✓ database/database.sqlite 已删除${NC}"
fi

if [ -f "database/testing.sqlite" ]; then
    rm database/testing.sqlite
    echo -e "${GREEN}✓ database/testing.sqlite 已删除${NC}"
fi

if [ -f "database/database.sqlite-shm" ]; then
    rm database/database.sqlite-shm
fi

if [ -f "database/database.sqlite-wal" ]; then
    rm database/database.sqlite-wal
fi

if [ -f "database/testing.sqlite-shm" ]; then
    rm database/testing.sqlite-shm
fi

if [ -f "database/testing.sqlite-wal" ]; then
    rm database/testing.sqlite-wal
fi

# 5. 清除 vendor 目录
echo -e "${YELLOW}清理 vendor 目录...${NC}"
if [ -d "vendor" ]; then
    rm -rf vendor
    echo -e "${GREEN}✓ vendor 目录已删除${NC}"
else
    echo -e "${YELLOW}⚠ vendor 目录不存在${NC}"
fi

# 6. 清除 node_modules 目录
echo -e "${YELLOW}清理 node_modules 目录...${NC}"
if [ -d "node_modules" ]; then
    rm -rf node_modules
    echo -e "${GREEN}✓ node_modules 目录已删除${NC}"
else
    echo -e "${YELLOW}⚠ node_modules 目录不存在${NC}"
fi

# 7. 清除编译后的前端文件
echo -e "${YELLOW}清理编译后的前端文件...${NC}"
if [ -f "public/css/app.css" ]; then
    rm -f public/css/app.css
fi
if [ -f "public/js/app.js" ]; then
    rm -f public/js/app.js
fi
if [ -f "public/mix-manifest.json" ]; then
    rm -f public/mix-manifest.json
fi
if [ -f "public/webpack.mix.js" ]; then
    rm -f public/webpack.mix.js
fi
if [ -d "public/build" ]; then
    rm -rf public/build
    echo -e "${GREEN}✓ 编译后的前端文件已清理${NC}"
fi

# 8. 清除测试相关文件
echo -e "${YELLOW}清理测试相关文件...${NC}"
if [ -d "tests/TestCase.php" ]; then
    # 只删除测试运行时生成的临时文件，保留测试用例
    find tests -name "*.tmp" -delete 2>/dev/null || true
    find tests -name "*.log" -delete 2>/dev/null || true
    rm -rf tests/_output 2>/dev/null || true
    rm -rf tests/_data/_generated 2>/dev/null || true
    echo -e "${GREEN}✓ 测试相关临时文件已清理${NC}"
fi

# 9. 清除其他可能的临时文件
echo -e "${YELLOW}清理其他临时文件...${NC}"
find . -name "*.log" -not -path "./.git/*" -not -path "./storage/logs/*" -delete 2>/dev/null || true
find . -name "*.tmp" -not -path "./.git/*" -delete 2>/dev/null || true
find . -name "*.temp" -not -path "./.git/*" -delete 2>/dev/null || true
find . -name ".DS_Store" -delete 2>/dev/null || true
find . -name "Thumbs.db" -delete 2>/dev/null || true

# 10. 清除 Composer 和 NPM 缓存文件
echo -e "${YELLOW}清理依赖管理缓存...${NC}"
rm -f composer.lock
rm -f package-lock.json
rm -f yarn.lock

# 11. 清除 IDE 相关文件（可选）
echo -e "${YELLOW}清理 IDE 相关文件...${NC}"
rm -rf .idea 2>/dev/null || true
rm -f .vscode/settings.json 2>/dev/null || true
rm -f .editorconfig 2>/dev/null || true

# 12. 清除 Git 相关的临时文件
echo -e "${YELLOW}清理 Git 临时文件...${NC}"
find .git -name "*.lock" -delete 2>/dev/null || true

# 13. 重新创建必要的目录结构
echo -e "${YELLOW}重建必要的目录结构...${NC}"
mkdir -p storage/logs
mkdir -p storage/cache
mkdir -p storage/framework/cache
mkdir -p storage/framework/sessions
mkdir -p storage/framework/views
mkdir -p storage/framework/testing
mkdir -p storage/app/public
chmod -R 755 storage
chmod -R 755 bootstrap/cache

echo -e "${GREEN}================================${NC}"
echo -e "${GREEN}项目清理完成！${NC}"
echo -e "${GREEN}================================${NC}"

echo -e "${BLUE}清理的文件和目录包括：${NC}"
echo -e "${BLUE}• storage/* (日志、缓存、会话等)${NC}"
echo -e "${BLUE}• bootstrap/cache/*${NC}"
echo -e "${BLUE}• .env 相关配置文件${NC}"
echo -e "${BLUE}• SQLite 数据库文件${NC}"
echo -e "${BLUE}• vendor/ 目录${NC}"
echo -e "${BLUE}• node_modules/ 目录${NC}"
echo -e "${BLUE}• 编译后的前端文件${NC}"
echo -e "${BLUE}• 测试临时文件${NC}"
echo -e "${BLUE}• 各种临时文件和缓存${NC}"

echo -e "\n${YELLOW}注意：${NC}"
echo -e "${YELLOW}1. 请确保已备份重要数据${NC}"
echo -e "${YELLOW}2. 重新使用项目前需要运行: composer install, npm install${NC}"
echo -e "${YELLOW}3. 重新生成密钥: php artisan key:generate${NC}"
echo -e "${YELLOW}4. 重新配置 .env 文件${NC}"