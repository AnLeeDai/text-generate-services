# Text Generate Services - Development Commands

## Add these aliases to your ~/.bashrc or ~/.zshrc for easier usage:

# Start Text Generate Services
alias tgs-start='cd /home/deb/source_code/drop_shopping_tool/services/text-generate-services && ./start-dev.sh'

# Quick commands
alias tgs-test='curl -s http://127.0.0.1:8081/api/system/server-info'
alias tgs-home='curl -s http://127.0.0.1:8081/'

## Usage Examples:

# Start the service
tgs-start

# Test API in another terminal
tgs-test

# Open in browser
xdg-open http://127.0.0.1:8081

## Direct commands:

# Start development server
./start-dev.sh

# Check if port is available
lsof -i :8081

# Manual start (if needed)
php artisan serve --host=127.0.0.1 --port=8081
