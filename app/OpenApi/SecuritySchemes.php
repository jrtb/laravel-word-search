<?php

namespace App\OpenApi;

/**
 * @OA\SecurityScheme(
 *     type="apiKey",
 *     in="header",
 *     securityScheme="csrf",
 *     name="X-CSRF-TOKEN",
 *     description="CSRF token required for internal API endpoints"
 * )
 */
class SecuritySchemes {} 