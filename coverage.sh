#!/bin/bash

# Generate code coverage report locally
echo "Generating code coverage report..."

# Create build directories
mkdir -p build/logs build/coverage

# Run PHPUnit with coverage
vendor/bin/phpunit --coverage-clover build/logs/clover.xml --coverage-html build/coverage

if [ $? -eq 0 ]; then
    echo ""
    echo "✅ Coverage report generated successfully!"
    echo ""
    echo "📊 Coverage reports available at:"
    echo "   - HTML: build/coverage/index.html"
    echo "   - Clover XML: build/logs/clover.xml"
    echo ""
    echo "🌐 Open the HTML report in your browser:"
    if command -v xdg-open &> /dev/null; then
        echo "   xdg-open build/coverage/index.html"
    elif command -v open &> /dev/null; then
        echo "   open build/coverage/index.html"
    else
        echo "   file://$(pwd)/build/coverage/index.html"
    fi
else
    echo ""
    echo "❌ Coverage generation failed!"
    exit 1
fi