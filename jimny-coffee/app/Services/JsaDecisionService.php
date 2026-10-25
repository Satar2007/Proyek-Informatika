<?php

namespace App\Services;

use JsonException;
use RuntimeException;

class JsaDecisionService
{
    private const SCHEMA_VERSION = 1;

    private const ALLOWED_STATUSES = [
        'READY_FOR_REVIEW',
        'REVIEW_REQUIRED',
    ];

    /**
     * Membaca paket keputusan terbaru Fase 4.
     *
     * Service ini read-only.
     * Tidak melakukan perubahan stok/database.
     */
    public function latest(): array
    {
        $basePath = $this->basePath();

        $latest = $this->readJson(
            $basePath . DIRECTORY_SEPARATOR . 'latest.json'
        );

        if (
            ($latest['schema_version'] ?? null)
            !== self::SCHEMA_VERSION
        ) {
            throw new RuntimeException(
                'Versi latest.json tidak didukung.'
            );
        }

        $runId = $latest['run_id'] ?? null;

        if (
            ! is_string($runId)
            || ! preg_match(
                '/^\d{8}T\d{6}Z-[a-f0-9]{12}$/',
                $runId
            )
        ) {
            throw new RuntimeException(
                'Run ID JSA tidak valid.'
            );
        }

        /*
         * Jangan percaya path manifest dari latest.json.
         * Bangun path hanya dari run_id yang sudah divalidasi.
         * Ini mencegah path traversal.
         */
        $runDirectory =
            $basePath
            . DIRECTORY_SEPARATOR
            . 'runs'
            . DIRECTORY_SEPARATOR
            . $runId;

        $manifest = $this->readJson(
            $runDirectory
            . DIRECTORY_SEPARATOR
            . 'manifest.json'
        );

        $this->validateManifest(
            $manifest,
            $runId
        );

        $verifiedArtifacts =
            $this->verifyArtifacts(
                $runDirectory,
                $manifest
            );

        $fingerprint =
            $this->verifyFingerprint(
                $manifest,
                $latest
            );

        if (! isset($verifiedArtifacts['restock'])) {
            throw new RuntimeException(
                'Artifact restock tidak tersedia.'
            );
        }

        $restock =
            $this->readJson(
                $verifiedArtifacts['restock']
            );

        return [
            'phase' => 5,

            'run_id' => $runId,

            'created_at_utc' =>
                $manifest['created_at_utc'] ?? null,

            'status' => $manifest['status'],

            'review_flags' =>
                is_array(
                    $manifest['review_flags'] ?? null
                )
                    ? $manifest['review_flags']
                    : [],

            /*
             * Payload Fase 3 disajikan apa adanya.
             * Laravel tidak menghitung ulang restock_qty.
             */
            'restock' => $restock,

            'integrity' => [
                'verified' => true,
                'schema_version' =>
                    self::SCHEMA_VERSION,
                'package_fingerprint' =>
                    $fingerprint,
                'verified_artifacts' =>
                    array_keys(
                        $verifiedArtifacts
                    ),
            ],

            'safety' => [
                'read_only' => true,
                'auto_restock' => false,
                'stock_mutated' => false,
                'reservation_recalculated' =>
                    false,
                'human_review_required' =>
                    true,
            ],
        ];
    }

    private function basePath(): string
    {
        $configured = config(
            'jsa.handoff_path'
        );

        $path =
            is_string($configured)
            && $configured !== ''
                ? $configured
                : storage_path(
                    'app/private/jsa'
                );

        return rtrim(
            $path,
            DIRECTORY_SEPARATOR
        );
    }

    private function validateManifest(
        array $manifest,
        string $runId
    ): void {
        if (
            ($manifest['schema_version'] ?? null)
            !== self::SCHEMA_VERSION
        ) {
            throw new RuntimeException(
                'Schema manifest JSA tidak didukung.'
            );
        }

        if (
            ($manifest['run_id'] ?? null)
            !== $runId
        ) {
            throw new RuntimeException(
                'Run ID latest dan manifest tidak cocok.'
            );
        }

        if (
            ($manifest['read_only'] ?? null)
            !== true
        ) {
            throw new RuntimeException(
                'Manifest tidak ditandai read-only.'
            );
        }

        if (
            ($manifest['auto_restock'] ?? null)
            !== false
        ) {
            throw new RuntimeException(
                'Manifest mengizinkan auto-restock.'
            );
        }

        $status = $manifest['status'] ?? null;

        if (
            ! is_string($status)
            || ! in_array(
                $status,
                self::ALLOWED_STATUSES,
                true
            )
        ) {
            throw new RuntimeException(
                'Status handoff tidak dapat digunakan.'
            );
        }

        if (
            ! is_array(
                $manifest['artifacts'] ?? null
            )
        ) {
            throw new RuntimeException(
                'Manifest tidak memiliki artifacts.'
            );
        }
    }

