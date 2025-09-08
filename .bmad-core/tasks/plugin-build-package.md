# Plugin Build and Package Task

## Purpose

Build versioned ZIP packages for Joomla plugin installation and distribution.

## Execution Steps

1. **Environment Check**: Verify build tools are available
2. **Version Detection**: Read current version from Version.php
3. **Clean Build**: Remove existing build artifacts
4. **Package Creation**: Generate ZIP files with version suffixes
5. **Validation**: Verify package integrity and content

## Commands

```bash
# Cross-platform build (zip/tar/PowerShell fallback)
bash tools/build.sh

# Windows PowerShell variants
pwsh tools/build_joomlaboost_simple.ps1
pwsh tools/build_joomlaboost_smart.ps1

# Manual GitHub Actions
# Actions → Build ZIP → Run workflow
```

## Package Structure

```
build/
├── plg_system_joomlaboost-0.1.17.zip    # Main plugin
├── plg_content_offroadmeta-0.1.17.zip   # Content plugin
└── ...                                   # Other components
```

## Success Criteria

- All ZIP files created successfully
- Version suffix matches Version.php
- Package sizes are reasonable (< 100KB typical)
- No missing or corrupted files
- Installation-ready format

## Validation Steps

1. **File Integrity**: Check ZIP can be opened
2. **Content Verification**: Ensure all necessary files included
3. **Version Consistency**: Version in ZIP matches source
4. **Installation Test**: Manual install via Joomla admin

## Distribution

- Upload to GitHub Releases
- Manual installation via Joomla backend
- Team sharing via build artifacts
- Staging environment testing

## Troubleshooting

- **Build fails**: Check zip/tar/PowerShell availability
- **Version mismatch**: Verify Version.php constant
- **Missing files**: Check source directory structure
- **Size issues**: Review included/excluded patterns
