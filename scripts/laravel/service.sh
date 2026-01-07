#!/bin/bash

# Laravel 服务管理脚本
# 用法: ./service.sh {start|stop|restart|status}

set -e

# 颜色定义
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
CYAN='\033[0;36m'
NC='\033[0m' # No Color

# 脚本名称
SCRIPT_NAME=$(basename "$0")

# 项目根目录
PROJECT_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

# 日志目录
LOG_DIR="$PROJECT_ROOT/storage/logs"
mkdir -p "$LOG_DIR"

# PID 文件目录
PID_DIR="$PROJECT_ROOT/storage/pids"
mkdir -p "$PID_DIR"

# Web 服务配置
WEB_COMMAND="php artisan serve --host=0.0.0.0 --port=8000"
WEB_PID_FILE="$PID_DIR/web.pid"
WEB_LOG_FILE="$LOG_DIR/web.log"

# Queue 服务配置
QUEUE_COMMAND="php artisan queue:work --sleep=3 --tries=3 --max-time=3600"
QUEUE_PID_FILE="$PID_DIR/queue.pid"
QUEUE_LOG_FILE="$LOG_DIR/queue.log"

# Schedule 服务配置（通过 cron 或单独进程）
SCHEDULE_COMMAND="php artisan schedule:run"
SCHEDULE_PID_FILE="$PID_DIR/schedule.pid"
SCHEDULE_LOG_FILE="$LOG_DIR/schedule.log"

# Echo 服务配置（如果使用 Laravel Echo Server）
ECHO_COMMAND="laravel-echo-server start"
ECHO_PID_FILE="$PID_DIR/echo.pid"
ECHO_LOG_FILE="$LOG_DIR/echo.log"

# Horizon 服务配置（如果使用 Horizon）
HORIZON_COMMAND="php artisan horizon"
HORIZON_PID_FILE="$PID_DIR/horizon.pid"
HORIZON_LOG_FILE="$LOG_DIR/horizon.log"

# 服务列表（按启动顺序）
SERVICES=("web" "queue" "schedule" "horizon" "echo")

# 检查是否在 Laravel 项目目录
check_laravel_project() {
    if [ ! -f "artisan" ] || [ ! -f "composer.json" ]; then
        echo -e "${RED}错误: 当前目录不是 Laravel 项目根目录！${NC}"
        echo -e "${YELLOW}请确保 artisan 文件和 composer.json 存在${NC}"
        exit 1
    fi
}

# 检查进程是否运行
is_running() {
    local pid_file="$1"
    if [ -f "$pid_file" ]; then
        local pid=$(cat "$pid_file")
        if ps -p "$pid" > /dev/null 2>&1; then
            return 0
        else
            rm -f "$pid_file"
            return 1
        fi
    else
        return 1
    fi
}

