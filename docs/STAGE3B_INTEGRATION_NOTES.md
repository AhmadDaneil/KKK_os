# Stage 3B Integration Notes

## Files to merge

- `app/Support/Photoshop/PhotoshopAutoMergeContractV1.php`
- `app/Services/Photoshop/BuildPhotoshopAutoMergeRowService.php`
- `app/Services/Photoshop/ExportPhotoshopAutoMergeCsvService.php`
- `tests/Feature/Merge/PhotoshopAutoMergeContractV1Test.php`
- `docs/PHOTOSHOP_AUTO_MERGE_CONTRACT_V1.md`

## Important blocker before production endpoint

Do not wire an automatic production export endpoint until `qtykad` has an approved database source.

Current Stage 3A canonical payload does not contain quantity.

The service deliberately requires a quantity resolver:

```php
$path = app(ExportPhotoshopAutoMergeCsvService::class)->export(
    $mergeJobs,
    storage_path('app/photoshop/ready-to-merge.csv'),
    fn ($mergeJob) => /* approved DB quantity source */
);
```

Do not replace the resolver with a hard-coded quantity.

## Suggested verification now

After merging:

```powershell
php artisan test --filter=PhotoshopAutoMergeContractV1Test
php artisan test
```

The test intentionally verifies:
- exact 28-column contract
- canonical mapping
- Malaysian date formatting
- design code normalization
- phone normalization
- quantity compatibility conversion from `200 PCS` to `200`

## Stage status

Stage 3B contract audit: DONE / VERIFIED.

Stage 3B production exporter wiring: BLOCKED only by the approved `qtykad` source
(and any future need to automate `gambar` / `flaggambar`).

No business rule was invented.
