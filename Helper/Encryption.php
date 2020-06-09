<?php

namespace Ingenico\Import\Helper;

define('ING_ENCRYPT_METHOD', 'AES-256-CBC');
define('ING_SECRET_IV', 'cOwsHp7HPnoZa29Qz0oecw==');

class Encryption
{
    public function encrypt($data, $password)
    {
        // phpcs:disable
        $output = openssl_encrypt($data, ING_ENCRYPT_METHOD, $password, 0, base64_decode(ING_SECRET_IV));
        return base64_encode($output);
        // phpcs:enable
    }

    public function decrypt($data, $password)
    {
        // phpcs:disable
        $output = base64_decode($data);
        return openssl_decrypt($output, ING_ENCRYPT_METHOD, $password, 0, base64_decode(ING_SECRET_IV));
        // phpcs:enable
    }
}
