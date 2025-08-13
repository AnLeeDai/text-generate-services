#!/bin/bash

# Test API for Banrisul Bill Generation (Simplified Template)

API_URL="http://localhost:8000/api/banrisul-bill/generate"

echo "Testing Banrisul Bill API (Simplified Template)..."
echo "API URL: $API_URL"
echo ""

# Sample data payload - với account number format mới
curl -X POST "$API_URL" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '[
    {
      "filename": "LORENA_HUTCHINSON_001",
      "fullname": "LORENA HUTCHINSON",
      "address1": "R. URAL 92 - SACOMA",
      "address2": "SAO PAULO - SP, 04264-060, BRAZIL",
      "accountNumber": "BR1587259546191081969168981N4"
    }
  ]'

echo -e "\n\n=== Test completed! ==="
echo "Check the generated/ folder for output files."
