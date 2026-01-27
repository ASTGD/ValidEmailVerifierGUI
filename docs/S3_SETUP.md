# S3 Setup (Local Dev + Production)

This app uses a configurable storage disk. To switch uploads/results to S3, set the S3 disk in `.env` and keep `.env.example` as the reference.

## 1) Required `.env` values

Set these in your local `.env`:

```
FILESYSTEM_DISK=s3
VERIFIER_STORAGE_DISK=s3

AWS_ACCESS_KEY_ID=...
AWS_SECRET_ACCESS_KEY=...
AWS_DEFAULT_REGION=us-east-1
AWS_BUCKET=your-bucket
AWS_URL=
AWS_ENDPOINT=
AWS_USE_PATH_STYLE_ENDPOINT=false
```

Notes:
- `VERIFIER_STORAGE_DISK` controls where job uploads/results are stored.
- `FILESYSTEM_DISK` is the Laravel default disk. Keeping it aligned avoids surprises.
- `AWS_URL` is optional (use for CDN/custom domain).
- `AWS_ENDPOINT` is only needed for S3‑compatible providers (e.g., MinIO).

## 2) Clear config cache

```
./vendor/bin/sail artisan config:clear
```

## 3) Run the app as usual

Local (Sail):
```
./vendor/bin/sail up -d
./vendor/bin/sail artisan queue:work
```

## 4) Test flow (local dev with real S3)

1. Upload a list from the customer portal.
2. Verify the input file appears in S3 under:
   - `uploads/{user_id}/{job_id}/input.csv`
3. Let the worker run. Results will upload to:
   - `results/jobs/{job_id}/valid.csv` (and invalid/risky)
4. Download results from the portal. The file should stream from S3.

## 5) Worker note

Workers use signed URLs provided by Laravel. No direct S3 credentials are required on the worker.
