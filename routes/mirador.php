<?php

/**
 * @file
 * mirador.php
 */

/**
 * Mirador.
 */
function mirador($args) {
  try {

    // https://vyjij.csb.app/

    $noid = filter_var(
      $args[0],
      FILTER_UNSAFE_RAW,
      FILTER_FLAG_STRIP_LOW
    );

    $lang = 'en';

    $cache = $_ENV['content_dir'] . '/' . $noid . '.' . $lang . '.json';

    if (file_exists($cache)) {
      $raw = file_get_contents($cache);
    } else {
      $endpoint = $_ENV['endpoint'] . '/api/v1/noid/' . $noid;
      $request = Requests::get($endpoint);
      if (
        $request->success &&
        $request->status_code === 200
      ) {
        file_put_contents($cache, $request->body, LOCK_EX);
        $raw = $request->body;
      } else {
        throw new Exception('Not found - Request failed.');
      }
    }
    if ($raw) {
      $body = json_decode($raw);
      $data = [
        'id' => $noid,
        'title' => $body->displayTitle,
        'availableLanguages' => $body->availableLanguages,
        'manifest' => $body->iiif->presentation->manifest,
        'description' => '',
        'summary' => '',
        'og_summary' => '',
      ];
      return [
        'template' => 'mirador.html',
        'data' => $data,
      ];
    }
    else {
      throw new Exception('Viewer request failed.');
    }
  }
  catch (Exception $e) {
    return [
      'template' => 'error.html',
      'data' => [
        'title' => 'Error',
        'body' => $e->getMessage(),
      ],
    ];
  }
}
