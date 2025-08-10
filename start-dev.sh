#!/bin/bash

# Fixed port configuration - DO NOT CHANGE
FIXED_PORT=8000

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

echo -e "${BLUE}🚀 Starting Text Generate Service...${NC}"
echo "================================================="
echo -e "${YELLOW}📍 Server will be available at: http://localhost:$FIXED_PORT${NC}"
echo -e "${YELLOW}⚠️  This service MUST run on port $FIXED_PORT - cannot be changed!${NC}"
echo ""

# Kiểm tra port có đang được sử dụng không
echo -e "${YELLOW}🔍 Checking if port $FIXED_PORT is available...${NC}"

if lsof -Pi :$FIXED_PORT -sTCP:LISTEN -t >/dev/null 2>&1; then
    echo -e "${RED}❌ Error: Port $FIXED_PORT is already in use!${NC}"
    echo -e "${YELLOW}Process using port $FIXED_PORT:${NC}"
    lsof -Pi :$FIXED_PORT -sTCP:LISTEN
    echo ""
    echo -e "${RED}🚫 CANNOT START Text Generate Service - PORT $FIXED_PORT IS OCCUPIED${NC}"
    echo -e "${YELLOW}Please stop the process using port $FIXED_PORT or wait for it to be available.${NC}"
    echo -e "${YELLOW}Do not change to another port - this service must run on port $FIXED_PORT.${NC}"
    exit 1
fi

echo -e "${GREEN}✅ Port $FIXED_PORT is available!${NC}"
echo ""

# Khởi động Laravel development server trên port cố định
echo -e "${GREEN}🌟 Starting Laravel development server on fixed port $FIXED_PORT...${NC}"
php artisan serve --host=0.0.0.0 --port=$FIXED_PORT

echo ""
echo "👋 Server stopped."
