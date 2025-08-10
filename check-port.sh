#!/bin/bash

# Text Generate Service Port Configuration
FIXED_PORT=8000

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

echo "🔍 Checking Text Generate Service port..."
echo "================================================="

if lsof -Pi :$FIXED_PORT -sTCP:LISTEN -t >/dev/null 2>&1; then
    echo -e "${RED}❌ Error: Port $FIXED_PORT is already in use!${NC}"
    echo -e "${YELLOW}Service: Text Generate Service (Laravel)${NC}"
    echo -e "${YELLOW}Process using port $FIXED_PORT:${NC}"
    lsof -Pi :$FIXED_PORT -sTCP:LISTEN
    echo ""
    echo -e "${RED}🚫 CANNOT START Text Generate Service - PORT $FIXED_PORT IS OCCUPIED${NC}"
    echo -e "${YELLOW}Please stop the process using port $FIXED_PORT or wait for it to be available.${NC}"
    echo -e "${YELLOW}Do not change to another port - this service must run on port $FIXED_PORT.${NC}"
    exit 1
else
    echo -e "${GREEN}✅ Port $FIXED_PORT is available for Text Generate Service${NC}"
    echo "================================================="
    exit 0
fi
