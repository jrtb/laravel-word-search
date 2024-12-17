<?php

namespace App\OpenApi;

/**
 * @OA\Info(
 *     version="1.0.0",
 *     title="Laravel Word Search API",
 *     description="API documentation for Laravel Word Search application, including both internal and external endpoints.",
 *     @OA\Contact(
 *         email="support@fairladymedia.com",
 *         name="API Support"
 *     )
 * )
 * 
 * @OA\Server(
 *     url="http://localhost:8000",
 *     description="Local Development Server"
 * )
 * @OA\Server(
 *     url="https://wordlists.fairladymedia.com",
 *     description="Production Server"
 * )
 * 
 * @OA\ExternalDocumentation(
 *     description="Find more info in README",
 *     url="https://github.com/yourusername/laravel-word-search"
 * )
 */
class OpenApiConfig {} 