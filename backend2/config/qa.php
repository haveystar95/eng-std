<?php

declare(strict_types=1);

/**
 * QA switches. Everything here is off unless somebody switched it on deliberately.
 */
return [
    /*
     * The password-less dev sign-in (`POST /api/v1/auth/dev`).
     *
     * OFF by default, and refused outright when APP_ENV=production whatever this says — see
     * App\Modules\Identity\Domain\Service\DevLoginGate, which is the single rule both the route
     * registration and the sign-in port ask.
     *
     * It exists because Google/Apple sign-in cannot be completed on an iOS simulator, which had
     * been blocking every live QA run of the app.
     */
    'dev_login' => (bool) env('DEV_LOGIN_ENABLED', false),
];
