<?php

namespace App\Console\Commands;

use App\Models\KycProfile;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;

/**
 * AtomPay and AtomShop share one database but keep their own project folders,
 * so the KYC disk is the one place the two apps must agree on byte-for-byte.
 * Run this in BOTH apps after a deploy: identical output means a document
 * uploaded by one is readable by the other.
 */
class KycStorageCheck extends Command
{
    protected $signature = 'atompay:kyc-check {--prune-check : Also list documents on disk that no profile references}';

    protected $description = 'Verify the shared KYC document store is reachable, writable, and complete';

    /** Columns on atompay_kyc_profiles that hold a path on the shared disk. */
    private const PATH_COLUMNS = ['cnic_front_path', 'cnic_back_path', 'selfie_path', 'verification_form_path'];

    public function handle(): int
    {
        $name = config('atompay.kyc.disk');
        $root = config("filesystems.disks.{$name}.root");
        $disk = Storage::disk($name);
        $ok = true;

        $this->line('');
        $this->line("  Disk   <fg=cyan>{$name}</>");
        $this->line("  Root   <fg=cyan>{$root}</>");
        $this->line('');

        // 1. The directory itself.
        if (! is_dir($root)) {
            $this->error("  Root does not exist. Create it and give both apps' PHP user access.");

            return self::FAILURE;
        }

        $this->state('Readable', is_readable($root), 'the web user cannot list this directory');
        $ok = $this->state('Writable', is_writable($root), 'uploads will fail') && $ok;

        if (! windows_os()) {
            $this->line('  <fg=gray>Mode</>     '.substr(sprintf('%o', fileperms($root)), -4)
                .'  <fg=gray>owner</> '.(function_exists('posix_getpwuid')
                    ? (posix_getpwuid(fileowner($root))['name'] ?? fileowner($root)) : fileowner($root))
                .'  <fg=gray>group</> '.(function_exists('posix_getgrgid')
                    ? (posix_getgrgid(filegroup($root))['name'] ?? filegroup($root)) : filegroup($root)));
        }

        // 2. A root inside the project tree is one `git clean` away from losing ID documents.
        if (str_starts_with(realpath($root), realpath(base_path()))) {
            $this->warn('  Root sits inside the project folder - a redeploy could delete it.');
            $this->line('  <fg=gray>Set ATOMPAY_KYC_ROOT to a directory outside both apps (production only).</>');
        }

        // 3. Round-trip a probe file, which is what the other app will do.
        $probe = config('atompay.kyc.path').'/.probe-'.uniqid();
        try {
            $disk->put($probe, 'ok');
            $ok = $this->state('Round-trip', $disk->get($probe) === 'ok', 'wrote a file but could not read it back') && $ok;
            $disk->delete($probe);
        } catch (\Throwable $e) {
            $this->state('Round-trip', false, $e->getMessage());
            $ok = false;
        }

        // 4. Every document the database promises must actually be on this disk.
        $this->line('');
        $missing = $this->missingDocuments($disk);
        $total = KycProfile::count();

        if ($missing->isEmpty()) {
            $this->info("  All documents present across {$total} KYC profile(s).");
        } else {
            $ok = false;
            $this->error("  {$missing->count()} document(s) recorded in the database are not on this disk:");
            foreach ($missing as $row) {
                $this->line("    <fg=gray>profile #{$row['profile']}  user {$row['user']}  {$row['column']}</>  {$row['path']}");
            }
            $this->line('  <fg=gray>Either the other app wrote them to a different root, or they were lost in a deploy.</>');
        }

        if ($this->option('prune-check')) {
            $this->unreferencedFiles($disk);
        }

        $this->line('');

        return $ok ? self::SUCCESS : self::FAILURE;
    }

    /** Paths held in the database that do not exist on the shared disk. */
    private function missingDocuments($disk): Collection
    {
        return KycProfile::query()
            ->select(['id', 'user_id', ...self::PATH_COLUMNS])
            ->get()
            ->flatMap(fn (KycProfile $p) => collect(self::PATH_COLUMNS)
                ->filter(fn ($c) => filled($p->{$c}) && ! $disk->exists($p->{$c}))
                ->map(fn ($c) => ['profile' => $p->id, 'user' => $p->user_id, 'column' => $c, 'path' => $p->{$c}]))
            ->values();
    }

    /** The reverse check: files taking up space that no profile points at. */
    private function unreferencedFiles($disk): void
    {
        $known = KycProfile::query()
            ->select(self::PATH_COLUMNS)->get()
            ->flatMap(fn ($p) => collect(self::PATH_COLUMNS)->map(fn ($c) => $p->{$c}))
            ->filter()->flip();

        $orphans = collect($disk->allFiles(config('atompay.kyc.path')))
            ->reject(fn ($path) => $known->has($path) || str_contains(basename($path), '.probe-'));

        $this->line('');

        if ($orphans->isEmpty()) {
            $this->info('  No unreferenced files on disk.');

            return;
        }

        $this->warn("  {$orphans->count()} file(s) on disk are referenced by no profile:");
        $orphans->each(fn ($path) => $this->line("    <fg=gray>{$path}</>"));
    }

    private function state(string $label, bool $pass, string $problem): bool
    {
        $this->line(sprintf('  %-9s %s', $label, $pass ? '<fg=green>yes</>' : "<fg=red>no</>  <fg=gray>{$problem}</>"));

        return $pass;
    }
}
