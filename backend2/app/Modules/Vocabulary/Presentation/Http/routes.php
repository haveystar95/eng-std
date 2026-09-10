<?php

declare(strict_types=1);

// Vocabulary routes (api/v1). Controllers translate input and dispatch Commands/Queries.
// Vocabulary currently exposes no HTTP surface of its own: terms reach the client through
// Collections and /sync. The file stays so the provider's route hook keeps one shape per module.