# 启动服务
start_service() {
    local service_name="$1"
    local command=""
    local pid_file=""
    local log_file=""
    
    case "$service_name" in
        "web")
            command="$WEB_COMMAND"
            pid_file="$WEB_PID_FILE"
            log_file="$WEB_LOG_FILE"
            ;;
        "queue")
            command="$QUEUE_COMMAND"
            pid_file="$QUEUE_PID_FILE"
            log_file="$QUEUE_LOG_FILE"
            ;;
        "schedule")
            command="$SCHEDULE_COMMAND"
            pid_file="$SCHEDULE_PID_FILE"
            log_file="$SCHEDULE_LOG_FILE"
            ;;
        "echo")
            command="$ECHO_COMMAND"
            pid_file="$ECHO_PID_FILE"
            log_file="$ECHO_LOG_FILE"
            ;;
        "horizon")
            command="$HORIZON_COMMAND"
            pid_file="$HORIZON_PID_FILE"
            log_file="$HORIZON_LOG_FILE"
            ;;
        *)
            echo -e "${RED}未知服务: $service_name${NC}"
            return 1
            ;;
    esac
    
    # 检查服务是否已在运行
    if is_running "$pid_file"; then
        echo -e "${YELLOW}⚠ $service_name 服务已在运行${NC}"
        return 0
    fi
    
    # 检查依赖命令是否存在
    if [[ "$service_name" == "echo" ]]; then
        if ! command -v laravel-echo-server &> /dev/null; then
            echo -e "${YELLOW}⚠ laravel-echo-server 未安装，跳过 $service_name 服务${NC}"
            return 0
        fi
    fi
    
    if [[ "$service_name" == "horizon" ]]; then
        if ! grep -q "laravel/horizon" composer.json &> /dev/null; then
            echo -e "${YELLOW}⚠ Laravel Horizon 未安装，跳过 $service_name 服务${NC}"
            return 0
        fi
    fi
    
    echo -e "${CYAN}启动 $service_name 服务...${NC}"
    
    # 启动服务
    if [[ "$service_name" == "schedule" ]]; then
        # Schedule 服务使用 cron 方式，这里用后台循环
        (
            while true; do
                $command >> "$log_file" 2>&1
                sleep 60
            done
        ) &
        echo $! > "$pid_file"
    else
        # 其他服务直接启动
        nohup bash -c "$command >> '$log_file' 2>&1 & echo \$!" > "$pid_file" 2>/dev/null
    fi
    
    sleep 2
    
    if is_running "$pid_file"; then
        echo -e "${GREEN}✓ $service_name 服务启动成功${NC}"
    else
        echo -e "${RED}✗ $service_name 服务启动失败${NC}"
        rm -f "$pid_file" 2>/dev/null
        return 1
    fi
}

# 停止服务
stop_service() {
    local service_name="$1"
    local pid_file=""
    
    case "$service_name" in
        "web") pid_file="$WEB_PID_FILE" ;;
        "queue") pid_file="$QUEUE_PID_FILE" ;;
        "schedule") pid_file="$SCHEDULE_PID_FILE" ;;
        "echo") pid_file="$ECHO_PID_FILE" ;;
        "horizon") pid_file="$HORIZON_PID_FILE" ;;
        *) 
            echo -e "${RED}未知服务: $service_name${NC}"
            return 1
            ;;
    esac
    
    if ! is_running "$pid_file"; then
        echo -e "${YELLOW}⚠ $service_name 服务未运行${NC}"
        return 0
    fi
    
    local pid=$(cat "$pid_file")
    echo -e "${CYAN}停止 $service_name 服务 (PID: $pid)...${NC}"
    
    # 发送终止信号
    kill "$pid" 2>/dev/null || true
    sleep 2
    
    # 如果进程还在，强制终止
    if is_running "$pid_file"; then
        echo -e "${YELLOW}强制终止 $service_name 服务...${NC}"
        kill -9 "$pid" 2>/dev/null || true
        sleep 1
    fi
    
    # 清理 PID 文件
    rm -f "$pid_file" 2>/dev/null
    
    if ! is_running "$pid_file"; then
        echo -e "${GREEN}✓ $service_name 服务已停止${NC}"
    else
        echo -e "${RED}✗ $service_name 服务停止失败${NC}"
        return 1
    fi
}

# 检查服务状态
status_service() {
    local service_name="$1"
    local pid_file=""
    
    case "$service_name" in
        "web") pid_file="$WEB_PID_FILE" ;;
        "queue") pid_file="$QUEUE_PID_FILE" ;;
        "schedule") pid_file="$SCHEDULE_PID_FILE" ;;
        "echo") pid_file="$ECHO_PID_FILE" ;;
        "horizon") pid_file="$HORIZON_PID_FILE" ;;
        *) 
            echo -e "${RED}未知服务: $service_name${NC}"
            return 1
            ;;
    esac
    
    if is_running "$pid_file"; then
        local pid=$(cat "$pid_file")
        echo -e "${GREEN}✓ $service_name: 运行中 (PID: $pid)${NC}"
    else
        echo -e "${RED}✗ $service_name: 已停止${NC}"
    fi
}

