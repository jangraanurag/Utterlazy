<?php

// v1 route alias — same routes as api.php, under /api/v1/ prefix
// This provides backward-compatible versioned API access.
// When v2 releases, create routes/v2.php with new/modified endpoints.

require __DIR__.'/api.php';
