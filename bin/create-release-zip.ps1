# PowerShell script to create release zip for Windows

$BIN_DIR = $PSScriptRoot
$BASE_DIR = Split-Path -Parent $BIN_DIR
$RELEASE_DIR = Join-Path $BASE_DIR "release"
$TMP_DIR = Join-Path $RELEASE_DIR "woocommerce-mercadopago"

# Clean release directory
if (Test-Path $RELEASE_DIR) {
    Write-Host "[+] Cleaning release directory..."
    Remove-Item -Path $RELEASE_DIR -Recurse -Force
}

# Create directories
Write-Host "[+] Creating release directory..."
New-Item -ItemType Directory -Path $TMP_DIR -Force | Out-Null

# Copy essential files
Write-Host "[+] Copying files..."
$filesToCopy = @(
    "assets",
    "build",
    "i18n",
    "src",
    "templates",
    "index.php",
    "readme.txt",
    "woocommerce-mercadopago.php",
    "composer.json",
    "composer.lock"
)

foreach ($item in $filesToCopy) {
    $sourcePath = Join-Path $BASE_DIR $item
    $destPath = Join-Path $TMP_DIR $item
    
    if (Test-Path $sourcePath) {
        Copy-Item -Path $sourcePath -Destination $destPath -Recurse -Force
        Write-Host "  Copied: $item"
    } else {
        Write-Warning "  Skipped (not found): $item"
    }
}

# Install composer dependencies
Write-Host "[+] Installing composer dependencies..."
Push-Location $TMP_DIR
composer install --no-dev
composer dump-autoload -o

# Clean up composer files and SDK examples/tests
Write-Host "[+] Cleaning up unnecessary files..."
Remove-Item -Path "composer.json", "composer.lock" -Force -ErrorAction SilentlyContinue

$sdkExamplesPath = Join-Path $TMP_DIR "vendor\mp-plugins\php-sdk\examples"
$sdkTestsPath = Join-Path $TMP_DIR "vendor\mp-plugins\php-sdk\tests"

if (Test-Path $sdkExamplesPath) {
    Remove-Item -Path $sdkExamplesPath -Recurse -Force
}
if (Test-Path $sdkTestsPath) {
    Remove-Item -Path $sdkTestsPath -Recurse -Force
}

# Remove non-minified assets
Write-Host "[+] Removing non-minified assets..."
$assetsPath = Join-Path $TMP_DIR "assets"

# Remove non-minified CSS files
Get-ChildItem -Path $assetsPath -Filter "*.css" -Recurse | 
    Where-Object { $_.Name -notlike "*.min.css" } | 
    Remove-Item -Force

# Remove non-minified JS files
Get-ChildItem -Path $assetsPath -Filter "*.js" -Recurse | 
    Where-Object { $_.Name -notlike "*.min.js" } | 
    Remove-Item -Force

Pop-Location

# Create ZIP file
Write-Host "[+] Creating ZIP file..."
$zipPath = Join-Path $BASE_DIR "woocommerce-mercadopago.zip"

if (Test-Path $zipPath) {
    Remove-Item -Path $zipPath -Force
}

# Compress the plugin directory
Compress-Archive -Path $TMP_DIR -DestinationPath $zipPath -Force

# Clean up release directory
Write-Host "[+] Cleaning up temporary files..."
Remove-Item -Path $RELEASE_DIR -Recurse -Force

Write-Host "[+] Package created successfully: $zipPath"
$zipInfo = Get-Item $zipPath
Write-Host "    Size: $([math]::Round($zipInfo.Length / 1MB, 2)) MB"
