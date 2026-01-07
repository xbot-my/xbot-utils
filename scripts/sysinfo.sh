#!/bin/bash

# 系统环境信息检测脚本
# 检测操作系统、环境信息、依赖和扩展

set -e  # 遇到错误时退出

# 颜色定义
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
PURPLE='\033[0;35m'
CYAN='\033[0;36m'
NC='\033[0m' # No Color

# 系统类型检测
detect_os() {
    if [[ "$OSTYPE" == "linux-gnu"* ]]; then
        OS="Linux"
    elif [[ "$OSTYPE" == "darwin"* ]]; then
        OS="macOS"
    elif [[ "$OSTYPE" == "cygwin" ]]; then
        OS="Cygwin"
    elif [[ "$OSTYPE" == "msys" ]]; then
        OS="MSYS"
    elif [[ "$OSTYPE" == "win32" ]]; then
        OS="Windows"
    else
        OS="Unknown"
    fi
}

# 获取系统信息
get_system_info() {
    echo -e "${BLUE}================================${NC}"
    echo -e "${BLUE}系统信息${NC}"
    echo -e "${BLUE}================================${NC}"
    
    # 主机名
    HOSTNAME=$(hostname)
    echo -e "${CYAN}主机名: ${NC}$HOSTNAME"
    
    # 用户信息
    USER=$(whoami)
    echo -e "${CYAN}当前用户: ${NC}$USER"
    
    # 当前目录
    CURRENT_DIR=$(pwd)
    echo -e "${CYAN}当前目录: ${NC}$CURRENT_DIR"
    
    # 操作系统
    echo -e "${CYAN}操作系统: ${NC}$OS"
    
    # Linux 发行版信息
    if [[ "$OS" == "Linux" ]]; then
        if [ -f /etc/os-release ]; then
            . /etc/os-release
            echo -e "${CYAN}发行版: ${NC}$NAME $VERSION"
            echo -e "${CYAN}发行版 ID: ${NC}$ID"
            if [ -n "$VERSION_CODENAME" ]; then
                echo -e "${CYAN}版本代号: ${NC}$VERSION_CODENAME"
            fi
            if [ -n "$ID_LIKE" ]; then
                echo -e "${CYAN}基于: ${NC}$ID_LIKE"
            fi
        elif type lsb_release >/dev/null 2>&1; then
            LSB_INFO=$(lsb_release -a 2>/dev/null)
            echo -e "${CYAN}发行版信息:${NC}"
            echo "$LSB_INFO" | while read -r line; do
                echo -e "  $line"
            done
        else
            echo -e "${CYAN}发行版: ${NC}Unknown (no /etc/os-release or lsb_release)"
        fi
        
        # Linux 版本号
        if [ -f /etc/issue ]; then
            LINUX_VERSION=$(head -n1 /etc/issue | cut -d' ' -f1-3)
            echo -e "${CYAN}Linux 版本: ${NC}$LINUX_VERSION"
        fi
    elif [[ "$OS" == "macOS" ]]; then
        MACOS_VERSION=$(sw_vers -productVersion)
        MACOS_BUILD=$(sw_vers -buildVersion)
        echo -e "${CYAN}macOS 版本: ${NC}$MACOS_VERSION ($MACOS_BUILD)"
    fi
    
    # 内核版本
    KERNEL=$(uname -r)
    echo -e "${CYAN}内核版本: ${NC}$KERNEL"
    
    # 硬件架构
    ARCH=$(uname -m)
    echo -e "${CYAN}硬件架构: ${NC}$ARCH"
    
    # CPU 信息
    if [[ "$OS" == "Linux" ]]; then
        CPU=$(lscpu | grep "Model name" | head -n1 | cut -d':' -f2 | xargs)
        CPU_CORES=$(nproc)
    elif [[ "$OS" == "macOS" ]]; then
        CPU=$(sysctl -n machdep.cpu.brand_string)
        CPU_CORES=$(sysctl -n hw.ncpu)
    else
        CPU="Unknown"
        CPU_CORES="Unknown"
    fi
    echo -e "${CYAN}CPU: ${NC}$CPU ($CPU_CORES 核心)"
    
    # 内存信息
    if [[ "$OS" == "Linux" ]]; then
        TOTAL_MEM=$(free -h | grep "Mem" | awk '{print $2}')
        AVAILABLE_MEM=$(free -h | grep "Mem" | awk '{print $7}')
        echo -e "${CYAN}内存: ${NC}总 $TOTAL_MEM / 可用 $AVAILABLE_MEM"
    elif [[ "$OS" == "macOS" ]]; then
        TOTAL_MEM=$(($(sysctl -n hw.memsize) / 1024 / 1024 / 1024))GB
        echo -e "${CYAN}内存: ${NC}总 $TOTAL_MEM"
    else
        echo -e "${CYAN}内存: ${NC}Unknown"
    fi
    
    # 磁盘使用情况
    DISK=$(df -h . | tail -n 1 | awk '{print $4 "/" $2 " (" $5 " used)"}')
    echo -e "${CYAN}可用磁盘空间: ${NC}$DISK"
    
    # 当前时间
    CURRENT_TIME=$(date)
    echo -e "${CYAN}当前时间: ${NC}$CURRENT_TIME"
    
    # 系统运行时间
    UPTIME=$(uptime -p 2>/dev/null || uptime | cut -d',' -f1 | awk '{print $3, $4}')
    echo -e "${CYAN}系统运行时间: ${NC}$UPTIME"
}