    /**
     * @return array<string, string>
     */
    private function verifyArtifacts(
        string $runDirectory,
        array $manifest
    ): array {
        $verified = [];

        foreach (
            $manifest['artifacts']
            as $name => $record
        ) {
            if (
                ! is_string($name)
                || ! preg_match(
                    '/^[A-Za-z0-9_-]+$/',
                    $name
                )
                || ! is_array($record)
            ) {
                throw new RuntimeException(
                    'Record artifact tidak valid.'
                );
            }

            $expectedHash =
                $record['sha256'] ?? null;

            if (
                ! is_string($expectedHash)
                || ! preg_match(
                    '/^[a-f0-9]{64}$/',
                    $expectedHash
                )
            ) {
                throw new RuntimeException(
                    "SHA-256 artifact {$name} tidak valid."
                );
            }

            $pattern =
                $runDirectory
                . DIRECTORY_SEPARATOR
                . 'artifacts'
                . DIRECTORY_SEPARATOR
                . $name
                . '.*';

            $matches = glob($pattern);

            if (
                ! is_array($matches)
                || count($matches) !== 1
            ) {
                throw new RuntimeException(
                    "Artifact {$name} tidak unik atau hilang."
                );
            }

            $path = $matches[0];

            if (! is_file($path)) {
                throw new RuntimeException(
                    "Artifact {$name} bukan file."
                );
            }

            $actualHash =
                hash_file(
                    'sha256',
                    $path
                );

            if (
                ! is_string($actualHash)
                || ! hash_equals(
                    $expectedHash,
                    $actualHash
                )
            ) {
                throw new RuntimeException(
                    "Integrity check gagal untuk {$name}."
                );
            }

            if (
                isset($record['size_bytes'])
                && is_int(
                    $record['size_bytes']
                )
                && filesize($path)
                    !== $record['size_bytes']
            ) {
                throw new RuntimeException(
                    "Ukuran artifact {$name} berubah."
                );
            }

            $verified[$name] = $path;
        }

        return $verified;
    }

    private function verifyFingerprint(
        array $manifest,
        array $latest
    ): string {
        $hashes = [];

        foreach (
            $manifest['artifacts']
            as $name => $record
        ) {
            $hashes[$name] =
                $record['sha256'];
        }

        ksort(
            $hashes,
            SORT_STRING
        );

        try {
            $canonical = json_encode(
                $hashes,
                JSON_UNESCAPED_SLASHES
                | JSON_UNESCAPED_UNICODE
                | JSON_THROW_ON_ERROR
            );
        } catch (JsonException $exception) {
            throw new RuntimeException(
                'Fingerprint JSA tidak dapat dihitung.',
                previous: $exception
            );
        }

        $actual =
            hash(
                'sha256',
                $canonical
            );

        $manifestFingerprint =
            $manifest[
                'package_fingerprint'
            ] ?? null;

        $latestFingerprint =
            $latest[
                'package_fingerprint'
            ] ?? null;

        if (
            ! is_string(
                $manifestFingerprint
            )
            || ! hash_equals(
                $manifestFingerprint,
                $actual
            )
        ) {
            throw new RuntimeException(
                'Package fingerprint manifest tidak cocok.'
            );
        }

        if (
            ! is_string(
                $latestFingerprint
            )
            || ! hash_equals(
                $latestFingerprint,
                $actual
            )
        ) {
            throw new RuntimeException(
                'Package fingerprint latest tidak cocok.'
            );
        }

        return $actual;
    }

    private function readJson(
        string $path
    ): array {
        if (! is_file($path)) {
            throw new RuntimeException(
                'Artifact JSA belum tersedia.'
            );
        }

        $contents =
            file_get_contents($path);

        if ($contents === false) {
            throw new RuntimeException(
                'Artifact JSA tidak dapat dibaca.'
            );
        }

        try {
            $decoded = json_decode(
                $contents,
                true,
                512,
                JSON_THROW_ON_ERROR
            );
        } catch (JsonException $exception) {
            throw new RuntimeException(
                'Artifact JSA bukan JSON valid.',
                previous: $exception
            );
        }

        if (! is_array($decoded)) {
            throw new RuntimeException(
                'Struktur JSON JSA tidak valid.'
            );
        }

        return $decoded;
    }
}
