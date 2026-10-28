<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Str;
use Tests\TestCase;

class JsaPhase5DecisionIntegrationTest extends TestCase
{
    private string $basePath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->basePath =
            storage_path(
                'framework/testing/jsa-phase5-'
                . Str::uuid()
            );

        config([
            'jsa.handoff_path' =>
                $this->basePath,
        ]);

        $this->publishValidPackage();
    }

    protected function tearDown(): void
    {
        $this->removeDirectory(
            $this->basePath
        );

        parent::tearDown();
    }

    public function test_admin_can_read_verified_decision_package(): void
    {
        $admin = $this->userWithRole('admin', 9101);

        $response = $this
            ->actingAs($admin)
            ->getJson(
                route(
                    'smart-assistant.decisions'
                )
            );

        $response
            ->assertOk()
            ->assertJsonPath(
                'phase',
                5
            )
            ->assertJsonPath(
                'status',
                'READY_FOR_REVIEW'
            )
            ->assertJsonPath(
                'integrity.verified',
                true
            )
            ->assertJsonPath(
                'safety.read_only',
                true
            )
            ->assertJsonPath(
                'safety.auto_restock',
                false
            )
            ->assertJsonPath(
                'safety.stock_mutated',
                false
            )
            ->assertJsonPath(
                'restock.recommendations.0.restock_qty',
                5
            );
    }

    public function test_owner_can_read_decision_package(): void
    {
        $owner = $this->userWithRole('owner', 9102);

        $this
            ->actingAs($owner)
            ->getJson(
                route(
                    'smart-assistant.decisions'
                )
            )
            ->assertOk()
            ->assertJsonPath(
                'integrity.verified',
                true
            );
    }

    public function test_kasir_cannot_read_decision_package(): void
    {
        $kasir = $this->userWithRole('kasir', 9103);

        $this
            ->actingAs($kasir)
            ->getJson(
                route(
                    'smart-assistant.decisions'
                )
            )
            ->assertForbidden();
    }

    public function test_tampered_artifact_is_blocked(): void
    {
        $admin = $this->userWithRole('admin', 9101);

        $latest = $this->readJson(
            $this->basePath
            . DIRECTORY_SEPARATOR
            . 'latest.json'
        );

        $runId = $latest['run_id'];

        $restockPath =
            $this->basePath
            . DIRECTORY_SEPARATOR
            . 'runs'
            . DIRECTORY_SEPARATOR
            . $runId
            . DIRECTORY_SEPARATOR
            . 'artifacts'
            . DIRECTORY_SEPARATOR
            . 'restock.json';

        file_put_contents(
            $restockPath,
            '{}'
        );

        $this
            ->actingAs($admin)
            ->getJson(
                route(
                    'smart-assistant.decisions'
                )
            )
            ->assertStatus(409)
            ->assertJsonPath(
                'status',
                'BLOCKED'
            );
    }

    /**
     * User untuk authorization test tanpa persistence database.
     *
     * Endpoint Fase 5 hanya membutuhkan authenticated user + role.
     */
    private function userWithRole(
        string $role,
        int $id
    ): User {
        $user = new User();

        $user->setAttribute(
            $user->getAuthIdentifierName(),
            $id
        );

        $user->setAttribute(
            'role',
            $role
        );

        $user->exists = true;

        return $user;
    }
    private function publishValidPackage(): void
    {
        $source = [
            'forecast' => [
                'status' => 'ok',
                'forecast_date' =>
                    '2026-10-26',
            ],

            'stock' => [
                'snapshot_date' =>
                    '2026-10-25',
                'menus' => [
                    [
                        'id' => 1,
                        'stok' => 10,
                    ],
                ],
            ],

            'policy' => [
                'service_level' => 0.95,
            ],

            'restock' => [
                'status' => 'ok',
                'recommendations' => [
                    [
                        'menu_id' => 1,
                        'restock_qty' => 5,
                    ],
                ],
            ],
        ];

        $temporary =
            $this->basePath
            . DIRECTORY_SEPARATOR
            . 'source';

        mkdir(
            $temporary,
            0777,
            true
        );

        $records = [];

        foreach (
            $source
            as $name => $payload
        ) {
            $path =
                $temporary
                . DIRECTORY_SEPARATOR
                . $name
                . '.json';

            file_put_contents(
                $path,
                json_encode(
                    $payload,
                    JSON_PRETTY_PRINT
                    | JSON_UNESCAPED_SLASHES
                )
                . PHP_EOL
            );

            $records[$name] = [
                'name' =>
                    $name . '.json',

                'sha256' =>
                    hash_file(
                        'sha256',
                        $path
                    ),

                'size_bytes' =>
                    filesize($path),
            ];
        }

        $hashes = [];

        foreach (
            $records
            as $name => $record
        ) {
            $hashes[$name] =
                $record['sha256'];
        }

        ksort(
            $hashes,
            SORT_STRING
        );

        $fingerprint =
            hash(
                'sha256',
                json_encode(
                    $hashes,
                    JSON_UNESCAPED_SLASHES
                    | JSON_UNESCAPED_UNICODE
                )
            );

        $runId =
            '20261025T083000Z-'
            . substr(
                $fingerprint,
                0,
                12
            );

        $runDirectory =
            $this->basePath
            . DIRECTORY_SEPARATOR
            . 'runs'
            . DIRECTORY_SEPARATOR
            . $runId;

        $artifactDirectory =
            $runDirectory
            . DIRECTORY_SEPARATOR
            . 'artifacts';

        mkdir(
            $artifactDirectory,
            0777,
            true
        );

        foreach (
            array_keys($source)
            as $name
        ) {
            copy(
                $temporary
                . DIRECTORY_SEPARATOR
                . $name
                . '.json',

                $artifactDirectory
                . DIRECTORY_SEPARATOR
                . $name
                . '.json'
            );
        }

        $manifest = [
            'schema_version' => 1,
            'phase' => 4,
            'run_id' => $runId,
            'created_at_utc' =>
                '2026-10-25T08:30:00+00:00',
            'status' =>
                'READY_FOR_REVIEW',
            'read_only' => true,
            'auto_restock' => false,
            'package_fingerprint' =>
                $fingerprint,
            'review_flags' => [],
            'artifacts' => $records,
        ];

        file_put_contents(
            $runDirectory
            . DIRECTORY_SEPARATOR
            . 'manifest.json',

            json_encode(
                $manifest,
                JSON_PRETTY_PRINT
                | JSON_UNESCAPED_SLASHES
            )
            . PHP_EOL
        );

        file_put_contents(
            $this->basePath
            . DIRECTORY_SEPARATOR
            . 'latest.json',

            json_encode(
                [
                    'schema_version' => 1,
                    'run_id' => $runId,
                    'status' =>
                        'READY_FOR_REVIEW',
                    'manifest' =>
                        "runs/{$runId}/manifest.json",
                    'package_fingerprint' =>
                        $fingerprint,
                ],
                JSON_PRETTY_PRINT
                | JSON_UNESCAPED_SLASHES
            )
            . PHP_EOL
        );
    }

    private function readJson(
        string $path
    ): array {
        return json_decode(
            file_get_contents($path),
            true,
            512,
            JSON_THROW_ON_ERROR
        );
    }

    private function removeDirectory(
        string $path
    ): void {
        if (! is_dir($path)) {
            return;
        }

        $items = scandir($path);

        if ($items === false) {
            return;
        }

        foreach ($items as $item) {
            if (
                $item === '.'
                || $item === '..'
            ) {
                continue;
            }

            $child =
                $path
                . DIRECTORY_SEPARATOR
                . $item;

            if (is_dir($child)) {
                $this->removeDirectory(
                    $child
                );

                continue;
            }

            unlink($child);
        }

        rmdir($path);
    }
}