# 获取网络信息
get_network_info() {
    echo -e "\n${BLUE}================================${NC}"
    echo -e "${BLUE}网络信息${NC}"
    echo -e "${BLUE}================================${NC}"
    
    # 本地 IPv4
    LOCAL_IPV4=$(hostname -I | awk '{print $1}' 2>/dev/null || echo "Unknown")
    if [[ "$LOCAL_IPV4" == "Unknown" ]]; then
        if [[ "$OS" == "Linux" ]]; then
            LOCAL_IPV4=$(ip route get 1.1.1.1 2>/dev/null | awk '{print $7; exit}' 2>/dev/null || echo "Unknown")
        elif [[ "$OS" == "macOS" ]]; then
            LOCAL_IPV4=$(ipconfig getifaddr en0 2>/dev/null || echo "Unknown")
        fi
    fi
    echo -e "${CYAN}本地 IPv4: ${NC}$LOCAL_IPV4"
    
    # 公共 IPv4
    PUBLIC_IPV4=$(curl -s --max-time 5 https://api.ipify.org 2>/dev/null || echo "Unknown")
    echo -e "${CYAN}公共 IPv4: ${NC}$PUBLIC_IPV4"
    
    # 本地 IPv6
    LOCAL_IPV6=$(hostname -I | awk '{for(i=1;i<=NF;i++) if($i ~ /:/) {print $i; exit}}' 2>/dev/null || echo "Unknown")
    if [[ "$LOCAL_IPV6" == "Unknown" ]]; then
        if [[ "$OS" == "Linux" ]]; then
            LOCAL_IPV6=$(ip route get 2001:4860:4860::8888 2>/dev/null | awk '{print $7; exit}' 2>/dev/null || echo "Unknown")
        elif [[ "$OS" == "macOS" ]]; then
            LOCAL_IPV6=$(ipconfig getifaddr en0 2>/dev/null | grep ":" || echo "Unknown")
        fi
    fi
    echo -e "${CYAN}本地 IPv6: ${NC}$LOCAL_IPV6"
    
    # 网络接口信息
    if [[ "$OS" == "Linux" ]]; then
        INTERFACES=$(ip link show 2>/dev/null | grep -E '^[0-9]+:' | grep -v 'lo:' | head -n 3 | awk -F': ' '{print $2}' | tr '\n' ' ')
        echo -e "${CYAN}网络接口: ${NC}$INTERFACES"
        
        # 显示活动连接
        ACTIVE_CONNECTIONS=$(ss -tuln 2>/dev/null | grep -c LISTEN || echo "Unknown")
        echo -e "${CYAN}活动连接数: ${NC}$ACTIVE_CONNECTIONS"
    elif [[ "$OS" == "macOS" ]]; then
        INTERFACES=$(networksetup -listallhardwareports 2>/dev/null | grep -A1 "Device:" | grep -v "Device:" | head -n 3 | tr '\n' ' ')
        echo -e "${CYAN}网络接口: ${NC}$INTERFACES"
        
        # 显示活动连接
        ACTIVE_CONNECTIONS=$(netstat -an | grep -c LISTEN || echo "Unknown")
        echo -e "${CYAN}活动连接数: ${NC}$ACTIVE_CONNECTIONS"
    fi
}

# 检测依赖
check_dependencies() {
    echo -e "\n${BLUE}================================${NC}"
    echo -e "${BLUE}依赖检测${NC}"
    echo -e "${BLUE}================================${NC}"
    
    declare -A deps
    deps["PHP"]="php"
    deps["Composer"]="composer"
    deps["Git"]="git"
    deps["Node.js"]="node"
    deps["NPM"]="npm"
    deps["Yarn"]="yarn"
    deps["MySQL Client"]="mysql"
    deps["PostgreSQL"]="psql"
    deps["SQLite"]="sqlite3"
    deps["Redis"]="redis-cli"
    deps["Docker"]="docker"
    deps["Docker Compose"]="docker-compose"
    deps["Nginx"]="nginx"
    deps["Apache"]="apache2"
    deps["Supervisor"]="supervisord"
    deps["Vim"]="vim"
    deps["Nano"]="nano"
    deps["Curl"]="curl"
    deps["Wget"]="wget"
    deps["Make"]="make"
    deps["GCC"]="gcc"
    
    missing_deps=()
    
    for name in "${!deps[@]}"; do
        cmd="${deps[$name]}"
        if command -v "$cmd" &> /dev/null; then
            # 获取版本信息
            case "$cmd" in
                "php")
                    version=$(php -v | head -n1 | cut -d' ' -f2)
                    ;;
                "composer")
                    version=$(composer --version 2>/dev/null | cut -d' ' -f3)
                    ;;
                "git")
                    version=$(git --version 2>/dev/null | cut -d' ' -f3)
                    ;;
                "node")
                    version=$(node --version 2>/dev/null | sed 's/v//')
                    ;;
                "npm")
                    version=$(npm --version 2>/dev/null)
                    ;;
                "yarn")
                    version=$(yarn --version 2>/dev/null)
                    ;;
                "mysql")
                    version=$(mysql --version 2>/dev/null | cut -d' ' -f6 | cut -d',' -f1)
                    ;;
                "psql")
                    version=$(psql --version 2>/dev/null | cut -d' ' -f3)
                    ;;
                "sqlite3")
                    version=$(sqlite3 --version 2>/dev/null | cut -d' ' -f1)
                    ;;
                "redis-cli")
                    version=$(redis-cli --version 2>/dev/null | cut -d' ' -f3)
                    ;;
                "docker")
                    version=$(docker --version 2>/dev/null | cut -d' ' -f3 | sed 's/,$//')
                    ;;
                "docker-compose")
                    version=$(docker-compose --version 2>/dev/null | cut -d' ' -f4)
                    ;;
                "nginx")
                    version=$(nginx -v 2>&1 | cut -d'/' -f2)
                    ;;
                "apache2")
                    version=$(apache2 -v 2>/dev/null | head -n1 | cut -d' ' -f3)
                    ;;
                "supervisord")
                    version=$(supervisord --version 2>/dev/null)
                    ;;
                *)
                    version=$($cmd --version 2>/dev/null | head -n1 | cut -d' ' -f1-3)
                    if [ -z "$version" ]; then
                        version=$($cmd -v 2>/dev/null | head -n1 | cut -d' ' -f1-3)
                    fi
                    ;;
            esac
            
            if [ -z "$version" ]; then
                version="Installed (version unknown)"
            fi
            echo -e "${GREEN}✓ ${name}: ${NC}$version"
        else
            echo -e "${RED}✗ ${name}: 未安装${NC}"
            missing_deps+=("$name")
        fi
    done
    
    if [ ${#missing_deps[@]} -eq 0 ]; then
        echo -e "\n${GREEN}✓ 所有必需依赖都已安装${NC}"
    else
        echo -e "\n${RED}✗ 以下依赖缺失:${NC}"
        for dep in "${missing_deps[@]}"; do
            echo -e "  - $dep"
        done
    fi
}

# 检测 PHP 扩展和版本
check_php_extensions() {
    echo -e "\n${BLUE}================================${NC}"
    echo -e "${BLUE}PHP 信息检测${NC}"
    echo -e "${BLUE}================================${NC}"
    
    if ! command -v php &> /dev/null; then
        echo -e "${RED}✗ PHP 未安装，跳过 PHP 扩展检测${NC}"
        return
    fi
    
    # PHP 版本信息
    PHP_VERSION=$(php -v | head -n1 | cut -d' ' -f2)
    PHP_API=$(php -r "echo PHP_API;" 2>/dev/null || echo "Unknown")
    PHP_SAPI=$(php -r "echo PHP_SAPI;" 2>/dev/null || echo "Unknown")
    PHP_INT_SIZE=$(php -r "echo PHP_INT_SIZE * 8;" 2>/dev/null || echo "Unknown")
    
    echo -e "${CYAN}PHP 版本: ${NC}$PHP_VERSION"
    echo -e "${CYAN}PHP API: ${NC}$PHP_API"
    echo -e "${CYAN}SAPI: ${NC}$PHP_SAPI"
    echo -e "${CYAN}整数大小: ${NC}${PHP_INT_SIZE}位"
    
    # 检查配置文件
    PHP_INI=$(php --ini | grep "Loaded Configuration File" | cut -d':' -f2 | xargs || echo "None")
    echo -e "${CYAN}配置文件: ${NC}$PHP_INI"
    
    # 检查扩展目录
    EXT_DIR=$(php -r "echo ini_get('extension_dir');" 2>/dev/null || echo "Unknown")
    echo -e "${CYAN}扩展目录: ${NC}$EXT_DIR"
    
    echo -e "\n${CYAN}已安装的 PHP 扩展:${NC}"
    
    # 获取所有已安装的扩展
    INSTALLED_EXTS=$(php -m | grep -v "PHP Modules" | grep -v "Core" | grep -v "date" | grep -v "filter" | grep -v "hash" | grep -v "json" | grep -v "Reflection" | grep -v "SPL" | grep -v "standard" | grep -v "Zend OPcache" | grep -v "ionCube Loader" | grep -v "xdebug" | grep -v "New Relic" | head -n 20)
    
    # 显示前20个扩展
    echo "$INSTALLED_EXTS" | while read -r ext; do
        if [ -n "$ext" ]; then
            echo -e "  - $ext"
        fi
    done
    
    # 检查关键扩展
    echo -e "\n${CYAN}关键扩展检测:${NC}"
    
    declare -A php_exts
    php_exts["OpenSSL"]="openssl"
    php_exts["PDO"]="pdo"
    php_exts["PDO MySQL"]="pdo_mysql"
    php_exts["PDO PostgreSQL"]="pdo_pgsql"
    php_exts["PDO SQLite"]="pdo_sqlite"
    php_exts["MBString"]="mbstring"
    php_exts["Tokenizer"]="tokenizer"
    php_exts["XML"]="xml"
    php_exts["Ctype"]="ctype"
    php_exts["JSON"]="json"
    php_exts["GD"]="gd"
    php_exts["cURL"]="curl"
    php_exts["Zip"]="zip"
    php_exts["BCMath"]="bcmath"
    php_exts["PCRE"]="pcre"
    php_exts["Intl"]="intl"
    php_exts["Fileinfo"]="fileinfo"
    php_exts["Exif"]="exif"
    php_exts["Sockets"]="sockets"
    php_exts["Redis"]="redis"
    php_exts["APCu"]="apcu"
    php_exts["Xdebug"]="xdebug"
    
    missing_php_exts=()
    
    for name in "${!php_exts[@]}"; do
        ext="${php_exts[$name]}"
        if php -m | grep -i "^$ext$" &> /dev/null; then
            echo -e "${GREEN}✓ ${name}: 已安装${NC}"
        else
            echo -e "${RED}✗ ${name}: 未安装${NC}"
            missing_php_exts+=("$name")
        fi
    done
    
    if [ ${#missing_php_exts[@]} -eq 0 ]; then
        echo -e "\n${GREEN}✓ 所有必需的 PHP 扩展都已安装${NC}"
    else
        echo -e "\n${RED}✗ 以下 PHP 扩展缺失:${NC}"
        for ext in "${missing_php_exts[@]}"; do
            echo -e "  - $ext"
        done
    fi
}

# 检测 Web 服务器
check_web_server() {
    echo -e "\n${BLUE}================================${NC}"
    echo -e "${BLUE}Web 服务器检测${NC}"
    echo -e "${BLUE}================================${NC}"
    
    # 检查 Nginx
    if command -v nginx &> /dev/null; then
        NGINX_VERSION=$(nginx -v 2>&1 | cut -d'/' -f2)
        echo -e "${GREEN}✓ Nginx: ${NC}$NGINX_VERSION"
        
        # 检查配置
        if nginx -t &> /dev/null; then
            echo -e "  ${GREEN}配置: 有效${NC}"
        else
            echo -e "  ${RED}配置: 无效${NC}"
        fi
        
        # 检查是否正在运行
        if [[ "$OS" == "Linux" ]]; then
            if pgrep nginx &> /dev/null; then
                echo -e "  ${GREEN}状态: 运行中${NC}"
            else
                echo -e "  ${YELLOW}状态: 未运行${NC}"
            fi
        elif [[ "$OS" == "macOS" ]]; then
            if brew services list | grep nginx &> /dev/null; then
                echo -e "  ${GREEN}状态: 运行中${NC}"
            else
                echo -e "  ${YELLOW}状态: 未运行${NC}"
            fi
        fi
    else
        echo -e "${RED}✗ Nginx: 未安装${NC}"
    fi
    
    # 检查 Apache
    if command -v apache2 &> /dev/null; then
        APACHE_VERSION=$(apache2 -v 2>/dev/null | head -n1 | cut -d' ' -f3)
        echo -e "${GREEN}✓ Apache: ${NC}$APACHE_VERSION"
        
        # 检查模块
        if command -v apache2ctl &> /dev/null; then
            APACHE_MODULES=$(apache2ctl -M 2>/dev/null | grep -c "module" || echo "Unknown")
            echo -e "  ${CYAN}已加载模块数: ${NC}$APACHE_MODULES"
        fi
        
        # 检查是否正在运行
        if [[ "$OS" == "Linux" ]]; then
            if pgrep apache2 &> /dev/null; then
                echo -e "  ${GREEN}状态: 运行中${NC}"
            else
                echo -e "  ${YELLOW}状态: 未运行${NC}"
            fi
        elif [[ "$OS" == "macOS" ]]; then
            if brew services list | grep httpd &> /dev/null; then
                echo -e "  ${GREEN}状态: 运行中${NC}"
            else
                echo -e "  ${YELLOW}状态: 未运行${NC}"
            fi
        fi
    elif command -v httpd &> /dev/null; then
        APACHE_VERSION=$(httpd -v 2>/dev/null | head -n1 | cut -d' ' -f3)
        echo -e "${GREEN}✓ Apache: ${NC}$APACHE_VERSION"
    else
        echo -e "${RED}✗ Apache: 未安装${NC}"
    fi
}

# 检测数据库服务
check_database_services() {
    echo -e "\n${BLUE}================================${NC}"
    echo -e "${BLUE}数据库服务检测${NC}"
    echo -e "${BLUE}================================${NC}"
    
    # MySQL
    if command -v mysql &> /dev/null; then
        MYSQL_VERSION=$(mysql --version 2>/dev/null | cut -d' ' -f6)
        echo -e "${GREEN}✓ MySQL Client: ${NC}$MYSQL_VERSION"
        
        # 检查服务状态
        if [[ "$OS" == "Linux" ]]; then
            if pgrep mysqld &> /dev/null || pgrep mysql &> /dev/null; then
                echo -e "  ${GREEN}状态: 服务运行中${NC}"
            else
                echo -e "  ${YELLOW}状态: 服务未运行${NC}"
            fi
        elif [[ "$OS" == "macOS" ]]; then
            if brew services list | grep mysql &> /dev/null; then
                echo -e "  ${GREEN}状态: 服务运行中${NC}"
            else
                echo -e "  ${YELLOW}状态: 服务未运行${NC}"
            fi
        fi
    else
        echo -e "${RED}✗ MySQL Client: 未安装${NC}"
    fi
    
    # PostgreSQL
    if command -v psql &> /dev/null; then
        PG_VERSION=$(psql --version 2>/dev/null | cut -d' ' -f3)
        echo -e "${GREEN}✓ PostgreSQL: ${NC}$PG_VERSION"
        
        # 检查服务状态
        if [[ "$OS" == "Linux" ]]; then
            if pgrep postgres &> /dev/null; then
                echo -e "  ${GREEN}状态: 服务运行中${NC}"
            else
                echo -e "  ${YELLOW}状态: 服务未运行${NC}"
            fi
        elif [[ "$OS" == "macOS" ]]; then
            if brew services list | grep postgresql &> /dev/null; then
                echo -e "  ${GREEN}状态: 服务运行中${NC}"
            else
                echo -e "  ${YELLOW}状态: 服务未运行${NC}"
            fi
        fi
    else
        echo -e "${RED}✗ PostgreSQL: 未安装${NC}"
    fi
    
    # SQLite
    if command -v sqlite3 &> /dev/null; then
        SQLITE_VERSION=$(sqlite3 --version 2>/dev/null | cut -d' ' -f1)
        echo -e "${GREEN}✓ SQLite: ${NC}$SQLITE_VERSION"
    else
        echo -e "${RED}✗ SQLite: 未安装${NC}"
    fi
    
    # Redis
    if command -v redis-cli &> /dev/null; then
        REDIS_VERSION=$(redis-cli --version 2>/dev/null | cut -d' ' -f3)
        echo -e "${GREEN}✓ Redis: ${NC}$REDIS_VERSION"
        
        # 检查服务状态
        if pgrep redis-server &> /dev/null || pgrep redis &> /dev/null; then
            echo -e "  ${GREEN}状态: 服务运行中${NC}"
        else
            echo -e "  ${YELLOW}状态: 服务未运行${NC}"
        fi
    else
        echo -e "${RED}✗ Redis: 未安装${NC}"
    fi
}

# 检测 Docker
check_docker() {
    echo -e "\n${BLUE}================================${NC}"
    echo -e "${BLUE}Docker 检测${NC}"
    echo -e "${BLUE}================================${NC}"
    
    if command -v docker &> /dev/null; then
        DOCKER_VERSION=$(docker --version 2>/dev/null | cut -d' ' -f3 | sed 's/,$//')
        echo -e "${GREEN}✓ Docker: ${NC}$DOCKER_VERSION"
        
        # 检查 Docker 是否正在运行
        if docker info &> /dev/null; then
            echo -e "  ${GREEN}状态: Docker 守护进程运行中${NC}"
            # 显示运行中的容器
            RUNNING_CONTAINERS=$(docker ps -q | wc -l)
            TOTAL_CONTAINERS=$(docker ps -a -q | wc -l)
            IMAGES_COUNT=$(docker images -q | wc -l)
            echo -e "  ${CYAN}容器: ${NC}$RUNNING_CONTAINERS 运行中 / $TOTAL_CONTAINERS 总计"
            echo -e "  ${CYAN}镜像: ${NC}$IMAGES_COUNT 个"
        else
            echo -e "  ${RED}状态: Docker 守护进程未运行${NC}"
        fi
    else
        echo -e "${RED}✗ Docker: 未安装${NC}"
    fi
    
    if command -v docker-compose &> /dev/null; then
        COMPOSE_VERSION=$(docker-compose --version 2>/dev/null | cut -d' ' -f4)
        echo -e "${GREEN}✓ Docker Compose: ${NC}$COMPOSE_VERSION"
    else
        echo -e "${RED}✗ Docker Compose: 未安装${NC}"
    fi
}

# 检测 Laravel 项目
check_laravel_project() {
    echo -e "\n${BLUE}================================${NC}"
    echo -e "${BLUE}Laravel 项目检测${NC}"
    echo -e "${BLUE}================================${NC}"
    
    if [ -f "artisan" ] && [ -f "composer.json" ]; then
        echo -e "${GREEN}✓ 检测到 Laravel 项目${NC}"
        
        # 获取 Laravel 版本
        if [ -f "composer.json" ]; then
            LARAVEL_VERSION=$(grep -o '"laravel/framework"[^}]*"version"[^"]*"[^"]*"' composer.json 2>/dev/null | cut -d'"' -f6)
            if [ -z "$LARAVEL_VERSION" ]; then
                LARAVEL_VERSION=$(php artisan --version 2>/dev/null | cut -d' ' -f3)
            fi
            echo -e "${CYAN}Laravel 版本: ${NC}$LARAVEL_VERSION"
        fi
        
        # 检查 .env 文件
        if [ -f ".env" ]; then
            echo -e "${GREEN}✓ .env 文件存在${NC}"
            # 显示一些关键配置
            if [ -f ".env" ]; then
                DB_CONNECTION=$(grep "^DB_CONNECTION=" .env | cut -d'=' -f2 2>/dev/null || echo "Not set")
                APP_ENV=$(grep "^APP_ENV=" .env | cut -d'=' -f2 2>/dev/null || echo "Not set")
                APP_DEBUG=$(grep "^APP_DEBUG=" .env | cut -d'=' -f2 2>/dev/null || echo "Not set")
                echo -e "  ${CYAN}环境: ${NC}$APP_ENV"
                echo -e "  ${CYAN}调试: ${NC}$APP_DEBUG"
                echo -e "  ${CYAN}数据库: ${NC}$DB_CONNECTION"
            fi
        else
            echo -e "${YELLOW}⚠ .env 文件不存在${NC}"
        fi
        
        # 检查 vendor 目录
        if [ -d "vendor" ]; then
            echo -e "${GREEN}✓ vendor 目录存在${NC}"
            # 统计包数量
            PKG_COUNT=$(find vendor -name "composer.json" 2>/dev/null | wc -l)
            echo -e "  ${CYAN}依赖包数量: ${NC}$PKG_COUNT 个"
        else
            echo -e "${YELLOW}⚠ vendor 目录不存在（可能需要运行 composer install）${NC}"
        fi
        
        # 检查 storage 目录权限
        if [ -w "storage" ]; then
            echo -e "${GREEN}✓ storage 目录可写${NC}"
        else
            echo -e "${RED}✗ storage 目录不可写${NC}"
        fi
        
        # 检查 bootstrap/cache 目录权限
        if [ -w "bootstrap/cache" ]; then
            echo -e "${GREEN}✓ bootstrap/cache 目录可写${NC}"
        else
            echo -e "${RED}✗ bootstrap/cache 目录不可写${NC}"
        fi
        
        # 检查配置缓存
        if [ -f "bootstrap/cache/config.php" ]; then
            echo -e "${GREEN}✓ 配置已缓存${NC}"
        else
            echo -e "${YELLOW}⚠ 配置未缓存${NC}"
        fi
        
    else
        echo -e "${YELLOW}⚠ 当前目录不是 Laravel 项目${NC}"
        echo -e "${CYAN}提示: 确保 artisan 文件和 composer.json 存在${NC}"
    fi
}

# 检测系统性能
check_system_performance() {
    echo -e "\n${BLUE}================================${NC}"
    echo -e "${BLUE}系统性能检测${NC}"
    echo -e "${BLUE}================================${NC}"
    
    if [[ "$OS" == "Linux" ]]; then
        # CPU 使用率
        CPU_USAGE=$(top -bn1 | grep "Cpu(s)" | awk '{print $2}' | cut -d'%' -f1)
        echo -e "${CYAN}CPU 使用率: ${NC}$CPU_USAGE%"
        
        # 内存使用率
        MEM_USAGE=$(free | grep "Mem" | awk '{printf("%.2f%%", $3/$2 * 100.0)}')
        echo -e "${CYAN}内存使用率: ${NC}$MEM_USAGE"
        
        # 磁盘使用率
        DISK_USAGE=$(df . | tail -n1 | awk '{print $5}')
        echo -e "${CYAN}磁盘使用率: ${NC}$DISK_USAGE"
        
        # 负载平均值
        LOAD_AVG=$(uptime | awk -F'load average:' '{print $2}' | xargs)
        echo -e "${CYAN}负载平均值: ${NC}$LOAD_AVG"
        
    elif [[ "$OS" == "macOS" ]]; then
        # CPU 使用率
        CPU_USAGE=$(top -l 1 | grep "CPU usage" | awk '{print $3}' | cut -d'%' -f1)
        echo -e "${CYAN}CPU 使用率: ${NC}${CPU_USAGE}%"
        
        # 内存使用率
        MEM_INFO=$(vm_stat | awk '/^Mach Virtual Memory Statistics:/ { found=1 } found && /Pages free/ { free=$3 } found && /Pages active/ { active=$3 } found && /Pages inactive/ { inactive=$3 } found && /Pages speculative/ { spec=$3 } END { total = free + active + inactive + spec; print active/total*100 }' 2>/dev/null)
        if [ ! -z "$MEM_INFO" ]; then
            echo -e "${CYAN}活跃内存使用率: ${NC}$(printf "%.2f%%" "$MEM_INFO")"
        fi
    fi
}

# 主函数
main() {
    detect_os
    get_system_info
    get_network_info
    check_dependencies
    check_php_extensions
    check_web_server
    check_database_services
    check_docker
    check_laravel_project
    check_system_performance
    
    echo -e "\n${BLUE}================================${NC}"
    echo -e "${GREEN}环境检测完成！${NC}"
    echo -e "${BLUE}================================${NC}"
    
    # 总结
    total_missing=0
    
    # 统计缺失的依赖
    declare -A deps
    deps["PHP"]="php"
    deps["Composer"]="composer"
    deps["Git"]="git"
    deps["Node.js"]="node"
    deps["NPM"]="npm"
    deps["MySQL Client"]="mysql"
    deps["PostgreSQL"]="psql"
    deps["SQLite"]="sqlite3"
    deps["Redis"]="redis-cli"
    deps["Docker"]="docker"
    
    for name in "${!deps[@]}"; do
        cmd="${deps[$name]}"
        if ! command -v "$cmd" &> /dev/null; then
            ((total_missing++))
        fi
    done
    
    # 统计缺失的 PHP 扩展
    declare -A php_exts
    php_exts["OpenSSL"]="openssl"
    php_exts["PDO"]="pdo"
    php_exts["PDO MySQL"]="pdo_mysql"
    php_exts["MBString"]="mbstring"
    php_exts["Tokenizer"]="tokenizer"
    php_exts["XML"]="xml"
    php_exts["Ctype"]="ctype"
    php_exts["JSON"]="json"
    
    for name in "${!php_exts[@]}"; do
        ext="${php_exts[$name]}"
        if command -v php &> /dev/null && ! php -m | grep -i "^$ext$" &> /dev/null; then
            ((total_missing++))
        fi
    done
    
    if [ $total_missing -eq 0 ]; then
        echo -e "${GREEN}🎉 所有必需的组件都已安装！${NC}"
    else
        echo -e "${YELLOW}⚠ 发现 $total_missing 个缺失的组件，可能需要安装以支持 Laravel 开发${NC}"
    fi
    
    # shellcheck disable=SC2004
    echo -e "\n${CYAN}运行时长: $(($(date +%s) - $SECONDS)) 秒${NC}"
}

# 记录开始时间
SECONDS=0

# 运行主函数
main "$@"