# 启动所有服务
start_all() {
    echo -e "${BLUE}================================${NC}"
    echo -e "${BLUE}启动所有 Laravel 服务${NC}"
    echo -e "${BLUE}================================${NC}"
    
    for service in "${SERVICES[@]}"; do
        start_service "$service"
    done
    
    echo -e "\n${GREEN}所有服务启动完成！${NC}"
    echo -e "${CYAN}Web 服务地址: http://localhost:8000${NC}"
    echo -e "${CYAN}日志文件位置: $LOG_DIR${NC}"
}

# 停止所有服务
stop_all() {
    echo -e "${BLUE}================================${NC}"
    echo -e "${BLUE}停止所有 Laravel 服务${NC}"
    echo -e "${BLUE}================================${NC}"
    
    # 反向停止（先停止依赖服务）
    for ((i=${#SERVICES[@]}-1; i>=0; i--)); do
        stop_service "${SERVICES[i]}"
    done
    
    echo -e "\n${GREEN}所有服务已停止！${NC}"
}

# 重启所有服务
restart_all() {
    echo -e "${BLUE}================================${NC}"
    echo -e "${BLUE}重启所有 Laravel 服务${NC}"
    echo -e "${BLUE}================================${NC}"
    
    stop_all
    sleep 3
    start_all
}

# 显示所有服务状态
status_all() {
    echo -e "${BLUE}================================${NC}"
    echo -e "${BLUE}Laravel 服务状态${NC}"
    echo -e "${BLUE}================================${NC}"
    
    for service in "${SERVICES[@]}"; do
        status_service "$service"
    done
    
    echo -e "\n${CYAN}日志文件:${NC}"
    echo -e "  Web:       $WEB_LOG_FILE"
    echo -e "  Queue:     $QUEUE_LOG_FILE"
    echo -e "  Schedule:  $SCHEDULE_LOG_FILE"
    echo -e "  Horizon:   $HORIZON_LOG_FILE"
    echo -e "  Echo:      $ECHO_LOG_FILE"
}

# 显示帮助信息
show_help() {
    echo -e "${CYAN}Laravel 服务管理脚本${NC}"
    echo -e "${CYAN}用法: $SCRIPT_NAME {start|stop|restart|status}${NC}"
    echo -e ""
    echo -e "${CYAN}命令说明:${NC}"
    echo -e "  start   - 启动所有服务"
    echo -e "  stop    - 停止所有服务"
    echo -e "  restart - 重启所有服务"
    echo -e "  status  - 显示所有服务状态"
    echo -e ""
    echo -e "${CYAN}管理的服务:${NC}"
    echo -e "  • Web 服务 (php artisan serve)"
    echo -e "  • Queue 队列处理器"
    echo -e "  • Schedule 计划任务"
    echo -e "  • Horizon (如果已安装)"
    echo -e "  • Echo Server (如果已安装)"
    echo -e ""
    echo -e "${CYAN}日志位置: ${NC}$LOG_DIR"
    echo -e "${CYAN}PID 文件: ${NC}$PID_DIR"
}

# 主函数
main() {
    # 检查参数
    if [ $# -eq 0 ]; then
        show_help
        exit 1
    fi
    
    # 检查是否为 Laravel 项目
    check_laravel_project
    
    # 执行命令
    case "$1" in
        "start")
            start_all
            ;;
        "stop")
            stop_all
            ;;
        "restart")
            restart_all
            ;;
        "status")
            status_all
            ;;
        "help"|"-h"|"--help")
            show_help
            ;;
        *)
            echo -e "${RED}错误: 未知命令 '$1'${NC}"
            echo -e "${YELLOW}使用 $SCRIPT_NAME help 查看帮助${NC}"
            exit 1
            ;;
    esac
}

# 运行主函数
main "$@"