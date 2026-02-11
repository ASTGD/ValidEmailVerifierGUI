<?php

namespace App\Services\SeedSend;

use App\Models\VerificationJob;
use Illuminate\Support\Facades\Storage;

class SeedSendRecipientResolver
{
    /**
     * @return array<int, string>
     */
    public function resolve(VerificationJob $job): array
    {
        $disk = trim((string) ($job->output_disk ?: $job->input_disk ?: config('filesystems.default')));
        if ($disk === '') {
            return [];
        }

        $emails = [];
        $keys = array_values(array_filter([
            $job->valid_key,
            $job->invalid_key,
            $job->risky_key,
        ]));

        foreach ($keys as $key) {
            $stream = Storage::disk($disk)->readStream((string) $key);
            if (! is_resource($stream)) {
                continue;
            }

            try {
                while (($line = fgets($stream)) !== false) {
                    $line = trim($line);
                    if ($line === '') {
                        continue;
                    }

                    $columns = str_getcsv($line);
                    if ($columns === []) {
                        continue;
                    }

                    if (strtolower(trim((string) ($columns[0] ?? ''))) === 'email') {
                        continue;
                    }

                    $email = strtolower(trim((string) ($columns[0] ?? '')));
                    if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                        continue;
                    }

                    $emails[$email] = true;
                }
            } finally {
                fclose($stream);
            }
        }

        $resolved = array_keys($emails);
        sort($resolved);

        $maxRecipients = max(1, (int) config('seed_send.recipient_limits.max_per_campaign', 100000));
        if (count($resolved) > $maxRecipients) {
            $resolved = array_slice($resolved, 0, $maxRecipients);
        }

        return $resolved;
    }
}
