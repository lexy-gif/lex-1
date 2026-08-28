<?php
function csrf_token()
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

function csrf_field()
{
    echo '<input type="hidden" name="csrf_token" value="' . htmlentities(csrf_token()) . '">';
}

function csrf_url_param()
{
    return 'csrf_token=' . urlencode(csrf_token());
}

function csrf_is_valid($token)
{
    return is_string($token) && isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

function csrf_require_valid($token)
{
    if (!csrf_is_valid($token)) {
        http_response_code(403);
        exit('Invalid security token. Please go back, refresh the page, and try again.');
    }
}
?>
