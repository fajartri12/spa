<?php

declare(strict_types=1);

namespace App\Services\Gateway;

use RuntimeException;

/**
 * Kegagalan komunikasi atau validasi dengan penyedia payment gateway.
 *
 * Pesan aman ditampilkan ke pengguna; detail teknis disimpan pada log.
 */
class GatewayException extends RuntimeException {}
