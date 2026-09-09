# Strips the costing app back to: materials -> quantity -> product cost.
#
# Deletes the quotation system, the pricing engine and every print-method rate.
# Backups were taken first:
#   D:\GitHub\costing-FULL-BACKUP-before-strip.zip
#   D:\GitHub\costing-DB-BACKUP-before-strip.sql
#
# The application will be broken immediately after this runs -- routes and views
# still reference the deleted classes. That is expected; the rest of the rewrite
# follows.

$ErrorActionPreference = 'Stop'
Set-Location 'D:\GitHub\costing'

$files = @(
    'app\Http\Controllers\ArtworkController.php'
    'app\Http\Controllers\CustomerController.php'
    'app\Http\Controllers\DiscountController.php'
    'app\Http\Controllers\QuoteController.php'
    'app\Http\Controllers\Admin\AdvancedPricingController.php'
    'app\Http\Controllers\Admin\PricingController.php'
    'app\Services\ArtworkService.php'
    'app\Services\PricingVersionService.php'
    'app\Services\QuoteNumberService.php'
    'app\Exceptions\PricingConfigurationException.php'
    'app\Console\Commands\ClearBusinessData.php'
    'app\Console\Commands\RemoveDevelopmentPricingData.php'
    'database\seeders\DevelopmentPricingSeeder.php'
)

$models = @(
    'Addon','Artwork','CapRate','CapType','Customer','CustomerType','DiscountRequest',
    'DtfRate','DtfRateMediaComponent','DtfSizeMultiplier','EmbroideryRate','EmbroideryStitchTier',
    'LaborRate','MarginRule','MaterialGroup','MethodRateMaterialComponent','OverheadRate',
    'PricingVersion','PrintLocation','PrintLocationRate','PrintMethod',
    'ProductRecipe','ProductRecipeItem','ProductSize','QuantityTier',
    'Quote','QuoteAddon','QuoteCalculation','QuoteEffect','QuoteItem','QuotePrintLocation','QuoteSize',
    'RoundingRule','SilkscreenPrintCostTier','SilkscreenRate','SilkscreenSizeMultiplier',
    'SizeSurcharge','SpecialEffect','StickerCutRate','StickerCutType','StickerLaminate',
    'StickerPrintType','StickerRate','SublimationRate','WastageRule'
) | ForEach-Object { "app\Models\$_.php" }

$folders = @(
    'app\Services\Pricing'
    'resources\views\quotes'
    'resources\views\customers'
    'resources\views\artwork'
    'resources\views\admin\pricing'
)

$removed = 0
foreach ($path in ($files + $models)) {
    if (Test-Path $path) { Remove-Item -Force $path; $removed++ }
}
foreach ($path in $folders) {
    if (Test-Path $path) { Remove-Item -Recurse -Force $path; $removed++ }
}

Write-Output ""
Write-Output "removed $removed files and folders"
Write-Output ""
Write-Output "models left:"
Get-ChildItem 'app\Models' -Filter *.php | ForEach-Object { "   $($_.BaseName)" }
Write-Output ""
Write-Output "controllers left:"
Get-ChildItem 'app\Http\Controllers' -Recurse -Filter *.php | ForEach-Object { "   $($_.BaseName)" }
