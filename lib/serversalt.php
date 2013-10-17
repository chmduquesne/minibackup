<?php

/*
 * Copyright (c) 2012 Sébastien SAUVAGE (sebsauvage.net)
 * Copyright (c) 2013 Christophe-Marie Duquesne (chmd.fr)
 *
 * This file has been stolen from Zerobin, and then modified.
 * I am reproducing here the license of Zerobin:
 *
 * This software is provided 'as-is', without any express or implied
 * warranty. In no event will the authors be held liable for any damages
 * arising from the use of this software.
 *
 * Permission is granted to anyone to use this software for any purpose,
 * including commercial applications, and to alter it and redistribute it
 * freely, subject to the following restrictions:
 *
 * 1. The origin of this software must not be misrepresented; you must
 *    not claim that you wrote the original software. If you use this
 *    software in a product, an acknowledgment in the product documentation
 *    would be appreciated but is not required.
 *
 * 2. Altered source versions must be plainly marked as such, and must
 *    not be misrepresented as being the original software.
 *
 * 3. This notice may not be removed or altered from any source
 *    distribution.
 */


/*
 * Crypto-secure function to generate a random number
 *
 * (as opposed to mt_rand and rand, which are NOT crypto-secure, though it
 * should not be a problem to use them here because the output of the
 * random number generator is never shown to anyone)
 */
function crypto_rand($min=NULL, $max=NULL) {
    if ($min == NULL) $min = 0;
    if ($max == NULL) $max = mt_getrandmax();
    $range = $max - $min;
    if ($range == 0) return $min; // not so random...
    $length = (int) (log($range,2) / 8) + 1;
    return $min + (hexdec(bin2hex(openssl_random_pseudo_bytes($length,$s))) % $range);
}

/*
 * Generate a large random hexadecimal string
 */
function random_hexa_string()
{
    $res = "";
    for ($i=0; $i<16; $i++) {
        $res .= base_convert(crypto_rand(),10,16);
    }
    return $res;
}

/*
 * Return the server salt. This is a random string which is unique to each
 * minibackup installation. It is automatically created if not present.
 * The salt is used to generate unique deletion tokens.
 */
function server_salt() {
    $saltfile = 'data/salt.php';
    if (!is_file($saltfile)) {
        file_put_contents($saltfile,'<?php /* |'.random_hexa_string().'| */ ?>');
    }
    $items=explode('|',file_get_contents($saltfile));
    return $items[1];

}

?>
