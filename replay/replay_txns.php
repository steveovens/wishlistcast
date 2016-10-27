<?php
    $path_to_plugin = 'http://roadahead.choicez.com.au/wp-content/plugins/wishlistcastpro';

    $data = file_get_contents( $path_to_plugin . '/params_' . $f['replay'] );
    if (false === $data) {
        logmsgandClose("Replay file [ {$path_to_plugin}/params_{$f['replay']} not found or empty");
        die("Replay file [ {$path_to_plugin}/params_{$f['replay']} not found or empty");
    }
    $f = unserialize ($data);
    $replaying = true;

?>