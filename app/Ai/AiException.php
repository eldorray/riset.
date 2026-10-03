<?php

declare(strict_types=1);

namespace App\Ai;

use RuntimeException;

/** Pesan ditampilkan ke pengguna apa adanya, jadi selalu dalam bahasa Indonesia. */
final class AiException extends RuntimeException {}
