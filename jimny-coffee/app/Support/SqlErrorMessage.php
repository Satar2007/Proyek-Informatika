<?php

namespace App\Support;

use Illuminate\Support\Facades\Log;
use PDOException;
use Throwable;

final class SqlErrorMessage
{
    public const MESSAGE = 'Operasi database gagal. Silakan coba lagi atau hubungi administrator.';

    public static function forUser(Throwable $error): string
    {
        // A Laravel QueryException is a PDOException. Also inspect wrapped errors.
        for ($cause = $error; $cause !== null; $cause = $cause->getPrevious()) {
            if ($cause instanceof PDOException) {
                Log::error('JIMNY database operation failed.', [
                    'exception_type' => get_class($cause),
                    'code' => (string) $cause->getCode(),
                ]);

                return self::MESSAGE;
            }
        }

        // Preserve existing business-rule messages; other exception types are
        // outside the scope of this targeted database error hardening.
        return $error->getMessage();
    }
}
