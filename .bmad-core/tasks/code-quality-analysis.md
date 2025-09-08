# Code Quality Analysis Task

## Purpose
Perform comprehensive code quality analysis using PHPStan and PHPCS to ensure high code standards.

## Execution Steps
1. **Static Analysis**: Run PHPStan level 7 analysis
2. **Style Check**: Validate PSR-12 compliance with PHPCS
3. **Report Generation**: Summarize findings and recommendations
4. **Issue Resolution**: Provide actionable fixes for identified problems

## Commands
```bash
# Full quality analysis
composer run stan && composer run lint

# Individual checks
composer run stan    # PHPStan static analysis
composer run lint    # PHPCS style validation

# Manual tools
vendor/bin/phpstan analyse --memory-limit=1G
vendor/bin/phpcs -n --no-colors -p -s --standard=phpcs.xml
```

## Success Criteria
- PHPStan: Zero errors at level 7
- PHPCS: Zero violations of PSR-12 standards
- All files pass validation
- No security or performance red flags

## Common Issues & Fixes

### PHPStan Issues
- Missing type declarations → Add proper type hints
- Undefined properties → Declare or remove unused code
- Dead code → Remove unreachable code blocks
- Parameter type mismatches → Fix method signatures

### PHPCS Violations
- Indentation errors → Use 4 spaces consistently
- Line length > 120 chars → Break long lines appropriately
- Missing DocBlocks → Add proper documentation
- Naming conventions → Follow PSR-12 naming rules

## Integration Points
- Pre-commit hooks should run this analysis
- CI pipeline must pass these checks
- Pull request reviews should reference results
- Release process requires clean quality report
