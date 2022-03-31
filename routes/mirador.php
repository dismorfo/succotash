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

    $noid = filter_var($args[0], FILTER_UNSAFE_RAW, FILTER_FLAG_STRIP_LOW);

    $lang = 'en';

    $request = select_by_noid($noid, $lang, false);

    if ($request) {
      $data = [
        'nid' => $request->nid,
        'noid' => $request->noid,
        'title' => $request->title,
        'manifest' => $request->manifest,
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
