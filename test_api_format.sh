#!/bin/bash

# Test script to verify API response format consistency
# Usage: ./test_api_format.sh [base_url]

BASE_URL=${1:-"http://127.0.0.1:8003"}

echo "Testing API Response Format Consistency"
echo "======================================="
echo "Base URL: $BASE_URL"
echo ""

# Function to test endpoint and check format
test_endpoint() {
    local method=$1
    local endpoint=$2
    local expected_success=$3
    
    echo "Testing: $method $endpoint"
    echo "Expected success: $expected_success"
    
    response=$(curl -s -X $method "$BASE_URL$endpoint")
    
    # Check if response is valid JSON
    if ! echo "$response" | jq . >/dev/null 2>&1; then
        echo "❌ Invalid JSON response"
        echo "Response: $response"
        echo ""
        return 1
    fi
    
    # Check required fields
    has_success=$(echo "$response" | jq -r '.success // "missing"')
    has_message=$(echo "$response" | jq -r '.message // "missing"')
    has_timestamp=$(echo "$response" | jq -r '.timestamp // "missing"')
    
    if [ "$has_success" = "missing" ]; then
        echo "❌ Missing 'success' field"
    elif [ "$has_message" = "missing" ]; then
        echo "❌ Missing 'message' field"
    elif [ "$has_timestamp" = "missing" ]; then
        echo "❌ Missing 'timestamp' field"
    elif [ "$has_success" != "$expected_success" ]; then
        echo "❌ Incorrect success value: expected $expected_success, got $has_success"
    else
        echo "✅ Response format is correct"
        echo "   Success: $has_success"
        echo "   Message: $(echo "$response" | jq -r '.message')"
        echo "   Has data: $(echo "$response" | jq -r 'has("data")')"
    fi
    
    echo ""
}

# Test all endpoints
echo "1. Testing Server Info endpoint..."
test_endpoint "GET" "/api/system/server-info" "true"

echo "2. Testing File List endpoint..."
test_endpoint "GET" "/api/system/list" "true"

echo "3. Testing non-existent endpoint (should fail)..."
test_endpoint "GET" "/api/system/nonexistent" "false"

echo "4. Testing delete non-existent file (should fail)..."
test_endpoint "DELETE" "/api/system/delete/nonexistent.txt" "false"

echo "Testing completed!"
