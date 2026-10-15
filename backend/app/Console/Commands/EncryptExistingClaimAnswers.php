<?php

namespace App\Console\Commands;

use App\Models\Claim;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

/**
 * Re-encrypt claim answers written before the `encrypted:array` cast existed.
 *
 * The migration only widens the column; rows created before it still hold
 * plaintext JSON and would raise a DecryptException on read. This command
 * decrypts nothing - it simply re-saves each legacy value through the model so
 * the cast encrypts it on the way out.
 */
class EncryptExistingClaimAnswers extends Command
{
    protected $signature = 'claims:encrypt-answers {--chunk=200 : Rows per batch}';

    protected $description = 'Re-encrypt claim answers that predate the encrypted cast';

    public function handle(): int
    {
        $migrated = 0;
        $alreadyEncrypted = 0;
        $skipped = 0;

        // Read through the query builder so legacy plaintext rows are inspected
        // WITHOUT the encrypted cast, which would otherwise throw a
        // DecryptException on every pre-migration row.
        DB::table('claims')
            ->select(['id', 'answers'])
            ->orderBy('id')
            ->chunk((int) $this->option('chunk'), function ($rows) use (&$migrated, &$alreadyEncrypted, &$skipped) {
                foreach ($rows as $row) {
                    $raw = $row->answers;

                    if (! is_string($raw) || $raw === '') {
                        $skipped++;

                        continue;
                    }

                    // Values written through the cast are base64 ciphertext and
                    // begin with 'eyJ' (the base64 of '{"'). Anything else is a
                    // legacy plaintext JSON value.
                    if (str_starts_with($raw, 'eyJ')) {
                        $alreadyEncrypted++;

                        continue;
                    }

                    $decoded = json_decode($raw, true);

                    if (! is_array($decoded)) {
                        $this->warn("Claim {$row->id}: value is neither JSON nor ciphertext - skipped.");
                        $skipped++;

                        continue;
                    }

                    // Encrypt the decoded array. Stored via the query builder so
                    // the model's cast does not encrypt it a second time.
                    DB::table('claims')->where('id', $row->id)->update([
                        'answers' => Crypt::encryptString(json_encode($decoded)),
                        'updated_at' => now(),
                    ]);

                    $migrated++;
                }
            });

        $this->info("Encrypted {$migrated} claim(s); {$alreadyEncrypted} already encrypted; {$skipped} skipped.");

        return self::SUCCESS;
    }
}
