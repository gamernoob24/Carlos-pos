<?php
/**
 * Error screens.
 */

function not_found_controller()
{
    http_response_code(404);
    return ['title' => 'Page not found'];
}
