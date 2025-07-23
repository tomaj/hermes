# Code Coverage

This project is configured with comprehensive code coverage reporting for both local development and GitLab CI/CD pipelines.

## GitLab CI/CD Coverage

### Automatic Coverage Reporting
When you push changes to GitLab, the CI pipeline will automatically:

1. **Run all PHPUnit tests** with coverage collection
2. **Generate coverage reports** in multiple formats
3. **Display coverage percentage** in the merge request and pipeline views
4. **Create detailed HTML coverage reports** showing line-by-line coverage
5. **Publish coverage reports** to GitLab Pages (for main/master/develop branches)

### Viewing Coverage in GitLab

#### Pipeline Coverage Badge
- Coverage percentage is displayed in the pipeline view
- Coverage percentage is extracted from PHPUnit output using regex pattern
- Visible in merge requests showing coverage changes

#### Coverage Reports
- **Cobertura XML**: Used by GitLab for diff coverage in merge requests
- **HTML Report**: Detailed line-by-line coverage available as pipeline artifacts
- **JUnit XML**: Test results for GitLab test reporting

#### GitLab Pages Coverage Report
For main branches, detailed HTML coverage reports are published to GitLab Pages:
- URL: `https://[username].gitlab.io/[project-name]/`
- Shows detailed file-by-file coverage with line highlighting
- Color-coded: Green (covered), Red (not covered), Orange (partially covered)

### Accessing Coverage Artifacts
1. Go to your GitLab project
2. Navigate to **CI/CD > Pipelines**
3. Click on a specific pipeline
4. In the right sidebar, click **"Coverage Report"** to download or browse
5. Download the artifacts to view the HTML coverage report locally

## Local Development Coverage

### Prerequisites
Ensure you have Xdebug installed and enabled:

```bash
# Check if Xdebug is installed
php -m | grep xdebug

# Install Xdebug (if not already installed)
# On Ubuntu/Debian:
sudo apt-get install php-xdebug

# On macOS with Homebrew:
brew install php@8.1 # or your PHP version
pecl install xdebug

# On Windows:
# Download appropriate Xdebug DLL and add to php.ini
```

### Running Coverage Locally

#### Basic Coverage Report (Console)
```bash
# Run tests with coverage summary in console
vendor/bin/phpunit --coverage-text
```

#### HTML Coverage Report
```bash
# Generate detailed HTML coverage report
vendor/bin/phpunit --coverage-html coverage_html

# Open the report in your browser
open coverage_html/index.html  # macOS
xdg-open coverage_html/index.html  # Linux
start coverage_html/index.html  # Windows
```

#### XML Coverage Reports
```bash
# Generate Cobertura XML (for IDE integration)
vendor/bin/phpunit --coverage-cobertura coverage.xml

# Generate Clover XML (alternative format)
vendor/bin/phpunit --coverage-clover coverage.xml
```

#### Combined Coverage Report
```bash
# Generate all coverage formats at once
vendor/bin/phpunit \
  --coverage-text \
  --coverage-html coverage_html \
  --coverage-cobertura coverage.xml \
  --log-junit junit.xml
```

### IDE Integration

#### PhpStorm/IntelliJ IDEA
1. Go to **Run > Show Coverage Data**
2. Import the generated `coverage.xml` file
3. View coverage highlighting directly in the editor

#### VS Code
1. Install the "Coverage Gutters" extension
2. Use Command Palette: "Coverage Gutters: Display Coverage"
3. Point to the generated coverage files

## Understanding Coverage Metrics

### Coverage Types
- **Line Coverage**: Percentage of executable lines covered by tests
- **Function Coverage**: Percentage of functions/methods covered by tests
- **Branch Coverage**: Percentage of conditional branches covered by tests

### Coverage Thresholds
The project is configured to:
- Report coverage percentage in GitLab pipelines
- Generate detailed reports for manual review
- *Note: No minimum coverage enforcement is currently configured*

### Improving Coverage
1. **Identify uncovered lines**: Use HTML report to see specific uncovered lines
2. **Write targeted tests**: Focus on red/uncovered lines in the reports
3. **Review branch coverage**: Ensure all conditional paths are tested
4. **Test edge cases**: Don't forget error conditions and boundary cases

## Troubleshooting

### Common Issues

#### "No code coverage driver available"
**Solution**: Install and enable Xdebug
```bash
# Check Xdebug status
php -i | grep xdebug

# Ensure Xdebug mode includes coverage
export XDEBUG_MODE=coverage
```

#### "Permission denied" for coverage files
**Solution**: Ensure write permissions for coverage directories
```bash
chmod -R 755 coverage_html/
```

#### GitLab CI coverage not showing
**Solution**: Check that:
1. Xdebug is properly installed in CI environment
2. Coverage regex pattern matches PHPUnit output
3. Artifacts are properly configured

### Configuration Files
- **PHPUnit**: `phpunit.xml` - Test and coverage configuration
- **GitLab CI**: `.gitlab-ci.yml` - CI/CD pipeline with coverage jobs
- **Composer**: `composer.json` - Dependencies and scripts

### Getting Help
If you encounter issues with coverage reporting:
1. Check the GitLab CI logs for detailed error messages
2. Run coverage locally to isolate CI-specific issues
3. Verify Xdebug configuration and version compatibility
4. Review PHPUnit documentation for coverage options